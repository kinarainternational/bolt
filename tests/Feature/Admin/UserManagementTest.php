<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_user_management()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $response = $this->actingAs($user)->get(route('admin.users.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_view_user_list()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $users = User::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/Users/Index')
            ->has('users', 4) // 3 users + admin
        );
    }

    public function test_admin_can_view_create_user_form()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get(route('admin.users.create'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('admin/Users/Create'));
    }

    public function test_admin_can_create_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => false,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'is_admin' => false,
        ]);
    }

    public function test_admin_can_create_another_admin()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_admin' => true,
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'email' => 'newadmin@example.com',
            'is_admin' => true,
        ]);
    }

    public function test_admin_can_view_edit_user_form()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $user));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('admin/Users/Edit')
            ->where('user.id', $user->id)
        );
    }

    public function test_admin_can_update_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'New Name',
            'email' => $user->email,
            'is_admin' => false,
        ]);

        $response->assertRedirect(route('admin.users.edit', $user));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
        ]);
    }

    public function test_admin_can_update_user_password()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $oldPasswordHash = $user->password;

        $response = $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('admin.users.edit', $user));
        $this->assertNotEquals($oldPasswordHash, $user->fresh()->password);
    }

    public function test_admin_can_deactivate_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_deactivate_themselves()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->delete(route('admin.users.destroy', $admin));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_restore_deactivated_user()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $user->delete();

        $response = $this->actingAs($admin)->post(route('admin.users.restore', $user->id));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_reset_user_two_factor()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create([
            'two_factor_secret' => 'secret',
            'two_factor_recovery_codes' => 'codes',
            'two_factor_confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.reset-2fa', $user));

        $response->assertRedirect(route('admin.users.edit', $user));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);
    }

    public function test_user_list_includes_deactivated_users()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $activeUser = User::factory()->create();
        $deactivatedUser = User::factory()->create();
        $deactivatedUser->delete();

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('users', 3) // admin + active + deactivated
        );
    }

    public function test_create_user_validates_required_fields()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_create_user_validates_unique_email()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $existingUser = User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}
