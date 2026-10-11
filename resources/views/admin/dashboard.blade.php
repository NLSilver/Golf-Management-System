@extends('layouts.app')

@section('title', 'Admin Dashboard')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Dashboard <span class="text-muted small">(Admin)</span></h4>
            <div class="text-muted small">Welcome, {{ auth()->user()->name }} &middot; {{ now()->format('d/m/Y') }}</div>
        </div>
        <div class="btn-group">
            <a href="{{ route('admin.transactions') }}" class="btn btn-outline-success btn-sm">View Transaction</a>
            <a href="{{ route('admin.reports') }}" class="btn btn-outline-success btn-sm">Monthly Reports</a>
            <a href="{{ route('admin.employees') }}" class="btn btn-outline-success btn-sm">View Employee Records</a>
            <a href="{{ route('admin.customers') }}" class="btn btn-outline-success btn-sm">View Customer Data</a>
            <a href="{{ route('admin.users') }}" class="btn btn-outline-success btn-sm">Login Accounts</a>
            <a href="{{ route('admin.activity') }}" class="btn btn-outline-success btn-sm">Activity Log</a>
            <a href="{{ route('admin.settings') }}" class="btn btn-outline-success btn-sm">Settings</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Today's Revenue</div>
                    <div class="fs-4 fw-bold text-success">&#8369;{{ number_format($todayRevenue, 2) }}</div>
                    <div class="small text-muted">{{ $todayTransactions }} transaction(s)</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Revenue Last Month</div>
                    <div class="fs-4 fw-bold">&#8369;{{ number_format($lastMonthRevenue, 2) }}</div>
                    <div class="small text-muted">{{ now()->subMonth()->format('F Y') }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Total Customers Today</div>
                    <div class="fs-4 fw-bold">{{ $customersToday }}</div>
                    <div class="small text-muted">{{ $totalCustomers }} registered overall</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Bays Occupied</div>
                    <div class="fs-4 fw-bold">{{ $activeSessions }} / {{ $bays->count() }}</div>
                    <div class="small text-muted">{{ $totalEmployees }} active employees</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Daily Revenue Graph (last 14 days)</div>
                <div class="card-body">
                    <div class="d-flex align-items-end gap-1" style="height: 170px;">
                        @foreach ($chart as $day)
                            <div class="flex-fill h-100 d-flex flex-column justify-content-end text-center"
                                 title="{{ $day['label'] }}: ₱{{ number_format($day['revenue'], 2) }}">
                                <div class="small text-muted mb-1" style="font-size: .6rem !important;">
                                    {{ $day['revenue'] > 0 ? number_format($day['revenue']) : '' }}
                                </div>
                                <div class="bg-success rounded-top"
                                     style="height: {{ max(2, (int) round($day['revenue'] / $chartMax * 100)) }}%;"></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="d-flex gap-1 mt-1">
                        @foreach ($chart as $day)
                            <div class="flex-fill text-center text-muted" style="font-size: .58rem;">
                                {{ \Illuminate\Support\Carbon::parse($day['date'])->format('d') }}
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Latest Transactions</div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Receipt</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th class="text-end">Total</th>
                            <th>Time</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($recentTransactions as $transaction)
                            <tr>
                                <td>
                                    <a href="{{ route('receipts.show', $transaction) }}" target="_blank" class="text-decoration-none">
                                        {{ $transaction->receipt_no }} &#128424;
                                    </a>
                                </td>
                                <td>{{ $transaction->customer?->getFullNameAttribute() ?? 'Walk-in' }}</td>
                                <td><span class="badge text-bg-light text-capitalize">{{ $transaction->payment_method }}</span></td>
                                <td class="text-end">&#8369;{{ number_format((float) $transaction->total, 2) }}</td>
                                <td>{{ $transaction->created_at->format('d/m h:i a') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No transactions yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white fw-semibold">Monitor Bay - live status</div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach ($bays as $bay)
                            @php
                                $session = $bay->activeSession;
                                $state = $bay->is_out_of_order ? 'secondary' : ($session ? 'primary' : 'success');
                            @endphp
                            <div class="col-4">
                                <div class="border rounded p-2 text-center bg-{{ $state }} bg-opacity-10">
                                    <div class="fw-bold">{{ $bay->code }}</div>
                                    <div class="small {{ $session ? 'text-primary' : ($bay->is_out_of_order ? 'text-secondary' : 'text-success') }}">
                                        {{ $bay->is_out_of_order ? 'Maintenance' : ($session ? 'Busy' : 'Free') }}
                                    </div>
                                    <div class="small text-muted">
                                        {{ $session ? $session->customer->getFullNameAttribute() : '-' }}
                                    </div>
                                    @unless ($session)
                                        <button type="submit" form="maint-{{ $bay->id }}"
                                                class="btn btn-link btn-sm p-0 small text-decoration-none">
                                            {{ $bay->is_out_of_order ? 'Back in service' : 'Mark for maintenance' }}
                                        </button>
                                    @endunless
                                </div>
                                <form id="maint-{{ $bay->id }}" method="POST"
                                      action="{{ route('admin.bays.maintenance', $bay) }}" class="d-none">
                                    @csrf
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Employee Records - today</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr><th>Employee</th><th>In</th><th>Out</th></tr>
                        </thead>
                        <tbody>
                        @forelse ($attendanceToday as $record)
                            <tr>
                                <td>{{ $record->employee->getFullNameAttribute() }}</td>
                                <td>{{ $record->clock_in->format('h:i a') }}</td>
                                <td>{{ $record->clock_out?->format('h:i a') ?? 'on shift' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-muted py-4">No DTR entries today.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
