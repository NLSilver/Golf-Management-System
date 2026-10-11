@extends('layouts.app')

@section('title', 'Login Accounts')

@section('content')
    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm mb-3 no-print">&larr; Back to Admin</a>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h4 class="mb-0 fw-bold">Login Accounts</h4>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addUserModal">&#65291; New account</button>
            </div>
            <div class="text-muted small">Admins see reports and settings; front desk runs the bay monitor and DTR.</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Linked employee</th>
                    <th>Role</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($users as $account)
                    <tr>
                        <td class="fw-semibold">{{ $account->username }}</td>
                        <td>{{ $account->name }}</td>
                        <td>{{ $account->employee?->getFullNameAttribute() ?? '-' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $account->isAdmin() ? 'primary' : 'secondary' }}">
                                {{ $account->isAdmin() ? 'Admin' : 'Front Desk' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <button type="button" class="btn btn-outline-secondary btn-sm"
                                        data-bs-toggle="modal"
                                        data-bs-target="#resetPasswordModal-{{ $account->id }}">Reset password</button>

                                @if ($account->id === auth()->id())
                                    <span class="badge text-bg-light align-self-center">your account</span>
                                @else
                                    <form action="{{ route('admin.users.role', $account) }}" method="POST" class="m-0">
                                        @csrf
                                        <input type="hidden" name="role"
                                               value="{{ $account->isAdmin() ? \App\Models\User::ROLE_STAFF : \App\Models\User::ROLE_ADMIN }}">
                                        <button type="submit" class="btn btn-outline-{{ $account->isAdmin() ? 'secondary' : 'primary' }} btn-sm">
                                            {{ $account->isAdmin() ? 'Make front desk' : 'Make admin' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No login accounts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" action="{{ route('admin.users.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserLabel">New login account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="user-username" class="form-label small fw-semibold">Username</label>
                            <input type="text" class="form-control" id="user-username" name="username" required
                                   value="{{ old('username') }}" placeholder="e.g. maria.s">
                            <div class="form-text">Letters, numbers, dashes and dots only.</div>
                        </div>
                        <div class="col-12">
                            <label for="user-name" class="form-label small fw-semibold">Full name</label>
                            <input type="text" class="form-control" id="user-name" name="name" required
                                   value="{{ old('name') }}" placeholder="e.g. Maria Santos">
                        </div>
                        <div class="col-12">
                            <label for="user-role" class="form-label small fw-semibold">Role</label>
                            <select class="form-select" id="user-role" name="role" required>
                                <option value="staff" @selected(old('role') === 'staff')>Front desk (bay monitor, DTR, checkout)</option>
                                <option value="admin" @selected(old('role') === 'admin')>Admin (everything + reports &amp; settings)</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="user-employee" class="form-label small fw-semibold">
                                Linked employee <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <select class="form-select" id="user-employee" name="employee_id">
                                <option value="">None</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}" @selected((int) old('employee_id') === $employee->id)>
                                        {{ $employee->employee_no }} &middot; {{ $employee->getFullNameAttribute() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="user-password" class="form-label small fw-semibold">Password</label>
                            <input type="text" class="form-control" id="user-password" name="password" required
                                   minlength="6" value="" placeholder="At least 6 characters">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create account</button>
                </div>
            </form>
        </div>
    </div>

    {{-- One server-rendered modal per account: the name and form action are baked
         in, so there is no shared-modal JS that can silently fail to populate. --}}
    @foreach ($users as $account)
        <div class="modal fade" id="resetPasswordModal-{{ $account->id }}" tabindex="-1"
             aria-labelledby="resetPasswordLabel-{{ $account->id }}" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" action="{{ route('admin.users.password', $account) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="resetPasswordLabel-{{ $account->id }}">
                            Reset password for {{ $account->name ? $account->name.' ('.$account->username.')' : $account->username }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label for="reset-password-{{ $account->id }}" class="form-label small fw-semibold">New password</label>
                        <input type="text" class="form-control" id="reset-password-{{ $account->id }}" name="password" required
                               minlength="6" placeholder="At least 6 characters">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save password</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
@endsection
