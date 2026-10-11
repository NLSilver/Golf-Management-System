<?php

namespace Tests\Feature;

use App\Models\Bay;
use App\Models\BaySession;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontDeskTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['username' => 'frontdesk', 'role' => 'staff']);
    }

    private function bay(): Bay
    {
        return Bay::query()->create(['code' => 'B1', 'bay_number' => 1, 'label' => 'Hitting Bay 1']);
    }

    private function bayService(): Service
    {
        return Service::query()->create([
            'code' => 'BAY-HR',
            'name' => 'Hitting Bay (per hour)',
            'category' => Service::CATEGORY_BAY_TIME,
            'unit' => 'hour',
            'rate' => 300,
            'minimum_minutes' => 90,
        ]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_front_desk_staff_can_sign_in_and_see_the_dashboard(): void
    {
        $this->staff();
        $this->bay();

        $this->post('/login', [
            'username' => 'frontdesk',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('B1');
    }

    public function test_bad_credentials_are_rejected(): void
    {
        $this->staff();

        $this->from('/login')
            ->post('/login', ['username' => 'frontdesk', 'password' => 'wrong'])
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_allocation_enforces_the_ninety_minute_minimum(): void
    {
        $this->actingAs($this->staff());
        $bay = $this->bay();

        $this->post("/bays/{$bay->id}/allocate", [
            'first_name' => 'Too',
            'last_name' => 'Short',
            'minutes' => 30,
        ])->assertSessionHasErrors('minutes');

        $this->assertSame(0, BaySession::query()->count());
    }

    public function test_a_session_can_be_allocated_extended_and_checked_out(): void
    {
        $this->actingAs($this->staff());
        $bay = $this->bay();
        $this->bayService();

        $this->post("/bays/{$bay->id}/allocate", [
            'first_name' => 'Walk',
            'last_name' => 'In',
            'minutes' => 90,
        ])->assertSessionHasNoErrors();

        $session = BaySession::query()->firstOrFail();

        $this->assertSame(90, $session->planned_minutes);
        $this->assertTrue($session->isActive());

        $this->post("/bay-sessions/{$session->id}/extend", ['minutes' => 20])
            ->assertSessionHasNoErrors();

        $this->assertSame(110, $session->fresh()->totalMinutes());

        $this->post("/bay-sessions/{$session->id}/checkout", [
            'payment_method' => 'cash',
            'amount_tendered' => 1000,
            'discount' => 0,
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $this->assertSame('completed', $session->fresh()->status);

        $transaction = $session->fresh()->transaction;
        $this->assertNotNull($transaction);
        $this->assertSame('paid', $transaction->status);
        $this->assertEquals(550.0, (float) $transaction->total);   // 110 min @ 300/hr
        $this->assertEquals(450.0, (float) $transaction->change_due);

        $this->assertTrue($bay->fresh()->isFree());
    }
}
