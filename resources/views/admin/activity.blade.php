@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm mb-3 no-print">&larr; Back to Admin</a>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Activity Log</h4>
            <div class="text-muted small">Audit trail of allocations, checkouts, extensions, logins and maintenance.</div>
        </div>

        <form method="GET" action="" class="d-flex gap-2 align-items-end">
            <div>
                <label class="form-label small mb-0">Action</label>
                <select class="form-select form-select-sm" name="action">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" {{ $activeAction === $action ? 'selected' : '' }}>
                            {{ $action }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            @if ($activeAction)
                <a href="{{ route('admin.activity') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            @endif
        </form>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th style="width: 15%;">When</th>
                    <th style="width: 14%;">Action</th>
                    <th style="width: 16%;">User</th>
                    <th>Description</th>
                    <th style="width: 12%;">IP</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at?->format('d/m/Y h:i:s a') ?? '-' }}</td>
                        <td><span class="badge text-bg-light font-monospace">{{ $log->action }}</span></td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td class="small">{{ $log->description }}</td>
                        <td class="small text-muted">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No activity recorded yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $logs->links() }}
    </div>
@endsection
