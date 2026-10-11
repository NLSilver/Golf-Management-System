<?php

namespace App\Http\Controllers;

use App\Models\Bay;
use App\Models\BayQueue;
use App\Models\BaySession;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Front desk dashboard: live bay grid + allocation panel. */
    public function show(Request $request)
    {
        // Close sessions that ran past their end + grace before rendering,
        // so freed bays and their unpaid receipts show up immediately.
        BaySession::autoFinishExpired();

        $bays = Bay::query()
            ->where('is_active', true)
            ->orderBy('bay_number')
            ->with(['activeSession.customer', 'pendingCustomer.customer'])
            ->get();

        return view('dashboard', [
            'bays' => $bays,
            'services' => Service::query()->active()->get(),
            'bayService' => Service::bayTime(),
            'minMinutes' => Setting::minutes('minimum_session_minutes', 90),
            'expiringMinutes' => Setting::minutes('expiring_soon_minutes', 10),
            'extensionOptions' => array_map('intval', explode(',', (string) Setting::getValue('extension_options', '15,20,90'))),
            'customers' => Customer::query()->orderBy('last_name')->get(),
            'openQueue' => BayQueue::query()->where('status', BayQueue::STATUS_WAITING)
                ->with(['customer', 'bay'])->oldest('id')->get(),
            'unsettled' => Transaction::query()
                ->where('status', Transaction::STATUS_UNPAID)
                ->with(['customer', 'baySession.bay'])
                ->latest('id')->get(),
        ]);
    }

    /** Polled by the dashboard every second for the live countdowns. */
    public function status(Request $request): JsonResponse
    {
        // This endpoint runs every second, and every request ages session flash
        // data. Without re-flashing here, a poll tick landing between a POST
        // redirect and its page GET silently swallows the success/error message.
        $request->session()->reflash();

        // The sweep runs here too: a session past end + grace closes on the
        // next tick and the bay flips back to Free without a page reload.
        BaySession::autoFinishExpired();

        $expiring = Setting::minutes('expiring_soon_minutes', 10);

        $bays = Bay::query()
            ->where('is_active', true)
            ->orderBy('bay_number')
            ->with(['activeSession.customer', 'pendingCustomer.customer'])
            ->get()
            ->map(function (Bay $bay) use ($expiring) {
                $session = $bay->activeSession;
                $pending = $bay->pendingCustomer;

                return [
                    'id' => $bay->id,
                    'code' => $bay->code,
                    'bay_number' => $bay->bay_number,
                    'out_of_order' => $bay->is_out_of_order,
                    'status' => $bay->status,
                    'session_id' => $session?->id,
                    'session_no' => $session?->session_no,
                    'customer' => $session?->customer?->getFullNameAttribute(),
                    'pending_customer' => $pending?->customer?->getFullNameAttribute(),
                    'seconds_left' => $session ? $session->secondsRemaining() : null,
                    'expiring' => $session ? $session->isExpiringSoon($expiring) : false,
                    'overstayed' => $session ? $session->isExpired() : false,
                    'total_minutes' => $session?->totalMinutes(),
                ];
            });

        return response()->json(['bays' => $bays, 'now' => now()->toIso8601String()]);
    }

    /** Type-ahead used by the "Search Customer by Id or LastName" field. */
    public function searchCustomers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $customers = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('last_name', 'like', "%{$q}%")
                        ->orWhere('first_name', 'like', "%{$q}%")
                        ->orWhere('id', $q);
                });
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get()
            ->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->getFullNameAttribute(),
                'contact_no' => $customer->contact_no,
            ]);

        return response()->json(['customers' => $customers]);
    }
}
