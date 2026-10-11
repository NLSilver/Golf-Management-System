<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Paper-style DTR history: every employee worked the last 10 days,
 * plus today's live picture (one on shift, one finished, one day off).
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        if (AttendanceRecord::query()->exists()) {
            return;
        }

        $employees = Employee::query()->orderBy('id')->get();
        $frontdesk = User::query()->where('username', 'frontdesk')->first();

        // Past 10 full days: 8:0x in, 17:0x out.
        foreach (range(1, 10) as $offset) {
            $day = now()->subDays($offset);

            foreach ($employees as $i => $employee) {
                $in = $day->copy()->setTime(8, 2 + ($i * 4))->startOfMinute();
                $out = $day->copy()->setTime(17, 5 + ($i * 3))->startOfMinute();

                AttendanceRecord::query()->create([
                    'employee_id' => $employee->id,
                    'date' => $in->toDateString(),
                    'clock_in' => $in,
                    'clock_out' => $out,
                    'minutes_worked' => (int) $in->diffInMinutes($out),
                    'recorded_by' => $frontdesk?->id,
                ]);
            }
        }

        // Today: first employee is still on shift.
        [$andrian, $charlie, $jerred] = $employees->values();

        AttendanceRecord::query()->create([
            'employee_id' => $andrian->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->copy()->setTime(8, 5),
            'clock_out' => null,
            'minutes_worked' => null,
            'recorded_by' => $frontdesk?->id,
        ]);

        // Second employee clocked in this morning and out for lunch-that-never-ended.
        $charlieIn = now()->copy()->setTime(8, 10);
        $charlieOut = now()->copy()->subMinutes(25);

        AttendanceRecord::query()->create([
            'employee_id' => $charlie->id,
            'date' => $charlieIn->toDateString(),
            'clock_in' => $charlieIn,
            'clock_out' => $charlieOut->greaterThan($charlieIn->copy()->addHour()) ? $charlieOut : null,
            'minutes_worked' => $charlieOut->greaterThan($charlieIn->copy()->addHour())
                ? (int) $charlieIn->diffInMinutes($charlieOut)
                : null,
            'recorded_by' => $frontdesk?->id,
        ]);

        // Third employee has no record today (day off).
        unset($jerred);
    }
}
