<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_staff_cannot_manage_login_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->actingAs($staff)->post('/admin/users', [
            'name' => 'Intruder',
            'username' => 'intruder',
            'role' => 'admin',
            'password' => 'secret123',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'intruder']);
    }

    public function test_admin_can_create_a_login_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/users', [
                'name' => 'Maria Santos',
                'username' => 'maria.s',
                'role' => 'staff',
                'password' => 'secret123',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/users');

        $user = User::query()->where('username', 'maria.s')->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertTrue(Hash::check('secret123', $user->password));

        // The page lists the new account.
        $this->actingAs($admin)->get('/admin/users')->assertOk()->assertSee('maria.s');
    }

    public function test_duplicate_or_invalid_usernames_are_rejected(): void
    {
        $admin = $this->admin();
        User::factory()->create(['username' => 'taken.name']);

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Dup', 'username' => 'taken.name', 'role' => 'staff', 'password' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Spaces', 'username' => 'has space', 'role' => 'staff', 'password' => 'secret123',
        ])->assertSessionHasErrors('username');

        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Short', 'username' => 'shorty', 'role' => 'staff', 'password' => '123',
        ])->assertSessionHasErrors('password');
    }

    public function test_admin_can_reset_a_password(): void
    {
        $admin = $this->admin();
        $target = User::factory()->create(['role' => 'staff', 'password' => 'oldpass123']);

        $this->actingAs($admin)
            ->post("/admin/users/{$target->id}/password", ['password' => 'newpass456'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newpass456', $target->fresh()->password));
        $this->assertFalse(Hash::check('oldpass123', $target->fresh()->password));
    }

    public function test_admin_can_change_roles_but_not_their_own(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'staff']);

        // Promote someone else.
        $this->actingAs($admin)
            ->post("/admin/users/{$other->id}/role", ['role' => 'admin'])
            ->assertSessionHasNoErrors();
        $this->assertSame('admin', $other->fresh()->role);

        // Demote them again.
        $this->actingAs($admin)
            ->post("/admin/users/{$other->id}/role", ['role' => 'staff'])
            ->assertSessionHasNoErrors();
        $this->assertSame('staff', $other->fresh()->role);

        // Cannot change own role (lock-out guard).
        $this->actingAs($admin)
            ->post("/admin/users/{$admin->id}/role", ['role' => 'staff'])
            ->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }
}
