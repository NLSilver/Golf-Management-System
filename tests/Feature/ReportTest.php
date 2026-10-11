<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Bay;
use App\Models\BaySession;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_monthly_report_is_admin_only(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin/reports')->assertForbidden();
        $this->actingAs($staff)->get('/admin/reports/export')->assertForbidden();
    }

    public function test_admin_can_view_the_monthly_report_with_totals(): void
    {
        $admin = $this->admin();
        $month = now()->format('Y-m');

        $transaction = Transaction::query()->create([
            'user_id' => $admin->id,
            'subtotal' => 450,
            'total' => 450,
            'payment_method' => 'cash',
            'status' => Transaction::STATUS_PAID,
            'amount_tendered' => 500,
            'change_due' => 50,
            'paid_at' => now(),
        ]);

        $transaction->items()->create([
            'item_type' => 'bay_time',
            'description' => 'B1 bay time (90 min)',
            'quantity' => 1,
            'unit_price' => 450,
            'amount' => 450,
        ]);

        // A completed session exercises the bay-utilization aggregation.
        $bay = Bay::query()->create(['code' => 'B1', 'bay_number' => 1]);
        $customer = Customer::query()->create(['first_name' => 'Walk', 'last_name' => 'In']);
        $session = BaySession::query()->create([
            'bay_id' => $bay->id,
            'customer_id' => $customer->id,
            'started_at' => now()->subHour(),
            'planned_minutes' => 90,
            'ends_at' => now()->subMinutes(30),
            'status' => BaySession::STATUS_COMPLETED,
        ]);
        $session->extensions()->create(['minutes' => 20]);

        $response = $this->actingAs($admin)->get('/admin/reports?month='.$month);

        $response->assertOk()
            ->assertSee('Monthly Report', false)
            ->assertSee('1. Operational - Bay Utilization', false)
            ->assertSee('2. Transactional - Payment Breakdown', false)
            ->assertSee('3. Attendance - Employee DTR Summary', false)
            ->assertSee('450.00', false);

        // 90 planned + 20 extended = 110 minutes, shown as 1.8 hours (a bare 90 would render 1.5).
        $response->assertSee('>1.8<', false)->assertSee('1 / 1.8h', false);
    }

    public function test_transaction_csv_export_downloads_for_the_selected_month(): void
    {
        $admin = $this->admin();
        $month = now()->format('Y-m');

        Transaction::query()->create([
            'user_id' => $admin->id,
            'subtotal' => 700,
            'total' => 700,
            'payment_method' => 'cash',
            'status' => Transaction::STATUS_PAID,
            'amount_tendered' => 700,
            'change_due' => 0,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($admin)
            ->get("/admin/reports/export?type=transactions&month={$month}");

        $response->assertOk()->assertDownload("degms-transactions-{$month}.csv");

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Receipt No', $csv);
        $this->assertStringContainsString('RC-000001', $csv);
        $this->assertStringContainsString('700', $csv);
    }

    public function test_attendance_csv_export_contains_the_dtr_rows(): void
    {
        $admin = $this->admin();
        $month = now()->format('Y-m');

        $employee = Employee::query()->create([
            'employee_no' => '189324',
            'first_name' => 'Jerred',
            'last_name' => 'Santos',
        ]);

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => now()->toDateString(),
            'clock_in' => now()->subHours(4),
            'clock_out' => now(),
        ])->clockOut()->save();

        $response = $this->actingAs($admin)
            ->get("/admin/reports/export?type=attendance&month={$month}");

        $response->assertOk()->assertDownload("degms-dtr-{$month}.csv");

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Employee ID', $csv);
        $this->assertStringContainsString('189324', $csv);
        $this->assertStringContainsString('Minutes Worked', $csv);
    }
}
