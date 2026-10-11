<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /** Digital Daily Time Record screen. */
    public function show()
    {
        $today = now()->toDateString();

        return view('dtr', [
            'today' => $today,
            'records' => AttendanceRecord::query()
                ->with('employee')
                ->where('date', $today)
                ->orderBy('clock_in')
                ->get(),
            'openRecords' => AttendanceRecord::query()
                ->with('employee')
                ->whereNull('clock_out')
                ->where('date', $today)
                ->pluck('employee_id'),
        ]);
    }

    public function clockIn(Request $request)
    {
        $employee = $this->resolveEmployee($request);

        if ($employee === null) {
            return back()->withErrors(['employee_no' => 'No active employee matches that Employee ID.']);
        }

        $open = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->exists();

        if ($open) {
            return back()->withErrors(['employee_no' => "{$employee->first_name} {$employee->last_name} is already clocked in."]);
        }

        $record = AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'clock_in' => now(),
            'recorded_by' => auth()->id(),
        ]);

        ActivityLog::record('dtr.clock-in', "{$employee->employee_no} {$employee->getFullNameAttribute()} clocked in", $record);

        return back()->with('success', "{$employee->getFullNameAttribute()} clocked in at {$record->clock_in->format('h:i:s a')}.");
    }

    public function clockOut(Request $request)
    {
        $employee = $this->resolveEmployee($request);

        if ($employee === null) {
            return back()->withErrors(['employee_no' => 'No active employee matches that Employee ID.']);
        }

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if (! $record) {
            return back()->withErrors(['employee_no' => "{$employee->first_name} {$employee->last_name} has no open time-in for today."]);
        }

        $record->clockOut()->save();

        ActivityLog::record('dtr.clock-out', "{$employee->employee_no} {$employee->getFullNameAttribute()} clocked out after {$record->minutes_worked} min", $record);

        return back()->with('success', "{$employee->getFullNameAttribute()} clocked out - {$record->minutes_worked} minutes worked.");
    }

    private function resolveEmployee(Request $request): ?Employee
    {
        $data = $request->validate([
            'employee_no' => ['required', 'string', 'max:50'],
        ]);

        return Employee::query()
            ->where('employee_no', trim($data['employee_no']))
            ->where('is_active', true)
            ->first();
    }
}
