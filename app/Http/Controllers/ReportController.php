<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\BaySession;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Monthly operational, transactional and attendance report. */
    public function index(Request $request)
    {
        [$start, $end] = $this->range($request);

        return view('admin.reports', $this->build($start, $end) + [
            'month' => $start->format('Y-m'),
            'months' => $this->months(),
        ]);
    }

    /** CSV export for corporate submission. */
    public function export(Request $request): StreamedResponse
    {
        [$start, $end] = $this->range($request);
        $type = $request->query('type', 'transactions');

        $filename = 'degms-'.($type === 'attendance' ? 'dtr' : 'transactions').'-'.$start->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($start, $end, $type) {
            $handle = fopen('php://output', 'w');

            if ($type === 'attendance') {
                fputcsv($handle, ['Employee ID', 'First Name', 'Middle Name', 'Last Name', 'Date', 'Clock In', 'Clock Out', 'Minutes Worked']);

                AttendanceRecord::with('employee')
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('date')
                    ->orderBy('clock_in')
                    ->get()
                    ->each(function ($record) use ($handle) {
                        fputcsv($handle, [
                            $record->employee->employee_no,
                            $record->employee->first_name,
                            $record->employee->middle_name,
                            $record->employee->last_name,
                            $record->date->format('Y-m-d'),
                            $record->clock_in->format('Y-m-d H:i:s'),
                            $record->clock_out?->format('Y-m-d H:i:s') ?? '',
                            $record->minutes_worked ?? '',
                        ]);
                    });
            } else {
                fputcsv($handle, ['Receipt No', 'Date', 'Customer', 'Items', 'Payment Method', 'Cashier', 'Subtotal', 'Discount', 'Total', 'Tendered', 'Change']);

                Transaction::with(['customer', 'user', 'items'])
                    ->whereBetween('created_at', [$start, $end])
                    ->orderBy('created_at')
                    ->get()
                    ->each(function ($transaction) use ($handle) {
                        fputcsv($handle, [
                            $transaction->receipt_no,
                            $transaction->created_at->format('Y-m-d H:i:s'),
                            $transaction->customer?->getFullNameAttribute() ?? 'Walk-in',
                            $transaction->items->map(fn ($i) => $i->description.' x'.(0 + $i->quantity))->implode(' | '),
                            $transaction->payment_method,
                            $transaction->user?->name ?? '',
                            $transaction->subtotal,
                            $transaction->discount,
                            $transaction->total,
                            $transaction->amount_tendered,
                            $transaction->change_due,
                        ]);
                    });
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
    {
        $month = (string) $request->query('month', now()->format('Y-m'));

        try {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }

        return [$start, $start->copy()->endOfMonth()];
    }

    /** @return array<string, mixed> */
    private function build(Carbon $start, Carbon $end): array
    {
        $transactions = Transaction::with(['customer', 'user', 'items'])
            ->whereBetween('created_at', [$start, $end])
            ->where('status', Transaction::STATUS_PAID)
            ->get();

        $sessions = BaySession::with('bay')
            ->whereBetween('started_at', [$start, $end])
            ->where('status', BaySession::STATUS_COMPLETED)
            ->get();

        $attendance = AttendanceRecord::with('employee')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $days = (int) $start->daysInMonth();

        $daily = collect(range(0, $days - 1))->map(function (int $offset) use ($start, $transactions) {
            $date = $start->copy()->addDays($offset)->toDateString();
            $rows = $transactions->filter(fn ($t) => $t->created_at->toDateString() === $date);

            return [
                'date' => $date,
                'label' => Carbon::parse($date)->format('d D'),
                'transactions' => $rows->count(),
                'revenue' => (float) $rows->sum('total'),
            ];
        });

        $bayStats = $sessions->groupBy('bay_id')->map(function ($rows) {
            $bay = $rows->first()->bay;

            return [
                'code' => $bay?->code ?? '-',
                'sessions' => $rows->count(),
                'minutes' => (int) $rows->sum(fn (BaySession $s) => $s->totalMinutes()),
                'hours' => round($rows->sum(fn (BaySession $s) => $s->totalMinutes()) / 60, 1),
                'customers' => $rows->pluck('customer_id')->unique()->count(),
            ];
        })->sortBy('code')->values();

        $employeeStats = $attendance->groupBy('employee_id')->map(function ($rows) {
            $employee = $rows->first()->employee;

            return [
                'employee_no' => $employee?->employee_no ?? '-',
                'name' => $employee?->getFullNameAttribute() ?? '-',
                'days' => $rows->pluck('date')->unique()->count(),
                'minutes' => (int) $rows->sum('minutes_worked'),
                'hours' => round($rows->sum('minutes_worked') / 60, 2),
                'open' => $rows->filter(fn ($r) => $r->clock_out === null)->count(),
            ];
        })->sortBy('name')->values();

        return [
            'transactions' => $transactions,
            'sessions' => $sessions,
            'attendance' => $attendance,
            'daily' => $daily,
            'bayStats' => $bayStats,
            'employeeStats' => $employeeStats,
            'grossRevenue' => (float) $transactions->sum('total'),
            'transactionCount' => $transactions->count(),
            'customersServed' => $transactions->pluck('customer_id')->filter()->unique()->count(),
            'sessionCount' => $sessions->count(),
            'hoursUsed' => round($sessions->sum(fn (BaySession $s) => $s->totalMinutes()) / 60, 1),
            'byMethod' => $transactions->groupBy('payment_method')->map(fn ($rows) => [
                'count' => $rows->count(),
                'total' => (float) $rows->sum('total'),
            ]),
            'topServices' => TransactionItem::query()
                ->whereHas('transaction', fn ($q) => $q
                    ->whereBetween('created_at', [$start, $end])
                    ->where('status', Transaction::STATUS_PAID))
                ->where('item_type', '!=', 'bay_time')
                ->selectRaw('item_type, description, SUM(quantity) as qty, SUM(amount) as amount')
                ->groupBy('item_type', 'description')
                ->orderByDesc('amount')
                ->get(),
            'totalMinutes' => (int) $attendance->sum('minutes_worked'),
            'start' => $start,
            'end' => $end,
        ];
    }

    private function months(): array
    {
        return collect(range(0, 11))
            ->map(fn ($i) => now()->subMonths($i))
            ->mapWithKeys(fn ($d) => [$d->format('Y-m') => $d->format('F Y')])
            ->all();
    }
}
