<?php

namespace Tests\Feature;

use App\Models\Bay;
use App\Models\BaySession;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoFinishTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::query()->firstOrCreate(
            ['username' => 'frontdesk'],
            ['role' => 'staff', 'name' => 'Front Desk', 'password' => 'password'],
        );
    }

    private function bay(): Bay
    {
        return Bay::query()->firstOrCreate(
            ['code' => 'B1'],
            ['bay_number' => 1, 'label' => 'Hitting Bay 1'],
        );
    }

    private function bayService(): Service
    {
        return Service::query()->firstOrCreate(
            ['code' => 'BAY-HR'],
            [
                'name' => 'Hitting Bay (per hour)',
                'category' => Service::CATEGORY_BAY_TIME,
                'unit' => 'hour',
                'rate' => 300,
                'minimum_minutes' => 90,
            ],
        );
    }

    /** Start a session that ended $minutesAgo minutes ago. */
    private function staleSession(int $minutesAgo): BaySession
    {
        $customer = Customer::query()->create(['first_name' => 'Walk', 'last_name' => 'In']);
        $bay = $this->bay();

        $session = BaySession::query()->create([
            'session_no' => sprintf('SES-%06d', BaySession::query()->max('id') + 1),
            'bay_id' => $bay->id,
            'customer_id' => $customer->id,
            'service_id' => $this->bayService()->id,
            'user_id' => $this->staff()->id,
            'started_at' => now()->subMinutes(90 + $minutesAgo),
            'planned_minutes' => 90,
            'ends_at' => now()->subMinutes($minutesAgo),
            'status' => BaySession::STATUS_ACTIVE,
        ]);

        Setting::setValue('overstay_grace_minutes', '5', 'bay');

        return $session;
    }

    public function test_dashboard_load_auto_finishes_expired_sessions_into_unpaid_receipts(): void
    {
        $session = $this->staleSession(minutesAgo: 10); // past 5 min grace

        $this->actingAs($this->staff())->get('/dashboard')->assertOk();

        $session->refresh();
        $this->assertSame(BaySession::STATUS_COMPLETED, $session->status);
        $this->assertNotNull($session->actual_end_at);

        $transaction = $session->transaction()->firstOrFail();
        $this->assertSame(Transaction::STATUS_UNPAID, $transaction->status);
        $this->assertNull($transaction->paid_at);

        // 90 min @ 300/hr, floored at the 90 minute minimum.
        $this->assertEquals(450.0, (float) $transaction->total);

        // The freed bay shows up as an unsettled receipt on the dashboard.
        $response = $this->actingAs($this->staff())->get('/dashboard');
        $response->assertOk()
            ->assertSee('Unsettled receipts', false)
            ->assertSee($transaction->receipt_no, false);
    }

    public function test_sessions_within_the_grace_period_stay_active(): void
    {
        $session = $this->staleSession(minutesAgo: 2); // inside 5 min grace

        $this->actingAs($this->staff())->get('/dashboard')->assertOk();

        $this->assertTrue($session->fresh()->isActive());
        $this->assertNull($session->transaction()->first());
    }

    public function test_status_poll_closes_expired_sessions_and_frees_the_bay(): void
    {
        $session = $this->staleSession(minutesAgo: 30);

        $this->actingAs($this->staff())->get('/dashboard/status')
            ->assertOk()
            ->assertJsonPath('bays.0.session_id', null);

        $this->assertSame(BaySession::STATUS_COMPLETED, $session->fresh()->status);
        $this->assertSame(Transaction::STATUS_UNPAID, $session->transaction()->firstOrFail()->status);
    }

    public function test_the_sweep_is_idempotent_across_repeated_polls(): void
    {
        $session = $this->staleSession(minutesAgo: 30);

        $staff = $this->staff();
        $this->actingAs($staff)->get('/dashboard/status')->assertOk();
        $this->actingAs($staff)->get('/dashboard/status')->assertOk();
        $this->actingAs($staff)->get('/dashboard')->assertOk();

        $this->assertSame(1, Transaction::query()->where('bay_session_id', $session->id)->count());
    }

    public function test_an_auto_closed_receipt_can_be_settled_from_the_dashboard(): void
    {
        $session = $this->staleSession(minutesAgo: 30);
        $staff = $this->staff();

        $this->actingAs($staff)->get('/dashboard')->assertOk();

        $transaction = $session->transaction()->firstOrFail();

        $this->actingAs($staff)->post("/receipts/{$transaction->id}/settle", [
            'payment_method' => 'cash',
            'amount_tendered' => 500,
        ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

        $transaction->refresh();
        $this->assertSame(Transaction::STATUS_PAID, $transaction->status);
        $this->assertNotNull($transaction->paid_at);
        $this->assertEquals(450.0, (float) $transaction->total);
        $this->assertEquals(50.0, (float) $transaction->change_due);
        $this->assertSame($staff->id, $transaction->user_id);

        // Second attempt must be rejected - it is already settled.
        $this->actingAs($staff)->post("/receipts/{$transaction->id}/settle", [
            'payment_method' => 'cash',
            'amount_tendered' => 500,
        ])->assertSessionHasErrors('transaction');
    }

    public function test_settle_rejects_an_underpayment(): void
    {
        $session = $this->staleSession(minutesAgo: 30);
        $staff = $this->staff();

        $this->actingAs($staff)->get('/dashboard')->assertOk();
        $transaction = $session->transaction()->firstOrFail();

        $this->actingAs($staff)->post("/receipts/{$transaction->id}/settle", [
            'payment_method' => 'cash',
            'amount_tendered' => 100,
        ])->assertSessionHasErrors('amount_tendered');

        $this->assertSame(Transaction::STATUS_UNPAID, $transaction->fresh()->status);
    }

    public function test_receipt_back_button_never_targets_the_status_endpoint(): void
    {
        $session = $this->staleSession(minutesAgo: 30);
        $staff = $this->staff();

        $this->actingAs($staff)->get('/dashboard')->assertOk();
        $transaction = $session->transaction()->firstOrFail();

        // Simulate the poller having clobbered the session's previous URL.
        $this->actingAs($staff)->withSession(['_previous.url' => 'http://localhost/dashboard/status'])
            ->get("/receipts/{$transaction->id}")
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('/dashboard/status', false);
    }

    public function test_receipts_show_an_unpaid_stamp_until_settled(): void
    {
        $session = $this->staleSession(minutesAgo: 30);
        $staff = $this->staff();

        $this->actingAs($staff)->get('/dashboard')->assertOk();
        $transaction = $session->transaction()->firstOrFail();

        $this->actingAs($staff)->get("/receipts/{$transaction->id}")
            ->assertOk()
            ->assertSee('UNPAID', false)
            ->assertDontSee('>PAID<', false);

        $this->actingAs($staff)->post("/receipts/{$transaction->id}/settle", [
            'payment_method' => 'cash',
            'amount_tendered' => 450,
        ])->assertSessionHasNoErrors();

        $this->actingAs($staff)->get("/receipts/{$transaction->id}")
            ->assertOk()
            ->assertSee('>PAID<', false);
    }
}
