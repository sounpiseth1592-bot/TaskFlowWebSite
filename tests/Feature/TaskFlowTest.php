<?php

namespace Tests\Feature;

use App\Mail\GoogleLoginNotificationMail;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class TaskFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_is_signed_in(): void
    {
        $response = $this->post('/auth/register', [
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'alex@example.com']);
    }

    public function test_a_user_can_update_their_name_and_choose_a_profile_avatar_without_changing_email(): void
    {
        $user = User::factory()->create(['email' => 'alex@example.com']);

        $response = $this->actingAs($user)->postJson('/profile', [
            'name' => 'Soun Piseth',
            'email' => 'changed@example.com',
            'avatar_action' => 'preset',
            'avatar_preset' => 'people13',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Soun Piseth')
            ->assertJsonPath('user.email', 'alex@example.com')
            ->assertJsonPath('user.avatar_action', 'preset')
            ->assertJsonPath('user.avatar_preset', 'people13')
            ->assertJsonPath('user.avatar_url', asset('images/icons/people13.png'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Soun Piseth',
            'email' => 'alex@example.com',
            'avatar_preset' => 'people13',
            'avatar_path' => null,
        ]);
    }

    public function test_a_user_can_update_their_name_without_submitting_an_avatar_action(): void
    {
        $user = User::factory()->create([
            'avatar_preset' => 'people4',
        ]);

        $response = $this->actingAs($user)->postJson('/profile', [
            'name' => 'Soun Piseth',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Soun Piseth')
            ->assertJsonPath('user.avatar_action', 'preset')
            ->assertJsonPath('user.avatar_preset', 'people4')
            ->assertJsonPath('user.avatar_url', asset('images/icons/people4.png'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Soun Piseth',
            'avatar_preset' => 'people4',
            'avatar_path' => null,
        ]);
    }

    public function test_a_signed_in_user_can_open_a_separate_settings_page(): void
    {
        $user = User::factory()->create([
            'avatar_preset' => 'people13',
        ]);

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Profile & account', false)
            ->assertSee('Preferences')
            ->assertSee('Due date reminders')
            ->assertSee('About TaskFlow')
            ->assertSee('Choose avatar 13')
            ->assertSee('/taskflow-icon.svg')
            ->assertSee(route('home'));
    }

    public function test_a_signed_in_user_sees_their_account_email_linked_to_settings_on_the_landing_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('Account')
            ->assertSee('alex@example.com')
            ->assertSee('href="'.route('profile.edit').'"', false);
    }

    public function test_workspace_filters_have_named_routes_and_restore_the_selected_filter(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $workspaceRoutes = [
            'workspace.index' => ['url' => '/workspace', 'filter' => 'all'],
            'workspace.today' => ['url' => '/workspace/today', 'filter' => 'today'],
            'workspace.overdue' => ['url' => '/workspace/overdue', 'filter' => 'overdue'],
            'workspace.completed' => ['url' => '/workspace/completed', 'filter' => 'done'],
        ];

        foreach ($workspaceRoutes as $routeName => $expected) {
            $this->assertSame($expected['url'], route($routeName, absolute: false));
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('data-workspace-filter="'.$expected['filter'].'"', false);
        }
    }

    public function test_workspace_project_route_selects_only_a_project_owned_by_the_user(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Personal']);
        $otherProject = User::factory()->create()->projects()->create(['name' => 'Private']);

        $this->actingAs($user)
            ->get(route('workspace.projects.show', $project))
            ->assertOk()
            ->assertSee('data-workspace-project-id="'.$project->id.'"', false)
            ->assertSee('data-project-url-template="'.route('workspace.projects.show', ['project' => '__PROJECT_ID__']).'"', false);

        $this->get(route('workspace.projects.show', $otherProject))
            ->assertNotFound();
    }

    public function test_workspace_routes_redirect_guests_to_login(): void
    {
        $project = User::factory()->create()->projects()->create(['name' => 'Private']);

        foreach ([
            route('workspace.index'),
            route('workspace.today'),
            route('workspace.overdue'),
            route('workspace.completed'),
            route('workspace.projects.show', $project),
        ] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_landing_sections_have_named_routes_without_fragment_links(): void
    {
        $sections = [
            'about' => 'landing.about',
            'features' => 'landing.features',
            'how-it-works' => 'landing.how-it-works',
            'contact' => 'landing.contact',
        ];

        foreach ($sections as $section => $routeName) {
            $this->get(route($routeName))
                ->assertOk()
                ->assertSee('data-landing-section="'.$section.'"', false)
                ->assertSee('id="'.$section.'"', false);
        }
    }

    public function test_a_guest_is_redirected_from_the_settings_page(): void
    {
        $this->get('/settings')
            ->assertRedirect('/login');
    }

    public function test_a_user_can_upload_a_profile_photo_and_replace_the_previous_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldAvatarPath = UploadedFile::fake()->image('old.png')->storePublicly('avatars/'.$user->id, 'public');
        $user->forceFill(['avatar_path' => $oldAvatarPath])->save();

        $response = $this->actingAs($user)->postJson('/profile', [
            'name' => $user->name,
            'avatar_action' => 'upload',
            'avatar_file' => UploadedFile::fake()->image('new.png', 128, 128),
        ]);

        $avatarPath = $user->fresh()->avatar_path;
        $response->assertOk()
            ->assertJsonPath('user.avatar_action', 'upload')
            ->assertJsonPath('user.avatar_url', Storage::disk('public')->url($avatarPath));
        Storage::disk('public')->assertExists($avatarPath);
        Storage::disk('public')->assertMissing($oldAvatarPath);
    }

    public function test_a_user_cannot_save_an_unsupported_profile_photo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_preset' => 'people4']);

        $this->actingAs($user)->postJson('/profile', [
            'name' => 'Changed Name',
            'avatar_action' => 'upload',
            'avatar_file' => UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('avatar_file');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => $user->name,
            'avatar_preset' => 'people4',
            'avatar_path' => null,
        ]);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_guest_cannot_update_a_profile(): void
    {
        $this->postJson('/profile', [
            'name' => 'Soun Piseth',
            'avatar_action' => 'initials',
        ])->assertUnauthorized();
    }

    public function test_google_sign_in_is_available_on_login_but_not_registration(): void
    {
        config(['services.google.client_id' => 'test-google-client-id']);

        $this->get('/login')
            ->assertOk()
            ->assertSee(route('home'))
            ->assertSee('aria-label="Back to home"', false)
            ->assertSee('/auth/google')
            ->assertSee('Continue with Google');

        $this->get('/register')
            ->assertOk()
            ->assertSee(route('home'))
            ->assertSee('aria-label="Back to home"', false)
            ->assertDontSee('/auth/google')
            ->assertDontSee('Continue with Google');
    }

    public function test_google_sign_in_explains_missing_oauth_configuration(): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => null,
        ]);

        $this->get('/auth/google')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('google');
    }

    public function test_google_sign_in_authenticates_immediately_and_emails_a_success_notification(): void
    {
        Mail::fake();
        config(['services.google.client_id' => 'test-google-client-id']);
        config(['services.google.client_secret' => 'test-google-client-secret']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-123',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
            'avatar' => 'https://lh3.googleusercontent.com/a/test-profile-photo',
        ]));

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1', 'SERVER_PORT' => 8000])->get('/auth/google')
            ->assertRedirect('https://socialite.fake/google/authorize');
        $this->get('/auth/google/callback')->assertRedirect('/');

        $this->assertAuthenticated();
        $user = User::where('email', 'google@example.com')->firstOrFail();
        $this->assertSame('https://lh3.googleusercontent.com/a/test-profile-photo', $user->google_avatar_url);
        $this->assertSame('https://lh3.googleusercontent.com/a/test-profile-photo', $user->fresh()->profilePhotoUrl());
        Mail::assertSent(GoogleLoginNotificationMail::class, fn (GoogleLoginNotificationMail $mail): bool => $mail->hasTo('google@example.com'));
    }

    public function test_gmail_smtp_uses_supported_scheme_with_starttls_on_port_587(): void
    {
        config([
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.port' => 587,
        ]);

        $transport = Mail::mailer('smtp')->getSymfonyTransport();

        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertTrue($transport->isAutoTls());
    }

    public function test_google_login_notification_uses_a_non_empty_default_from_address(): void
    {
        $this->assertNotEmpty(config('mail.from.address'));

        Mail::mailer('log')->to('google@example.com')->send(new GoogleLoginNotificationMail(
            'Google User',
            'google@example.com',
            'Friday, October 2, 2026',
        ));
    }

    public function test_google_json_sign_in_returns_a_sanctum_token_and_safe_user_data(): void
    {
        Mail::fake();
        config(['services.google.client_id' => 'test-google-client-id']);
        config(['services.google.client_secret' => 'test-google-client-secret']);
        config(['mail.default' => 'smtp']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-api',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
        ]));

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1', 'SERVER_PORT' => 8000])
            ->get('/auth/google?format=json')
            ->assertRedirect('https://socialite.fake/google/authorize');

        $response = $this->get('/auth/google/callback')
            ->assertOk()
            ->assertJsonPath('message', 'Signed in with Google successfully.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('notification_sent', true)
            ->assertJsonPath('user.email', 'google@example.com')
            ->assertJsonMissingPath('user.google_id');

        $this->withToken($response->json('access_token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'google@example.com')
            ->assertJsonMissingPath('data.google_id');
        Mail::assertSent(GoogleLoginNotificationMail::class, fn (GoogleLoginNotificationMail $mail): bool => $mail->hasTo('google@example.com'));
    }

    public function test_google_json_sign_in_does_not_claim_a_log_mailer_delivered_the_notification(): void
    {
        Mail::fake();
        config(['services.google.client_id' => 'test-google-client-id']);
        config(['services.google.client_secret' => 'test-google-client-secret']);
        config(['mail.default' => 'log']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-local-mail',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
        ]));

        $this->get('/auth/google?format=json')
            ->assertRedirect('https://socialite.fake/google/authorize');

        $this->get('/auth/google/callback')
            ->assertOk()
            ->assertJsonPath('notification_sent', false);
        Mail::assertSent(GoogleLoginNotificationMail::class, fn (GoogleLoginNotificationMail $mail): bool => $mail->hasTo('google@example.com'));
    }

    public function test_google_photo_refresh_keeps_a_user_selected_avatar(): void
    {
        Mail::fake();
        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => 'test-google-client-secret',
        ]);
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'google_id' => 'google-user-existing',
            'avatar_preset' => 'people13',
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-existing',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
            'avatar' => 'https://lh3.googleusercontent.com/a/refreshed-profile-photo',
        ]));

        $this->withServerVariables(['HTTP_HOST' => '127.0.0.1', 'SERVER_PORT' => 8000])
            ->get('/auth/google/callback')
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_avatar_url' => 'https://lh3.googleusercontent.com/a/refreshed-profile-photo',
            'avatar_preset' => 'people13',
        ]);
        $this->assertSame(asset('images/icons/people13.png'), $user->fresh()->profilePhotoUrl());
    }

    public function test_api_assets_include_every_avatar_and_app_icon_url(): void
    {
        $this->getJson('/api/assets')
            ->assertOk()
            ->assertJsonCount(13, 'data.avatars')
            ->assertJsonPath('data.avatars.12.name', 'people13')
            ->assertJsonPath('data.avatars.12.url', asset('images/icons/people13.png'))
            ->assertJsonCount(6, 'data.icons')
            ->assertJsonPath('data.images.welcome', asset('images/taskflow-welcome.jpg'))
            ->assertJsonPath('data.images.favicon', asset('favicon.ico'))
            ->assertJsonPath('data.app_icon', asset('taskflow-icon.svg'));
    }

    public function test_api_registration_returns_a_token_for_creating_workspace_data(): void
    {
        $registration = $this->postJson('/api/register', [
            'name' => 'API User',
            'email' => 'api-user@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
            'device_name' => 'Postman',
        ]);

        $registration->assertCreated()
            ->assertJsonPath('message', 'Account created successfully.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'api-user@example.com')
            ->assertJsonMissingPath('user.password');
        $this->assertDatabaseHas('users', ['email' => 'api-user@example.com']);

        $token = $registration->json('access_token');
        $projectResponse = $this->withToken($token)->postJson('/api/projects', [
            'name' => 'API project',
            'color' => '#123456',
        ]);

        $projectResponse->assertCreated()
            ->assertJsonPath('data.name', 'API project')
            ->assertJsonPath('data.color', '#123456');
        $projectId = $projectResponse->json('data.id');
        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'API project',
            'color' => '#123456',
        ]);

        $this->withToken($token)->patchJson("/api/projects/{$projectId}", [
            'color' => '#654321',
        ])->assertOk()
            ->assertJsonPath('data.color', '#654321');
        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'color' => '#654321',
        ]);

        $taskResponse = $this->withToken($token)->postJson("/api/projects/{$projectId}/tasks", [
            'title' => 'API task',
            'priority' => 'high',
        ]);

        $taskResponse->assertCreated()
            ->assertJsonPath('data.title', 'API task');
        $this->assertDatabaseHas('tasks', [
            'project_id' => $projectId,
            'title' => 'API task',
        ]);

        $otherUser = User::factory()->create();
        $privateProject = $otherUser->projects()->create(['name' => 'Private project']);
        $privateProject->tasks()->create(['title' => 'Private task']);

        $this->withToken($token)->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'api-user@example.com')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonPath('data.projects.0.color', '#654321')
            ->assertJsonPath('data.tasks.0.title', 'API task');
    }

    public function test_api_login_returns_401_json_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $this->postJson('/api/login', [
            'email' => 'api-user@example.com',
            'password' => 'incorrect-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are incorrect.');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_login_returns_a_bearer_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'name' => 'API User',
            'email' => 'api-user@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'api-user@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Signed in successfully.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'api-user@example.com');
        $this->withToken($response->json('access_token'))->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'api-user@example.com');
    }

    public function test_api_workspace_returns_json_for_an_unauthenticated_request(): void
    {
        $this->get('/api/workspace')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_api_project_creation_returns_json_validation_errors_for_an_invalid_color(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-api')->plainTextToken;

        $this->withToken($token)->postJson('/api/projects', [
            'name' => 'Invalid project color',
            'color' => 'purple',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('color');
        $this->assertDatabaseMissing('projects', ['name' => 'Invalid project color']);
    }

    public function test_api_project_data_requires_a_token_and_is_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Personal']);
        Project::create(['user_id' => User::factory()->create()->id, 'name' => 'Private']);

        $this->getJson('/api/projects')->assertUnauthorized();

        $token = $user->createToken('test-api')->plainTextToken;
        $this->withToken($token)
            ->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $project->id)
            ->assertJsonPath('data.0.name', 'Personal');

        $this->withToken($token)->postJson('/api/profile', [
            'name' => 'Updated Name',
            'avatar_action' => 'preset',
            'avatar_preset' => 'people13',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.avatar_url', asset('images/icons/people13.png'));

        $tokenId = $user->tokens()->firstOrFail()->getKey();
        $this->withToken($token)->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Signed out successfully.');
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    public function test_google_sign_in_rejects_unverified_email_claims(): void
    {
        Mail::fake();
        config(['services.google.client_id' => 'test-google-client-id']);
        config(['services.google.client_secret' => 'test-google-client-secret']);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-123',
            'email' => 'google@example.com',
            'email_verified' => false,
            'name' => 'Google User',
        ]));

        $this->get('/auth/google/callback')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('google');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'google@example.com']);
        Mail::assertNothingSent();
    }

    public function test_a_user_can_create_and_filter_their_tasks(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Personal', 'color' => '#8977f8']);
        $this->actingAs($user);

        $this->postJson("/projects/{$project->id}/tasks", [
            'title' => 'Plan the week',
            'due_date' => now()->toDateTimeString(),
            'priority' => 'high',
        ])->assertCreated()->assertJsonPath('data.title', 'Plan the week');

        $this->getJson('/tasks?due=today')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Plan the week');
    }

    public function test_a_user_can_read_a_project_and_its_task(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Personal', 'color' => '#8977f8']);
        $task = Task::create(['project_id' => $project->id, 'title' => 'Read the brief']);
        $this->actingAs($user);

        $this->getJson("/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Personal');

        $this->getJson("/projects/{$project->id}/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Read the brief');
    }

    public function test_users_cannot_read_another_users_projects(): void
    {
        $project = Project::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Private',
            'color' => '#8977f8',
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/projects/{$project->id}/tasks")
            ->assertNotFound();
    }

    public function test_a_task_cannot_be_moved_into_another_users_project(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Mine', 'color' => '#8977f8']);
        $task = Task::create(['project_id' => $project->id, 'title' => 'Private task']);
        $otherProject = Project::create([
            'user_id' => User::factory()->create()->id,
            'name' => 'Not mine',
            'color' => '#8977f8',
        ]);

        $this->actingAs($user)
            ->patchJson("/projects/{$project->id}/tasks/{$task->id}", ['project_id' => $otherProject->id])
            ->assertUnprocessable();
    }
}
