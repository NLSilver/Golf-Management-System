@extends('layouts.app')

@section('title', 'Customer Data')

@section('content')
    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm mb-3 no-print">&larr; Back to Admin</a>

    <div class="mb-3">
        <h4 class="mb-0 fw-bold">View Customer Data</h4>
        <div class="text-muted small">{{ $customers->count() }} registered customer(s)</div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>FirstName</th>
                    <th>LastName</th>
                    <th>Contact No</th>
                    <th class="text-end">Sessions</th>
                    <th class="text-end">Transactions</th>
                    <th class="text-end">Total spend</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td>{{ $customer->id }}</td>
                        <td>{{ $customer->first_name }}</td>
                        <td>{{ $customer->last_name }}</td>
                        <td>{{ $customer->contact_no ?? '-' }}</td>
                        <td class="text-end">{{ $customer->sessions_count }}</td>
                        <td class="text-end">{{ $customer->transactions_count }}</td>
                        <td class="text-end">&#8369;{{ number_format((float) ($customer->transactions_sum_total ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No customers yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
