<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['558675', 'Andrian Christian', null, 'Hernadez', 'Front Desk Staff', '09668263945'],
            ['835242', 'Charlie', null, 'Menedez', 'Range Staff', '09171234567'],
            ['189324', 'Jerred', null, 'Santos', 'Golf Instructor', '09189876543'],
        ];

        foreach ($employees as [$no, $first, $middle, $last, $position, $contact]) {
            Employee::query()->updateOrCreate(
                ['employee_no' => $no],
                [
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'last_name' => $last,
                    'position' => $position,
                    'contact_no' => $contact,
                    'date_hired' => '2024-01-15',
                    'is_active' => true,
                ],
            );
        }
    }
}
