<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtrAdminTest extends TestCase
{
    use RefreshDatabase;

    private function employee(): Employee
    {
        return Employee::query()->create([
            'employee_no' => '558675',
            'first_name' => 'Andrian',
            'last_name' => 'Hernadez',
            'position' => 'Front Desk Staff',
        ]);
    }

    public function test_employee_can_clock_in_and_out_with_their_employee_id(): void
    {
        $employee = $this->employee();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/dtr')->assertOk()->assertSee('Date Time Record');

        $this->actingAs($staff)
            ->post('/dtr/clock-in', ['employee_no' => $employee->employee_no])
            ->assertSessionHasNoErrors();

        $record = AttendanceRecord::query()->firstOrFail();
        $this->assertNull($record->clock_out);
        $this->assertSame(now()->toDateString(), $record->date->toDateString());

        // Clocking in twice must be rejected while a shift is open.
        $this->actingAs($staff)
            ->post('/dtr/clock-in', ['employee_no' => $employee->employee_no])
            ->assertSessionHasErrors('employee_no');

        $this->actingAs($staff)
            ->post('/dtr/clock-out', ['employee_no' => $employee->employee_no])
            ->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertNotNull($record->clock_out);
        $this->assertNotNull($record->minutes_worked);
    }

    public function test_clocking_out_without_an_open_shift_is_rejected(): void
    {
        $employee = $this->employee();
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post('/dtr/clock-out', ['employee_no' => $employee->employee_no])
            ->assertSessionHasErrors('employee_no');
    }

    public function test_unknown_employee_id_is_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post('/dtr/clock-in', ['employee_no' => '000000'])
            ->assertSessionHasErrors('employee_no');
    }

    public function test_admin_area_is_forbidden_for_front_desk_staff(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_open_the_dashboard_and_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee("Today's Revenue", false);

        $this->actingAs($admin)->get('/admin/transactions')->assertOk();
        $this->actingAs($admin)->get('/admin/employees')->assertOk();
        $this->actingAs($admin)->get('/admin/customers')->assertOk();
    }

    public function test_admin_can_add_a_new_employee(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post('/admin/employees', [
                'employee_no' => '900123',
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'last_name' => 'Clara',
                'position' => 'Range Marshall',
                'contact_no' => '09171234567',
                'date_hired' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/employees');

        $this->assertDatabaseHas('employees', [
            'employee_no' => '900123',
            'first_name' => 'Maria',
            'last_name' => 'Clara',
        ]);

        // The new hire shows up on the employee records page.
        $this->actingAs($admin)->get('/admin/employees')
            ->assertOk()
            ->assertSee('Maria Santos Clara');

        // Duplicate employee IDs are rejected.
        $this->actingAs($admin)
            ->post('/admin/employees', ['employee_no' => '900123', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz'])
            ->assertSessionHasErrors('employee_no');

        // Required fields are enforced.
        $this->actingAs($admin)
            ->post('/admin/employees', ['employee_no' => '900456'])
            ->assertSessionHasErrors(['first_name', 'last_name']);
    }

    public function test_front_desk_cannot_add_employees(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->post('/admin/employees', ['employee_no' => '900999', 'first_name' => 'Nope', 'last_name' => 'Denied'])
            ->assertForbidden();

        $this->assertDatabaseMissing('employees', ['employee_no' => '900999']);
    }
}
