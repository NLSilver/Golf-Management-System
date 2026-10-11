<?php

namespace Tests\Feature;

use App\Models\Bay;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptExtrasTest extends TestCase
{
    use RefreshDatabase;

    private function seedRateCard(): void
    {
        Setting::setValue('minimum_session_minutes', '90', 'bay');
        Setting::setValue('receipt_footer', 'Thank you! Play again soon.', 'receipt');
        Setting::setValue('facility_name', 'Davao EagleMasters Golf Driving Range', 'general');

        Service::query()->create([
            'code' => 'BAY-HR',
            'name' => 'Bay Time',
            'category' => Service::CATEGORY_BAY_TIME,
            'rate' => 300,
            'is_active' => true,
        ]);
    }

    private function bay(string $code): Bay
    {
        return Bay::query()->firstOrCreate(
            ['code' => $code],
            ['bay_number' => (int) substr($code, 1), 'label' => "Hitting Bay ".substr($code, 1), 'is_out_of_order' => false],
        );
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_printable_receipt_renders_for_authenticated_users_and_hides_from_guests(): void
    {
        $this->seedRateCard();
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => 'staff']);

        // Guest probe: an unknown receipt must bounce to login.
        $this->get('/receipts/1')->assertRedirect('/login');

        // Run a full allocate -> checkout flow to produce a real transaction.
        $bay = $this->bay('B1');

        $this->actingAs($staff)->post("/bays/{$bay->id}/allocate", [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'minutes' => 90,
        ])->assertSessionHasNoErrors();

        $session = $bay->activeSession()->firstOrFail();

        $this->actingAs($staff)->post("/bay-sessions/{$session->id}/checkout", [
            'payment_method' => 'cash',
            'amount_tendered' => 1000,
            'discount' => 0,
        ])->assertSessionHasNoErrors();

        $transaction = $session->transaction()->firstOrFail();

        $this->actingAs($staff)->get("/receipts/{$transaction->id}")
            ->assertOk()
            ->assertSee('Official Receipt', false)
            ->assertSee($transaction->receipt_no, false)
            ->assertSee('window.print()', false);

        $this->actingAs($admin)->get("/receipts/{$transaction->id}")->assertOk();
    }

    public function test_staff_are_forbidden_from_activity_log_and_settings(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $bay = $this->bay('B7');

        $this->actingAs($staff)->get('/admin/activity')->assertForbidden();
        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
        $this->actingAs($staff)->post("/admin/bays/{$bay->id}/maintenance")->assertForbidden();
    }

    public function test_admin_can_view_and_filter_the_activity_log(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/activity')
            ->assertOk()
            ->assertSee('Activity Log');

        $this->actingAs($admin)->get('/admin/activity?action=login')
            ->assertOk()
            ->assertSee('login', false);
    }

    public function test_admin_can_toggle_bay_maintenance(): void
    {
        $admin = $this->admin();
        $bay = $this->bay('B7');
        $this->assertFalse($bay->is_out_of_order);

        $this->actingAs($admin)->post("/admin/bays/{$bay->id}/maintenance")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($bay->fresh()->is_out_of_order);

        $this->actingAs($admin)->post("/admin/bays/{$bay->id}/maintenance")->assertRedirect();
        $this->assertFalse($bay->fresh()->is_out_of_order);
    }

    public function test_admin_can_save_facility_settings(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('Facility Settings');

        $this->actingAs($admin)->post('/admin/settings', [
            'facility_name' => 'Davao EagleMasters Golf Driving Range',
            'facility_address' => 'Riverfront Drive, Davao City',
            'operating_open' => '06:00',
            'operating_close' => '22:00',
            'minimum_session_minutes' => 90,
            'expiring_soon_minutes' => 10,
            'overstay_grace_minutes' => 5,
            'receipt_footer' => 'Play again soon!',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Play again soon!', Setting::getValue('receipt_footer'));
        $this->assertSame('Davao EagleMasters Golf Driving Range', Setting::getValue('facility_name'));
        $this->assertSame('5', Setting::getValue('overstay_grace_minutes'));
    }

    public function test_every_admin_page_links_back_to_the_admin_dashboard(): void
    {
        $admin = $this->admin();

        $pages = [
            '/admin/transactions',
            '/admin/reports',
            '/admin/employees',
            '/admin/customers',
            '/admin/users',
            '/admin/activity',
            '/admin/settings',
        ];

        foreach ($pages as $uri) {
            $this->actingAs($admin)->get($uri)
                ->assertOk()
                ->assertSee('Back to Admin', false);
        }
    }

    public function test_reset_password_modal_preloads_the_account_name_and_username(): void
    {
        $admin = $this->admin();
        User::factory()->create([
            'role' => 'staff',
            'username' => 'maria.s',
            'name' => 'Maria Santos',
        ]);

        $user = User::query()->where('username', 'maria.s')->firstOrFail();

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Reset password for Maria Santos (maria.s)', false)
            ->assertSee('action="'.url('/admin/users/'.$user->id.'/password').'"', false);
    }
}
