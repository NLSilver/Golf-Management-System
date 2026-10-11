<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\MaintenanceLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Evening ball collection + closing sweep history for the ops log. */
class MaintenanceLogSeeder extends Seeder
{
    public function run(): void
    {
        if (MaintenanceLog::query()->exists()) {
            return;
        }

        $staff = User::query()->where('username', 'frontdesk')->first();
        $rangeStaff = Employee::query()->where('employee_no', '835242')->first()
            ?? Employee::query()->first();

        foreach (range(1, 7) as $offset) {
            $day = now()->subDays($offset);

            MaintenanceLog::query()->create([
                'type' => MaintenanceLog::TYPE_BALL_COLLECTION,
                'employee_id' => $rangeStaff?->id,
                'started_at' => $day->copy()->setTime(18, 0),
                'finished_at' => $day->copy()->setTime(19, 15),
                'notes' => 'Collected and sorted balls from all active bays.',
                'user_id' => $staff?->id,
            ]);
        }

        MaintenanceLog::query()->create([
            'type' => MaintenanceLog::TYPE_CLOSING_SWEEP,
            'employee_id' => $rangeStaff?->id,
            'started_at' => now()->subDay()->setTime(21, 30),
            'finished_at' => now()->subDay()->setTime(22, 0),
            'notes' => 'End-of-day cleanup; range closed on time.',
            'user_id' => $staff?->id,
        ]);
    }
}
