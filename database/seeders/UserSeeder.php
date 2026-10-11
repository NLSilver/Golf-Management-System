<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts (password for all: password)
 *   admin      -> admin   (full access, reports)
 *   frontdesk  -> staff   (bay allocation, time logging, checkout)
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['admin', 'System Administrator', User::ROLE_ADMIN, '558675'],
            ['frontdesk', 'Front Desk Staff', User::ROLE_STAFF, '835242'],
        ];

        foreach ($accounts as [$username, $name, $role, $employeeNo]) {
            $employee = Employee::query()->where('employee_no', $employeeNo)->first();

            User::query()->updateOrCreate(
                ['username' => $username],
                [
                    'name' => $name,
                    'email' => $username.'@degms.local',
                    'password' => 'password',
                    'role' => $role,
                    'employee_id' => $employee?->id,
                ],
            );
        }
    }
}
