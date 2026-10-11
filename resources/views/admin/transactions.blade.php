@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm mb-3 no-print">&larr; Back to Admin</a>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-0 fw-bold">View Transaction</h4>
            <div class="text-muted small">{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} -
                {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}</div>
        </div>

        <form method="GET" action="" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small mb-0">From</label>
                <input type="date" class="form-control form-control-sm" name="from" value="{{ $from }}">
            </div>
            <div>
                <label class="form-label small mb-0">To</label>
                <input type="date" class="form-control form-control-sm" name="to" value="{{ $to }}">
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <button type="button" class="btn btn-sm btn-outline-secondary"
                    onclick="window.print()">Print</button>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm"><div class="card-body py-3">
                <div class="text-muted small">Transactions</div>
                <div class="fs-5 fw-bold">{{ $transactions->count() }}</div>
            </div></div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm"><div class="card-body py-3">
                <div class="text-muted small">Gross total</div>
                <div class="fs-5 fw-bold text-success">&#8369;{{ number_format($total, 2) }}</div>
            </div></div>
        </div>
        @foreach ($byMethod as $method => $amount)
            <div class="col-md-3">
                <div class="card border-0 shadow-sm"><div class="card-body py-3">
                    <div class="text-muted small text-capitalize">{{ $method }}</div>
                    <div class="fs-5 fw-bold">&#8369;{{ number_format($amount, 2) }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Method</th>
                    <th>Cashier</th>
                    <th class="text-end">Total</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>
                            <a href="{{ route('receipts.show', $transaction) }}" target="_blank" class="text-decoration-none">
                                {{ $transaction->receipt_no }} &#128424;
                            </a>
                        </td>
                        <td>{{ $transaction->created_at->format('d/m/Y h:i a') }}</td>
                        <td>{{ $transaction->customer?->getFullNameAttribute() ?? 'Walk-in' }}</td>
                        <td class="small">
                            {{ $transaction->items->map(fn ($i) => $i->description.' x'.(0 + $i->quantity))->implode(', ') }}
                        </td>
                        <td class="text-capitalize">{{ $transaction->payment_method }}</td>
                        <td>{{ $transaction->user?->name ?? '-' }}</td>
                        <td class="text-end">&#8369;{{ number_format((float) $transaction->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No transactions in this range.</td></tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr class="fw-semibold table-light">
                    <td colspan="6">Total</td>
                    <td class="text-end">&#8369;{{ number_format($total, 2) }}</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endsection
