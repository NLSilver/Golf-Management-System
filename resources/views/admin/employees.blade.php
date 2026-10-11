@extends('layouts.app')

@section('title', 'Employee Records')

@section('content')
    <a href="{{ route('admin.index') }}" class="btn btn-outline-secondary btn-sm mb-3 no-print">&larr; Back to Admin</a>

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <h4 class="mb-0 fw-bold">View Employee Records</h4>
                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                        data-bs-target="#addEmployeeModal">&#65291; Add employee</button>
            </div>
            <div class="text-muted small">Attendance for {{ \Illuminate\Support\Carbon::parse($today)->format('d/m/Y') }}</div>
        </div>
        <div class="text-end">
            <div class="text-muted small">Total minutes logged today</div>
            <div class="fs-5 fw-bold">{{ $totalMinutesToday }}</div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Contact</th>
                    <th>Clock-in</th>
                    <th>Clock-out</th>
                    <th class="text-end">Minutes</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($employees as $employee)
                    @php $record = $employee->todayRecord; @endphp
                    <tr>
                        <td>{{ $employee->employee_no }}</td>
                        <td>{{ $employee->getFullNameAttribute() }}</td>
                        <td>{{ $employee->position ?? '-' }}</td>
                        <td>{{ $employee->contact_no ?? '-' }}</td>
                        <td>{{ $record?->clock_in?->format('h:i:s a') ?? '-' }}</td>
                        <td>{{ $record?->clock_out?->format('h:i:s a') ?? '-' }}</td>
                        <td class="text-end">{{ $record?->minutes_worked ?? '-' }}</td>
                        <td>
                            @if ($record && ! $record->clock_out)
                                <span class="badge text-bg-success">On shift</span>
                            @elseif ($record)
                                <span class="badge text-bg-secondary">Done</span>
                            @else
                                <span class="badge text-bg-light text-dark">No record</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No employees on file.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" action="{{ route('admin.employees.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="addEmployeeLabel">Add employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="emp-no" class="form-label small fw-semibold">Employee ID</label>
                            <input type="text" class="form-control" id="emp-no" name="employee_no" required
                                   value="{{ old('employee_no') }}" placeholder="e.g. 558675">
                            <div class="form-text">Staff use this ID to clock in at the DTR terminal.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="emp-first" class="form-label small fw-semibold">First name</label>
                            <input type="text" class="form-control" id="emp-first" name="first_name" required
                                   value="{{ old('first_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="emp-last" class="form-label small fw-semibold">Last name</label>
                            <input type="text" class="form-control" id="emp-last" name="last_name" required
                                   value="{{ old('last_name') }}">
                        </div>
                        <div class="col-12">
                            <label for="emp-middle" class="form-label small fw-semibold">
                                Middle name <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="text" class="form-control" id="emp-middle" name="middle_name"
                                   value="{{ old('middle_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label for="emp-position" class="form-label small fw-semibold">Position</label>
                            <input type="text" class="form-control" id="emp-position" name="position"
                                   value="{{ old('position') }}" placeholder="e.g. Range Marshall">
                        </div>
                        <div class="col-md-6">
                            <label for="emp-contact" class="form-label small fw-semibold">Contact no.</label>
                            <input type="text" class="form-control" id="emp-contact" name="contact_no"
                                   value="{{ old('contact_no') }}" placeholder="e.g. 09171234567">
                        </div>
                        <div class="col-md-6">
                            <label for="emp-hired" class="form-label small fw-semibold">Date hired</label>
                            <input type="date" class="form-control" id="emp-hired" name="date_hired"
                                   value="{{ old('date_hired') }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add employee</button>
                </div>
            </form>
        </div>
    </div>
@endsection
