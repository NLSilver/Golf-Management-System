@extends('layouts.app')

@section('title', 'Monthly Reports')

@section('content')
    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm mb-3 no-print">&larr; Back to Admin</a>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3 no-print">
        <div>
            <h4 class="mb-0 fw-bold">Monthly Report</h4>
            <div class="text-muted small">
                {{ $start->format('d M Y') }} - {{ $end->format('d M Y') }} &middot; for submission to Davao Eagle Masters Inc.
            </div>
        </div>

        <form method="GET" action="" class="d-flex gap-2 align-items-end flex-wrap">
            <div>
                <label class="form-label small mb-0">Report month</label>
                <select class="form-select form-select-sm" name="month" onchange="this.form.submit()">
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}" @selected($value === $month)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="button" class="btn btn-sm btn-primary" onclick="window.print()">Print / PDF</button>
            <a class="btn btn-sm btn-outline-success"
               href="{{ route('admin.reports.export', ['month' => $month, 'type' => 'transactions']) }}">
                CSV - Transactions
            </a>
            <a class="btn btn-sm btn-outline-success"
               href="{{ route('admin.reports.export', ['month' => $month, 'type' => 'attendance']) }}">
                CSV - DTR
            </a>
        </form>
    </div>

    <div class="d-flex align-items-center justify-content-between border rounded bg-white px-3 py-2 mb-3 d-none d-print-flex">
        <div class="fw-bold">Davao EagleMasters Golf Driving Range - Monthly Report</div>
        <div>{{ $start->format('F Y') }}</div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small text-uppercase">Gross revenue</div>
                <div class="fs-5 fw-bold text-success">&#8369;{{ number_format($grossRevenue, 2) }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small text-uppercase">Transactions</div>
                <div class="fs-5 fw-bold">{{ $transactionCount }}</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small text-uppercase">Bay sessions / hours</div>
                <div class="fs-5 fw-bold">{{ $sessionCount }} / {{ $hoursUsed }}h</div>
            </div></div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                <div class="text-muted small text-uppercase">Customers served</div>
                <div class="fs-5 fw-bold">{{ $customersServed }}</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">1. Operational - Bay Utilization</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr><th>Bay</th><th class="text-end">Sessions</th><th class="text-end">Hours</th><th class="text-end">Customers</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($bayStats as $bay)
                            <tr>
                                <td>{{ $bay['code'] }}</td>
                                <td class="text-end">{{ $bay['sessions'] }}</td>
                                <td class="text-end">{{ $bay['hours'] }}</td>
                                <td class="text-end">{{ $bay['customers'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">No sessions this month.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">2. Transactional - Payment Breakdown</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr><th>Method</th><th class="text-end">Count</th><th class="text-end">Amount</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($byMethod as $method => $row)
                            <tr>
                                <td class="text-capitalize">{{ $method }}</td>
                                <td class="text-end">{{ $row['count'] }}</td>
                                <td class="text-end">&#8369;{{ number_format($row['total'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No payments recorded.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr class="table-light fw-semibold">
                            <td>Total</td>
                            <td class="text-end">{{ $transactionCount }}</td>
                            <td class="text-end">&#8369;{{ number_format($grossRevenue, 2) }}</td>
                        </tr>
                        </tfoot>
                    </table>

                    @if ($topServices->isNotEmpty())
                        <div class="px-3 pb-3">
                            <div class="small text-muted text-uppercase mb-1">Add-ons sold</div>
                            @foreach ($topServices as $service)
                                <div class="d-flex justify-content-between small">
                                    <span>{{ $service->description }}</span>
                                    <span>x{{ 0 + $service->qty }} &middot; &#8369;{{ number_format((float) $service->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Daily Revenue</div>
                <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                        <tr><th>Date</th><th class="text-end">Transactions</th><th class="text-end">Revenue</th></tr>
                        </thead>
                        <tbody>
                        @foreach ($daily as $day)
                            <tr @class(['table-light' => $day['revenue'] > 0])>
                                <td>{{ $day['label'] }}</td>
                                <td class="text-end">{{ $day['transactions'] }}</td>
                                <td class="text-end">&#8369;{{ number_format($day['revenue'], 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="table-light fw-semibold">
                            <td>Total</td>
                            <td class="text-end">{{ $transactionCount }}</td>
                            <td class="text-end">&#8369;{{ number_format($grossRevenue, 2) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">3. Attendance - Employee DTR Summary</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th class="text-end">Days</th>
                            <th class="text-end">Minutes</th>
                            <th class="text-end">Hours</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($employeeStats as $employee)
                            <tr>
                                <td>{{ $employee['employee_no'] }}</td>
                                <td>{{ $employee['name'] }}</td>
                                <td class="text-end">{{ $employee['days'] }}</td>
                                <td class="text-end">{{ $employee['minutes'] }}</td>
                                <td class="text-end">{{ $employee['hours'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">No attendance this month.</td></tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                        <tr class="table-light fw-semibold">
                            <td colspan="3">Total ({{ $attendance->count() }} records)</td>
                            <td class="text-end">{{ $totalMinutes }}</td>
                            <td class="text-end">{{ round($totalMinutes / 60, 2) }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
