<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Bay;
use App\Models\BayQueue;
use App\Models\BaySession;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BayController extends Controller
{
    /** Allocate a bay to a new or returning customer (minimum 1h30m enforced). */
    public function allocate(Request $request, Bay $bay)
    {
        $minimum = Setting::minutes('minimum_session_minutes', 90);

        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'first_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:50'],
            'minutes' => ['required', 'integer', 'min:'.$minimum, 'max:720'],
        ]);

        if (! $bay->isFree()) {
            return back()->withErrors(['bay' => "{$bay->code} is no longer available."]);
        }

        $waiting = $bay->pendingCustomer()->first();

        $customerId = $data['customer_id'] ?? null;

        $customer = $customerId
            ? Customer::query()->findOrFail($customerId)
            : Customer::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'contact_no' => $data['contact_no'] ?? null,
            ]);

        DB::transaction(function () use ($bay, $customer, $waiting, $data) {
            $session = BaySession::create([
                'bay_id' => $bay->id,
                'customer_id' => $customer->id,
                'service_id' => Service::bayTime()?->id,
                'user_id' => auth()->id(),
                'started_at' => now(),
                'planned_minutes' => (int) $data['minutes'],
                'ends_at' => now()->addMinutes((int) $data['minutes']),
                'status' => BaySession::STATUS_ACTIVE,
            ]);

            if ($waiting) {
                $waiting->forceFill([
                    'status' => $waiting->customer_id === $customer->id
                        ? BayQueue::STATUS_SEATED
                        : BayQueue::STATUS_CANCELLED,
                    'served_at' => now(),
                ])->save();
            }

            ActivityLog::record(
                'bay.allocate',
                "{$session->session_no} - {$customer->getFullNameAttribute()} allocated to {$bay->code} for {$data['minutes']} min",
                $session,
            );
        });

        return back()->with('success', "{$bay->code} allocated to {$customer->getFullNameAttribute()}.");
    }

    /** Add a customer to the waiting list for a busy bay (Pending Spot). */
    public function queue(Request $request, Bay $bay)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'first_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'last_name' => ['required_without:customer_id', 'nullable', 'string', 'max:255'],
            'contact_no' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $customerId = $data['customer_id'] ?? null;

        $customer = $customerId
            ? Customer::query()->findOrFail($customerId)
            : Customer::query()->create([
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'contact_no' => $data['contact_no'] ?? null,
            ]);

        $entry = BayQueue::query()->create([
            'bay_id' => $bay->id,
            'customer_id' => $customer->id,
            'status' => BayQueue::STATUS_WAITING,
            'queued_at' => now(),
            'user_id' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        ActivityLog::record('bay.queue', "{$customer->getFullNameAttribute()} queued for {$bay->code}", $entry);

        return back()->with('success', "{$customer->getFullNameAttribute()} added to {$bay->code}'s waiting list.");
    }

    public function cancelQueue(BayQueue $bayQueue)
    {
        $bayQueue->forceFill(['status' => BayQueue::STATUS_CANCELLED, 'served_at' => now()])->save();

        ActivityLog::record('bay.queue.cancel', "Queue entry for {$bayQueue->bay->code} cancelled", $bayQueue);

        return back()->with('success', 'Waiting entry removed.');
    }

    /** Add Time: +15m / +20m / +1h30m. */
    public function extend(Request $request, BaySession $baySession)
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:5', 'max:720'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $baySession->isActive()) {
            return back()->withErrors(['session' => 'This session is already closed.']);
        }

        $rate = $baySession->service?->rate ?? Service::bayTime()?->rate ?? 0;
        $amount = round(((int) $data['minutes'] / 60) * (float) $rate, 2);

        DB::transaction(function () use ($baySession, $data, $amount) {
            $baySession->extend(
                minutes: (int) $data['minutes'],
                user: auth()->user(),
                reason: $data['reason'] ?? 'Add Time',
            );

            $baySession->extensions()->latest('id')->first()?->forceFill(['amount' => $amount])->save();

            ActivityLog::record('session.extend', "{$baySession->session_no} extended by {$data['minutes']} min", $baySession);
        });

        return back()->with('success', "{$baySession->session_no} extended by {$data['minutes']} minutes.");
    }

    /** End the session, bill it and take the payment. */
    public function checkout(Request $request, BaySession $baySession)
    {
        $minimum = Setting::minutes('minimum_session_minutes', 90);

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(array_keys($this->paymentMethods()))],
            'amount_tendered' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'addons' => ['nullable', 'array'],
            'addons.*.service_id' => ['required', 'integer', 'exists:services,id'],
            'addons.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        if (! $baySession->isActive()) {
            return back()->withErrors(['session' => 'This session is already closed.']);
        }

        $result = DB::transaction(function () use ($baySession, $data, $minimum) {
            $minutes = max($minimum, $baySession->totalMinutes());
            $rate = (float) ($baySession->service?->rate ?? Service::bayTime()?->rate ?? 0);

            $transaction = Transaction::create([
                'customer_id' => $baySession->customer_id,
                'bay_session_id' => $baySession->id,
                'user_id' => auth()->id(),
                'discount' => (float) ($data['discount'] ?? 0),
                'payment_method' => $data['payment_method'],
                'status' => Transaction::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $items = [[
                'service_id' => $baySession->service_id,
                'item_type' => Service::CATEGORY_BAY_TIME,
                'description' => "{$baySession->bay->code} bay time ({$minutes} min)",
                'quantity' => 1,
                'unit_price' => round($rate * $minutes / 60, 2),
                'amount' => round($rate * $minutes / 60, 2),
                'meta' => ['minutes' => $minutes, 'session_no' => $baySession->session_no],
            ]];

            foreach ($data['addons'] ?? [] as $addon) {
                $service = Service::query()->findOrFail($addon['service_id']);
                $amount = round($service->rate * (int) $addon['quantity'], 2);

                $items[] = [
                    'service_id' => $service->id,
                    'item_type' => $service->category,
                    'description' => $service->name,
                    'quantity' => (int) $addon['quantity'],
                    'unit_price' => $service->rate,
                    'amount' => $amount,
                    'meta' => null,
                ];
            }

            foreach ($items as $item) {
                $transaction->items()->create($item);
            }

            $transaction->refreshTotals();

            $tendered = (float) $data['amount_tendered'];
            $total = (float) $transaction->total;

            if ($tendered + 0.001 < $total) {
                abort(422, 'Amount tendered is less than the total due.');
            }

            $transaction->forceFill([
                'amount_tendered' => $tendered,
                'change_due' => round($tendered - $total, 2),
            ])->save();

            $baySession->finish();

            return $transaction;
        });

        ActivityLog::record(
            'checkout',
            sprintf('%s settled for %s (%s %s)', $baySession->session_no, $result->receipt_no, $result->payment_method, $result->total),
            $result,
        );

        return redirect()->route('dashboard')
            ->with('success', "Receipt {$result->receipt_no} settled - total ₱".number_format((float) $result->total, 2).'.')
            ->with('receipt_url', route('receipts.show', $result))
            ->with('receipt_label', $result->receipt_no);
    }

    /** Available payment methods (card / e-wallet rows are placeholders, no gateway). */
    public function paymentMethods(): array
    {
        return [
            Transaction::METHOD_CASH => 'Cash',
            Transaction::METHOD_GCASH => 'GCash',
            Transaction::METHOD_MAYA => 'Maya',
            Transaction::METHOD_CARD => 'Card',
            Transaction::METHOD_EBANKING => 'E-Banking',
        ];
    }
}
