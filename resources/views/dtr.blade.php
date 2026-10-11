@extends('layouts.app')

@section('title', 'Employee Attendance')

@section('content')
    @php
        $fmtTime = fn ($t) => $t ? $t->format('h:i:s a') : '&mdash;';
    @endphp

    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h4 class="mb-0 fw-bold">Date Time Record</h4>
            <div class="text-muted small">Employee Attendance &middot; replaces the paper DTR sheet</div>
        </div>
        <div class="text-end">
            <div class="text-muted small">Today is:</div>
            <div class="fw-semibold" id="dtr-clock">{{ now()->format('d/m/Y h:i:s a') }}</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Welcome, Employees</div>
                <div class="card-body">
                    <form action="{{ route('dtr.clock-in') }}" method="POST" class="mb-3">
                        @csrf
                        <label class="form-label">Enter Your Id</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M3 14s-1 0-1-1 1-4 5-4 5 3 5 4-1 1-1 1zm3-9a3 3 0 1 0 0-6 3 3 0 0 0 0 6m5 3a5 5 0 0 1 0 10H5a5 5 0 0 1 0-10"/>
                                </svg>
                            </span>
                            <input type="text" name="employee_no" class="form-control"
                                   placeholder="Employee ID" required autofocus>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-success flex-fill">Clock-in</button>
                        </div>
                    </form>

                    <form action="{{ route('dtr.clock-out') }}" method="POST">
                        @csrf
                        <input type="hidden" name="employee_no" value="">
                        <div class="d-grid">
                            <button type="button" class="btn btn-outline-primary"
                                    data-bs-toggle="collapse" data-bs-target="#clockout-form">
                                Clock-out
                            </button>
                        </div>
                        <div class="collapse mt-2" id="clockout-form">
                            <div class="input-group mb-2">
                                <span class="input-group-text">ID</span>
                                <input type="text" name="employee_no" class="form-control" placeholder="Employee ID" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Confirm clock-out</button>
                        </div>
                    </form>

                    <hr>
                    <div class="small text-muted">
                        Staff without an account can still be clocked in by the front desk using their
                        Employee ID.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-semibold">Attendance - {{ \Illuminate\Support\Carbon::parse($today)->format('d/m/Y') }}</div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>FirstName</th>
                            <th>MiddleName</th>
                            <th>LastName</th>
                            <th>Date</th>
                            <th>Clock-in</th>
                            <th>Clock-out</th>
                            <th class="text-end">Minutes</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ $record->employee->employee_no }}</td>
                                <td>{{ $record->employee->first_name }}</td>
                                <td>{{ $record->employee->middle_name ?? '-' }}</td>
                                <td>{{ $record->employee->last_name }}</td>
                                <td>{{ $record->date->format('d/m/Y') }}</td>
                                <td>{{ $fmtTime($record->clock_in) }}</td>
                                <td>
                                    @if ($record->clock_out)
                                        {{ $fmtTime($record->clock_out) }}
                                    @else
                                        <span class="badge text-bg-success">On shift</span>
                                    @endif
                                </td>
                                <td class="text-end">{{ $record->minutes_worked ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No attendance recorded today.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        const clock = document.getElementById('dtr-clock');
        const pad = (n) => String(n).padStart(2, '0');
        const stamp = () => {
            const d = new Date();
            const months = String(d.getMonth() + 1).padStart(2, '0');
            const suffix = d.getHours() < 12 ? 'am' : 'pm';
            let h = d.getHours() % 12;
            h = h === 0 ? 12 : h;
            return `${pad(d.getDate())}/${months}/${d.getFullYear()} ${h}:${pad(d.getMinutes())}:${pad(d.getSeconds())} ${suffix}`;
        };
        if (clock) {
            setInterval(() => { clock.textContent = stamp(); }, 1000);
        }
    </script>
@endsection
