<?php

namespace Database\Seeders;

use App\Models\BaySession;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Bills every completed session (bay time + rotating add-ons, occasional
 * discount) and adds a few last-month walk-in sales so both the "revenue
 * last month" card and the monthly report have history to show.
 */
class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        if (Transaction::query()->exists()) {
            return;
        }

        $bayService = Service::bayTime();
        $rate = (float) ($bayService?->rate ?? 300);
        $addons = Service::query()->active()
            ->where('category', '!=', Service::CATEGORY_BAY_TIME)
            ->orderBy('id')
            ->get();
        $frontdesk = User::query()->where('username', 'frontdesk')->first();
        $admin = User::query()->where('username', 'admin')->first();
        $methods = [Transaction::METHOD_CASH, Transaction::METHOD_CASH, Transaction::METHOD_GCASH, Transaction::METHOD_MAYA, Transaction::METHOD_CARD];

        // ---- Bill every completed session that has no receipt yet ----------
        $sessions = BaySession::with(['bay', 'customer'])
            ->where('status', BaySession::STATUS_COMPLETED)
            ->whereDoesntHave('transaction')
            ->orderBy('actual_end_at')
            ->get();

        foreach ($sessions as $i => $session) {
            $minutes = max(90, (int) $session->planned_minutes + (int) $session->extensions()->sum('minutes'));
            $bayAmount = round($rate * $minutes / 60, 2);

            $discount = $i % 9 === 0 ? 50.00 : 0.0;
            $method = $methods[$i % count($methods)];

            $transaction = Transaction::query()->create([
                'receipt_no' => null, // auto RC-xxxxxx
                'customer_id' => $session->customer_id,
                'bay_session_id' => $session->id,
                'user_id' => ($i % 4 === 0 ? $admin : $frontdesk)?->id,
                'discount' => $discount,
                'payment_method' => $method,
                'status' => Transaction::STATUS_PAID,
                'paid_at' => $session->actual_end_at,
            ]);

            $transaction->items()->create([
                'service_id' => $bayService?->id,
                'item_type' => Service::CATEGORY_BAY_TIME,
                'description' => "{$session->bay->code} bay time ({$minutes} min)",
                'quantity' => 1,
                'unit_price' => $bayAmount,
                'amount' => $bayAmount,
                'meta' => ['minutes' => $minutes, 'session_no' => $session->session_no],
            ]);

            // Every third guest picks up a rental, a lesson or a snack.
            if ($i % 3 === 0 && $addons->isNotEmpty()) {
                $addon = $addons[$i % $addons->count()];
                $qty = $i % 2 === 0 ? 1 : 2;
                $amount = round((float) $addon->rate * $qty, 2);

                $transaction->items()->create([
                    'service_id' => $addon->id,
                    'item_type' => $addon->category,
                    'description' => $addon->name,
                    'quantity' => $qty,
                    'unit_price' => $addon->rate,
                    'amount' => $amount,
                    'meta' => null,
                ]);
            }

            $transaction->refreshTotals();

            $total = (float) $transaction->total;
            $tendered = ceil(($total + 0.01) / 50) * 50; // change from a 50-peso step note

            $transaction->forceFill([
                'amount_tendered' => $tendered,
                'change_due' => round($tendered - $total, 2),
            ])->save();

            // Keep created_at aligned with the sale so reports bucket correctly.
            $transaction->forceFill(['created_at' => $session->actual_end_at])->save();
        }

        // ---- Last-month walk-in sales (no session) ------------------------
        $customers = Customer::query()->orderBy('id')->get();

        foreach (range(18, 26, 2) as $daysAgo) {
            $when = now()->subDays($daysAgo)->setTime(14, 10);
            $addon = $addons->isNotEmpty() ? $addons[$daysAgo % $addons->count()] : null;

            if (! $addon) {
                continue;
            }

            $transaction = Transaction::query()->create([
                'receipt_no' => null,
                'customer_id' => $customers[$daysAgo % $customers->count()]->id,
                'bay_session_id' => null,
                'user_id' => $frontdesk?->id,
                'discount' => 0,
                'payment_method' => $methods[$daysAgo % count($methods)],
                'status' => Transaction::STATUS_PAID,
                'paid_at' => $when,
            ]);

            $transaction->items()->create([
                'service_id' => $addon->id,
                'item_type' => $addon->category,
                'description' => $addon->name,
                'quantity' => 2,
                'unit_price' => $addon->rate,
                'amount' => round((float) $addon->rate * 2, 2),
                'meta' => null,
            ]);

            $transaction->refreshTotals();

            $total = (float) $transaction->total;
            $tendered = ceil(($total + 0.01) / 50) * 50;

            $transaction->forceFill([
                'amount_tendered' => $tendered,
                'change_due' => round($tendered - $total, 2),
                'created_at' => $when,
            ])->save();
        }
    }
}
