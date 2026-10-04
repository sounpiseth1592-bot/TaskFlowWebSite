<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.email' => 'sounpisethadmin@gmail.com']);
    }

    public function test_admin_login_redirects_to_the_admin_dashboard(): void
    {
        User::factory()->create([
            'name' => 'TaskFlow Admin',
            'email' => 'sounpisethadmin@gmail.com',
            'password' => 'a-strong-admin-password',
        ]);

        $this->from('/login')->post('/auth/login', [
            'email' => 'sounpisethadmin@gmail.com',
            'password' => 'a-strong-admin-password',
        ])->assertRedirect(route('admin.index'));

        $this->assertAuthenticated();
    }

    public function test_server_setup_command_creates_the_configured_admin_with_a_hashed_password(): void
    {
        $this->artisan('app:setup-admin-account')
            ->expectsQuestion('Administrator name', 'TaskFlow Admin')
            ->expectsQuestion('Administrator password (minimum 12 characters)', 'a-strong-admin-password')
            ->expectsQuestion('Confirm administrator password', 'a-strong-admin-password')
            ->assertExitCode(0);

        $admin = User::where('email', 'sounpisethadmin@gmail.com')->firstOrFail();
        $this->assertTrue(Hash::check('a-strong-admin-password', $admin->password));
        $this->assertTrue($admin->can('access-admin'));
    }

    public function test_server_setup_command_rejects_a_weak_admin_password(): void
    {
        $this->artisan('app:setup-admin-account')
            ->expectsQuestion('Administrator name', 'TaskFlow Admin')
            ->expectsQuestion('Administrator password (minimum 12 characters)', '123456')
            ->expectsQuestion('Confirm administrator password', '123456')
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_only_the_configured_admin_can_open_the_admin_dashboard(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create();

        $this->get(route('admin.index'))->assertRedirect('/login');

        $this->actingAs($user)->get(route('admin.index'))->assertForbidden();

        $this->actingAs($admin)->get(route('admin.index'))
            ->assertSee('Admin dashboard')
            ->assertSee('User management')
            ->assertSee('Project management')
            ->assertSee('Task management')
            ->assertSee('Generate token');
    }

    public function test_public_admin_profile_endpoint_returns_only_name_and_avatar_without_a_token(): void
    {
        User::factory()->create([
            'name' => 'TaskFlow Admin',
            'email' => 'sounpisethadmin@gmail.com',
            'description' => 'Private admin bio',
            'avatar_preset' => 'people4',
        ]);

        $this->getJson('/api/admins')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'name' => 'TaskFlow Admin',
                    'avatar_url' => asset('images/icons/people4.png'),
                ],
            ]);
    }

    public function test_public_admin_profile_endpoint_returns_not_found_until_admin_is_configured(): void
    {
        $this->getJson('/api/admins')->assertNotFound();
    }

    public function test_regular_users_cannot_change_accounts_or_generate_admin_tokens(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('admin.users.update', $admin), [
                'name' => 'Compromised',
                'email' => 'sounpisethadmin@gmail.com',
            ])->assertForbidden();

        $this->post(route('admin.users.token', $user))->assertForbidden();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Admin generated token',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => $admin->name,
        ]);
    }

    public function test_public_registration_cannot_claim_the_reserved_admin_email(): void
    {
        $this->from('/register')->post('/auth/register', [
            'name' => 'Impersonator',
            'email' => 'SounPisethAdmin@gmail.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertRedirect('/register')
            ->assertSessionHasErrors('email');

        $this->postJson('/api/register', [
            'name' => 'API Impersonator',
            'email' => 'SounPisethAdmin@gmail.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_admin_can_edit_and_delete_users_projects_and_tasks(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Old project']);
        $task = $project->tasks()->create(['title' => 'Old task']);

        $this->actingAs($admin)->patch(route('admin.users.update', $user), [
            'name' => 'Updated name',
            'email' => 'updated@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated name',
            'email' => 'updated@example.com',
        ]);

        $this->patch(route('admin.projects.update', $project), [
            'name' => 'Updated project',
            'color' => '#123456',
            'user_id' => $admin->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated project',
            'color' => '#123456',
            'user_id' => $admin->id,
        ]);

        $this->patch(route('admin.tasks.update', $task), [
            'project_id' => $project->id,
            'title' => 'Updated task',
            'notes' => 'Updated details',
            'due_date' => '2026-10-05 10:30',
            'priority' => 'high',
            'done' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Updated task',
            'notes' => 'Updated details',
            'priority' => 'high',
            'done' => true,
        ]);

        $this->delete(route('admin.tasks.destroy', $task))->assertRedirect();
        $this->delete(route('admin.projects.destroy', $project))->assertRedirect();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);

        $user = User::factory()->create();
        $ownedProject = $user->projects()->create(['name' => 'Delete with user']);
        $ownedTask = $ownedProject->tasks()->create(['title' => 'Cascade delete']);

        $this->delete(route('admin.users.destroy', $user))->assertRedirect();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('projects', ['id' => $ownedProject->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $ownedTask->id]);
    }

    public function test_admin_cannot_change_or_delete_the_configured_admin_account(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);

        $this->actingAs($admin)->patch(route('admin.users.update', $admin), [
            'name' => 'Changed admin',
            'email' => 'changed@example.com',
        ])->assertSessionHasErrors('email');

        $this->delete(route('admin.users.destroy', $admin))->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'email' => 'sounpisethadmin@gmail.com',
            'name' => $admin->name,
        ]);
    }

    public function test_admin_can_generate_a_one_time_scoped_token_for_a_user(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.users.token', $user))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Copy it now')
            ->assertSee($user->name);
        $plainTextToken = trim($response->viewData('apiToken'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Admin generated token',
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($plainTextToken)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_admin_api_returns_dashboard_data_and_avatar_urls_for_each_user(): void
    {
        $admin = User::factory()->create([
            'email' => 'sounpisethadmin@gmail.com',
            'avatar_preset' => 'people2',
        ]);
        $user = User::factory()->create([
            'avatar_preset' => 'people13',
            'description' => 'A user bio.',
        ]);
        $project = $user->projects()->create(['name' => 'API project']);
        $project->tasks()->create(['title' => 'API task']);
        $token = $admin->createToken('Admin API')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/admin');

        $response->assertOk()
            ->assertJsonPath('data.stats.users', 2)
            ->assertJsonPath('data.stats.projects', 1)
            ->assertJsonPath('data.stats.tasks', 1)
            ->assertJsonFragment([
                'id' => $user->id,
                'description' => 'A user bio.',
                'avatar_action' => 'preset',
                'avatar_preset' => 'people13',
                'avatar_url' => asset('images/icons/people13.png'),
            ])
            ->assertJsonFragment([
                'id' => $user->id,
                'email' => $user->email,
                'avatar_url' => asset('images/icons/people13.png'),
            ])
            ->assertJsonMissingPath('data.users.0.password');

        $this->get(route('admin.index'))
            ->assertSee('src="'.asset('images/icons/people13.png').'"', false)
            ->assertSee('src="'.asset('images/icons/people2.png').'"', false);
    }

    public function test_admin_api_requires_an_admin_sanctum_token(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create();

        $this->getJson('/api/admin')->assertUnauthorized();

        $userToken = $user->createToken('Regular user')->plainTextToken;
        $this->withToken($userToken)->getJson('/api/admin')->assertForbidden();

        $this->app['auth']->forgetGuards();
        $adminToken = $admin->createToken('Admin API')->plainTextToken;
        $this->withToken($adminToken)->getJson('/api/admin')->assertOk();
    }

    public function test_admin_api_can_edit_and_delete_users_projects_and_tasks(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Initial project']);
        $task = $project->tasks()->create(['title' => 'Initial task']);
        $token = $admin->createToken('Admin API')->plainTextToken;

        $this->withToken($token)->patchJson("/api/admin/users/{$user->id}", [
            'name' => 'Admin API edited',
            'email' => 'edited@example.com',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Admin API edited');

        $this->patchJson("/api/admin/projects/{$project->id}", [
            'name' => 'Updated project',
            'color' => '#123456',
            'user_id' => $admin->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated project')
            ->assertJsonPath('data.user.id', $admin->id);

        $this->patchJson("/api/admin/tasks/{$task->id}", [
            'project_id' => $project->id,
            'title' => 'Updated task',
            'priority' => 'high',
            'done' => true,
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated task')
            ->assertJsonPath('data.done', true);

        $this->deleteJson("/api/admin/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Task deleted.');
        $this->deleteJson("/api/admin/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Project and its tasks were deleted.');
        $this->deleteJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'User account and its projects were deleted.');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_admin_api_does_not_allow_claiming_the_reserved_admin_email(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create();
        $token = $admin->createToken('Admin API')->plainTextToken;

        $this->withToken($token)->patchJson("/api/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => 'SounPisethAdmin@gmail.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function test_admin_api_can_generate_a_one_time_token_for_a_user(): void
    {
        $admin = User::factory()->create(['email' => 'sounpisethadmin@gmail.com']);
        $user = User::factory()->create(['avatar_preset' => 'people4']);
        $token = $admin->createToken('Admin API')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson("/api/admin/users/{$user->id}/token")
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.avatar_url', asset('images/icons/people4.png'));

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Admin generated token',
        ]);
    }
}
