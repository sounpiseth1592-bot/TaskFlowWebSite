<?php

namespace Tests\Feature;

use App\Mail\GoogleLoginNotificationMail;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
        $user = User::factory()->create([
            'email' => 'alex@example.com',
        ]);

        $response = $this->actingAs($user)->postJson('/profile', [
            'name' => 'Soun Piseth',
            'description' => 'A short introduction.',
            'email' => 'changed@example.com',
            'avatar_action' => 'preset',
            'avatar_preset' => 'people13',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Soun Piseth')
            ->assertJsonPath('user.email', 'alex@example.com')
            ->assertJsonPath('user.description', 'A short introduction.')
            ->assertJsonPath('user.avatar_action', 'preset')
            ->assertJsonPath('user.avatar_preset', 'people13')
            ->assertJsonPath('user.avatar_url', asset('images/icons/people13.png'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Soun Piseth',
            'email' => 'alex@example.com',
            'description' => 'A short introduction.',
            'avatar_preset' => 'people13',
            'avatar_path' => null,
        ]);
    }

    public function test_a_user_can_update_their_name_without_submitting_an_avatar_action(): void
    {
        $user = User::factory()->create([
            'avatar_preset' => 'people4',
            'description' => 'Keep this introduction.',
        ]);

        $response = $this->actingAs($user)->postJson('/profile', [
            'name' => 'Soun Piseth',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Soun Piseth')
            ->assertJsonPath('user.description', 'Keep this introduction.')
            ->assertJsonPath('user.avatar_action', 'preset')
            ->assertJsonPath('user.avatar_preset', 'people4')
            ->assertJsonPath('user.avatar_url', asset('images/icons/people4.png'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Soun Piseth',
            'description' => 'Keep this introduction.',
            'avatar_preset' => 'people4',
            'avatar_path' => null,
        ]);
    }

    public function test_a_user_can_clear_their_profile_description(): void
    {
        $user = User::factory()->create(['description' => 'A short introduction.']);

        $this->actingAs($user)->postJson('/profile', [
            'name' => $user->name,
            'description' => '   ',
        ])->assertOk()
            ->assertJsonPath('user.description', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'description' => null,
        ]);
    }

    public function test_a_user_cannot_save_a_profile_description_longer_than_500_characters(): void
    {
        $user = User::factory()->create(['description' => 'Existing description.']);

        $this->actingAs($user)->postJson('/profile', [
            'name' => $user->name,
            'description' => str_repeat('a', 501),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('description');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'description' => 'Existing description.',
        ]);
    }

    public function test_a_signed_in_user_can_open_settings_separately_from_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings')
            ->assertOk()
            ->assertSee('Preferences')
            ->assertSee('Due date reminders')
            ->assertSee('API access token')
            ->assertSee('id="account-menu-button"', false)
            ->assertSee('aria-haspopup="true"', false)
            ->assertSee('href="'.route('profile.show').'"', false)
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertSee('src="'.asset('images/icons/dark_mode.png').'"', false)
            ->assertDontSee('Profile information')
            ->assertDontSee('About TaskFlow');
    }

    public function test_a_signed_in_user_can_open_and_edit_their_separate_profile_page(): void
    {
        $user = User::factory()->create([
            'name' => 'Alex Morgan',
            'description' => 'A TaskFlow user.',
            'avatar_preset' => 'people13',
        ]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Profile information')
            ->assertSee('value="Alex Morgan"', false)
            ->assertSee('A TaskFlow user.')
            ->assertSee('Choose avatar 13')
            ->assertSee('maxlength="500"', false)
            ->assertSee('Choose from device')
            ->assertSee('accept="image/png,image/jpeg,image/webp"', false)
            ->assertDontSee('External image URL')
            ->assertSee('data-light-icon="'.asset('images/icons/lightmode.png').'"', false)
            ->assertSee('id="account-menu-button"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('href="'.route('profile.edit').'"', false)
            ->assertDontSee('admin-icons-api')
            ->assertSee('/taskflow-icon.svg');
    }

    public function test_a_signed_in_user_can_generate_and_replace_their_api_token_from_settings(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherToken = $otherUser->createToken('TaskFlow API token')->plainTextToken;

        $firstResponse = $this->actingAs($user)->post(route('profile.api-token'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('API access token')
            ->assertSee('Copy your new token')
            ->assertDontSee($otherToken);
        $firstToken = trim($firstResponse->viewData('apiToken'));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'TaskFlow API token',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $otherUser->id,
            'name' => 'TaskFlow API token',
        ]);

        $secondResponse = $this->post(route('profile.api-token'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
        $secondToken = trim($secondResponse->viewData('apiToken'));

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->app['auth']->forgetGuards();
        $this->withToken($firstToken)->getJson('/api/user')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($secondToken)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
        $this->app['auth']->forgetGuards();
        $this->withToken($otherToken)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $otherUser->email);
    }

    public function test_a_guest_cannot_generate_an_api_token(): void
    {
        $this->post(route('profile.api-token'))
            ->assertRedirect('/login');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_user_returns_the_signed_in_accounts_data_from_its_browser_session_without_a_bearer_token(): void
    {
        $user = User::factory()->create(['email' => 'session-user@example.com']);
        $project = $user->projects()->create(['name' => 'Session project']);
        $project->tasks()->create(['title' => 'Session task']);

        $otherUser = User::factory()->create();
        $otherProject = $otherUser->projects()->create(['name' => 'Private project']);
        $otherProject->tasks()->create(['title' => 'Private task']);

        $this->actingAs($user)->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'session-user@example.com')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.projects.0.id', $project->id)
            ->assertJsonPath('data.tasks.0.title', 'Session task')
            ->assertJsonMissing(['title' => 'Private project'])
            ->assertJsonMissing(['title' => 'Private task'])
            ->assertJsonMissingPath('data.password');
    }

    public function test_public_users_endpoint_excludes_the_admin_and_returns_other_users_taskflow_data(): void
    {
        $firstUser = User::factory()->create([
            'name' => 'Public User',
            'email' => 'public-user@example.com',
            'google_id' => 'private-google-id',
            'avatar_preset' => 'people13',
            'description' => 'A short public introduction.',
        ]);
        $firstProject = $firstUser->projects()->create([
            'name' => 'Public project',
            'color' => '#654321',
            'description' => 'A project description.',
            'icon' => 'icon16.png',
        ]);
        $firstTask = $firstProject->tasks()->create([
            'title' => 'Public task',
            'notes' => 'Task details',
            'priority' => 'high',
            'done' => true,
            'subtasks' => [
                ['title' => 'First checklist item', 'done' => true],
                ['title' => 'Second checklist item', 'done' => false],
            ],
        ]);

        $secondUser = User::factory()->create(['name' => 'Another User']);
        $secondProject = $secondUser->projects()->create(['name' => 'Another project']);
        $secondTask = $secondProject->tasks()->create(['title' => 'Another task']);

        $admin = User::factory()->create([
            'name' => 'Private Admin',
            'email' => 'SounPisethAdmin@gmail.com',
        ]);
        $adminProject = $admin->projects()->create(['name' => 'Private admin project']);
        $adminProject->tasks()->create(['title' => 'Private admin task']);

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $firstUser->id)
            ->assertJsonPath('data.0.name', 'Public User')
            ->assertJsonPath('data.0.email', 'public-user@example.com')
            ->assertJsonPath('data.0.description', 'A short public introduction.')
            ->assertJsonPath('data.0.avatar_url', asset('images/icons/people13.png'))
            ->assertJsonPath('data.0.projects.0.id', $firstProject->id)
            ->assertJsonPath('data.0.projects.0.name', 'Public project')
            ->assertJsonPath('data.0.projects.0.color', '#654321')
            ->assertJsonPath('data.0.projects.0.description', 'A project description.')
            ->assertJsonPath('data.0.projects.0.icon', 'icon16.png')
            ->assertJsonPath('data.0.projects.0.icon_url', asset('images/icon-new-project/icon16.png'))
            ->assertJsonPath('data.0.projects.0.tasks.0.id', $firstTask->id)
            ->assertJsonPath('data.0.projects.0.tasks.0.title', 'Public task')
            ->assertJsonPath('data.0.projects.0.tasks.0.notes', 'Task details')
            ->assertJsonPath('data.0.projects.0.tasks.0.priority', 'high')
            ->assertJsonPath('data.0.projects.0.tasks.0.done', true)
            ->assertJsonPath('data.0.projects.0.tasks.0.subtasks.0.title', 'First checklist item')
            ->assertJsonPath('data.0.projects.0.tasks.0.subtasks.0.done', true)
            ->assertJsonPath('data.0.projects.0.tasks.0.subtasks.1.title', 'Second checklist item')
            ->assertJsonPath('data.1.id', $secondUser->id)
            ->assertJsonPath('data.1.email', $secondUser->email)
            ->assertJsonPath('data.1.description', null)
            ->assertJsonPath('data.1.avatar_url', null)
            ->assertJsonPath('data.1.projects.0.tasks.0.id', $secondTask->id)
            ->assertJsonPath('data.1.projects.0.tasks.0.title', 'Another task')
            ->assertJsonMissing(['email' => $admin->email])
            ->assertJsonMissing(['name' => 'Private admin project'])
            ->assertJsonMissing(['title' => 'Private admin task'])
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.google_id');
    }

    public function test_a_signed_in_user_can_open_their_profile_from_the_workspace_account_menu(): void
    {
        $user = User::factory()->create([
            'name' => 'Alex Morgan',
            'email' => 'alex@example.com',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertSee('User profile')
            ->assertSee('alex@example.com')
            ->assertSee('href="'.route('profile.show').'"', false)
            ->assertSee('data-dark-icon="'.asset('images/icons/dark_mode.png').'"', false)
            ->assertSee('data-light-icon="'.asset('images/icons/lightmode.png').'"', false)
            ->assertSee('aria-label="Switch to dark mode"', false)
            ->assertSee('src="'.asset('images/icons/dark_mode.png').'"', false)
            ->assertDontSee('data-theme-icon')
            ->assertDontSee('admin-icons-api');
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

        $this->assertSame('/profile', route('profile.show', absolute: false));
    }

    public function test_workspace_dashboard_includes_project_progress_and_task_overview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('workspace.index'))
            ->assertOk()
            ->assertSee('Project progress')
            ->assertSee('See every project and its task progress at a glance.')
            ->assertSee('All tasks')
            ->assertSee('In progress')
            ->assertSee('Due today')
            ->assertSee('src="'.asset('images/icons/Project_taskflow.png').'"', false)
            ->assertSee('src="'.asset('images/icons/Task_taskflow.png').'"', false)
            ->assertSee('src="'.asset('images/icons/inProgress_taskflow.png').'"', false)
            ->assertSee('src="'.asset('images/icons/dueToday_taskflow.png').'"', false)
            ->assertSee('src="'.asset('images/icons/Passdue_taskflow.png').'"', false)
            ->assertSee('src="'.asset('images/icons/complete_taskflow.png').'"', false)
            ->assertSee('id="account-menu-button"', false)
            ->assertSee('href="'.route('profile.show').'"', false)
            ->assertSee('href="'.route('profile.edit').'"', false);
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
            route('profile.show'),
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

    public function test_a_guest_is_redirected_from_the_settings_and_profile_pages(): void
    {
        $this->get('/settings')
            ->assertRedirect('/login');

        $this->get('/profile')
            ->assertRedirect('/login');
    }

    public function test_a_user_can_upload_a_profile_photo_and_replace_the_previous_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldAvatarPath = UploadedFile::fake()->image('old.png')->storePublicly('avatars/'.$user->id, 'public');
        $user->forceFill(['avatar_path' => $oldAvatarPath])->save();

        $response = $this->actingAs($user)->postJson('/profile', [
            'avatar_action' => 'upload',
            'avatar_file' => UploadedFile::fake()->image('new.png', 128, 128),
        ]);

        $avatarPath = $user->fresh()->avatar_path;
        $response->assertOk()
            ->assertJsonPath('user.name', $user->name)
            ->assertJsonPath('user.avatar_action', 'upload')
            ->assertJsonPath('user.avatar_url', Storage::disk('public')->url($avatarPath));
        Storage::disk('public')->assertExists($avatarPath);
        Storage::disk('public')->assertMissing($oldAvatarPath);
        $this->getJson('/api/users')->assertJsonFragment([
            'avatar_url' => Storage::disk('public')->url($avatarPath),
        ]);
        $this->withToken($user->createToken('avatar-check')->plainTextToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', Storage::disk('public')->url($avatarPath));
        $this->actingAs($user)->get(route('profile.show'))
            ->assertSee('data-profile-photo-url="'.Storage::disk('public')->url($avatarPath).'"', false)
            ->assertSee('src="'.Storage::disk('public')->url($avatarPath).'"', false);
    }

    public function test_the_authenticated_profile_api_stores_uploaded_photos_and_exposes_their_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $apiToken = $user->createToken('profile-upload')->plainTextToken;
        $photo = UploadedFile::fake()->image('profile.png', 128, 128);

        $response = $this->withToken($apiToken)->postJson('/api/profile', [
            'avatar_action' => 'upload',
            'avatar_file' => $photo,
        ]);

        $avatarPath = $user->fresh()->avatar_path;
        $avatarUrl = Storage::disk('public')->url($avatarPath);
        $response->assertOk()
            ->assertJsonPath('user.avatar_action', 'upload')
            ->assertJsonPath('user.avatar_url', $avatarUrl);
        Storage::disk('public')->assertExists($avatarPath);
        $this->assertSame($avatarPath, $user->fresh()->avatar_path);

        $this->withToken($apiToken)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', $avatarUrl);
        $this->getJson('/api/users')->assertJsonFragment([
            'avatar_url' => $avatarUrl,
        ]);
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

    public function test_guest_auth_pages_offer_google_sign_in_and_signed_in_users_return_home(): void
    {
        config(['services.google.client_id' => 'test-google-client-id']);

        foreach ([
            '/login' => '/auth/login',
            '/register' => '/auth/register',
        ] as $page => $formAction) {
            $this->get($page)
                ->assertOk()
                ->assertSee(route('home'))
                ->assertSee('aria-label="Back to home"', false)
                ->assertSee('action="'.$formAction.'"', false)
                ->assertSee('href="'.route('google.redirect').'"', false)
                ->assertSee('Continue with Google');
        }

        $this->actingAs(User::factory()->create());

        foreach (['/login', '/register'] as $page) {
            $this->get($page)->assertRedirect('/');
        }
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

        $this->getJson('/api/auth/google?format=json')
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Google sign-in is not configured.');
    }

    public function test_google_api_sign_in_requires_a_browser_session(): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => 'test-google-client-secret',
        ]);

        $this->getJson('/api/auth/google?format=json')
            ->assertBadRequest()
            ->assertJsonPath('message', 'Google sign-in must be started from a browser session.');
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
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'google_id' => 'google-user-api',
        ]);
        $project = $user->projects()->create(['name' => 'Google project']);
        $project->tasks()->create(['title' => 'Google task']);
        $privateProject = User::factory()->create()->projects()->create(['name' => 'Private project']);
        $privateProject->tasks()->create(['title' => 'Another user task']);

        config(['services.google.client_id' => 'test-google-client-id']);
        config(['services.google.client_secret' => 'test-google-client-secret']);
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => 'test-sender@gmail.com',
            'mail.mailers.smtp.password' => 'test-app-password',
        ]);
        $redirect = $this->withServerVariables(['HTTP_HOST' => '127.0.0.1', 'SERVER_PORT' => 8000])
            ->get('/auth/google/token')
            ->assertRedirect();
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $googleParameters);
        $this->assertSame('consent select_account', $googleParameters['prompt']);
        $this->assertSame('offline', $googleParameters['access_type']);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-api',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
        ]));

        $response = $this->get('/auth/google/callback')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('message', 'Signed in with Google successfully.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('notification_sent', true)
            ->assertJsonPath('user.email', 'google@example.com')
            ->assertJsonPath('user.projects.0.id', $project->id)
            ->assertJsonPath('user.projects.0.tasks_count', 1)
            ->assertJsonPath('user.projects.0.open_tasks_count', 1)
            ->assertJsonCount(1, 'user.tasks')
            ->assertJsonPath('user.tasks.0.title', 'Google task')
            ->assertJsonMissing(['title' => 'Another user task'])
            ->assertJsonMissingPath('user.google_id')
            ->assertJsonMissingPath('user.password');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('users', [
            'email' => 'google@example.com',
            'google_id' => 'google-user-api',
        ]);
        $this->withToken($response->json('access_token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'google@example.com')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.tasks')
            ->assertJsonMissingPath('data.google_id');
        $this->withToken($response->json('access_token'))
            ->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', null)
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.tasks');
        Mail::assertSent(GoogleLoginNotificationMail::class, fn (GoogleLoginNotificationMail $mail): bool => $mail->hasTo('google@example.com'));
    }

    public function test_signed_in_google_account_can_get_an_api_token_for_its_taskflow_data(): void
    {
        $user = User::factory()->create([
            'email' => 'google@example.com',
            'google_id' => 'google-user-token',
        ]);
        $project = $user->projects()->create(['name' => 'Google project']);
        $project->tasks()->create(['title' => 'Google task']);
        $privateProject = User::factory()->create()->projects()->create(['name' => 'Private project']);
        $privateProject->tasks()->create(['title' => 'Another user task']);

        config(['services.google.client_id' => 'test-google-client-id']);
        config(['services.google.client_secret' => 'test-google-client-secret']);
        Mail::fake();
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-token',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
        ]));

        $this->actingAs($user)->get('/auth/google/token')
            ->assertRedirect('https://socialite.fake/google/authorize');

        $response = $this->get('/auth/google/callback')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('message', 'Signed in with Google successfully.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'google@example.com')
            ->assertJsonPath('user.projects.0.id', $project->id)
            ->assertJsonPath('user.tasks.0.title', 'Google task')
            ->assertJsonMissing(['title' => 'Another user task'])
            ->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('user.google_id');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->withToken($response->json('access_token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'google@example.com')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.tasks');
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

    public function test_google_sign_in_succeeds_when_gmail_smtp_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id',
            'services.google.client_secret' => 'test-google-client-secret',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.gmail.com',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-user-no-mail-config',
            'email' => 'google@example.com',
            'email_verified' => true,
            'name' => 'Google User',
        ]));
        Mail::shouldReceive('to')->never();
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Google sign-in notification was skipped because Gmail SMTP credentials are missing. Configure MAIL_USERNAME and MAIL_PASSWORD with a Gmail address and Google App Password.',
                \Mockery::type('array'),
            );

        $this->get('/auth/google?format=json')
            ->assertRedirect('https://socialite.fake/google/authorize');

        $this->get('/auth/google/callback')
            ->assertOk()
            ->assertJsonPath('message', 'Signed in with Google successfully.')
            ->assertJsonPath('notification_sent', false)
            ->assertJsonPath('user.email', 'google@example.com')
            ->assertJsonStructure(['access_token']);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'google@example.com']);
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

    public function test_api_assets_include_all_avatar_project_and_app_icon_urls(): void
    {
        $response = $this->getJson('/api/assets')
            ->assertOk()
            ->assertJsonCount(13, 'data.avatars')
            ->assertJsonPath('data.avatars.12.name', 'people13')
            ->assertJsonPath('data.avatars.12.url', asset('images/icons/people13.png'))
            ->assertJsonCount(6, 'data.icons')
            ->assertJsonCount(6, 'data.workspace')
            ->assertJsonPath('data.workspace.0.name', 'projects')
            ->assertJsonPath('data.workspace.0.url', asset('images/icons/Project_taskflow.png'))
            ->assertJsonPath('data.workspace.5.name', 'completed')
            ->assertJsonPath('data.workspace.5.url', asset('images/icons/complete_taskflow.png'))
            ->assertJsonPath('data.images.welcome', asset('images/taskflow-welcome.jpg'))
            ->assertJsonPath('data.images.favicon', asset('favicon.ico'))
            ->assertJsonPath('data.app_icon', asset('taskflow-icon.svg'));

        $projectIconPaths = glob(public_path('images/icon-new-project/*.png')) ?: [];
        $response->assertJsonCount(count($projectIconPaths), 'data.project_icons');

        foreach ($projectIconPaths as $projectIconPath) {
            $iconName = basename($projectIconPath);
            $response->assertJsonFragment([
                'name' => $iconName,
                'url' => asset('images/icon-new-project/'.$iconName),
            ]);
        }
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
            ->assertJsonPath('user.description', null)
            ->assertJsonMissingPath('user.password');
        $this->assertDatabaseHas('users', ['email' => 'api-user@example.com']);

        $token = $registration->json('access_token');
        $this->withToken($token)->getJson('/api/workspace')
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', null)
            ->assertJsonCount(0, 'data.projects')
            ->assertJsonCount(0, 'data.tasks');
        $this->withToken($token)->getJson('/api/tasks?due=today')
            ->assertOk()
            ->assertExactJson(['data' => []]);
        $this->withToken($token)->getJson('/api/tasks?due=overdue')
            ->assertOk()
            ->assertExactJson(['data' => []]);
        $this->withToken($token)->getJson('/api/tasks?due=done')
            ->assertOk()
            ->assertExactJson(['data' => []]);

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

    public function test_api_registration_normalizes_email_and_rejects_case_insensitive_duplicates(): void
    {
        $registration = $this->postJson('/api/register', [
            'name' => 'API User',
            'email' => ' API-USER@Example.COM ',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ]);

        $registration->assertCreated()
            ->assertJsonPath('user.email', 'api-user@example.com')
            ->assertJsonCount(0, 'user.projects')
            ->assertJsonCount(0, 'user.tasks')
            ->assertJsonMissingPath('user.password');
        $this->assertDatabaseHas('users', ['email' => 'api-user@example.com']);
        $this->withToken($registration->json('access_token'))
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'api-user@example.com')
            ->assertJsonPath('data.description', null)
            ->assertJsonCount(0, 'data.projects')
            ->assertJsonCount(0, 'data.tasks');

        $this->postJson('/api/register', [
            'name' => 'Duplicate API User',
            'email' => 'API-USER@example.com',
            'password' => 'correct-horse-battery',
            'password_confirmation' => 'correct-horse-battery',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('users', 1);
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
        $user = User::factory()->create([
            'name' => 'API User',
            'email' => 'api-user@example.com',
            'password' => 'correct-horse-battery',
            'description' => 'API account description.',
        ]);
        $project = $user->projects()->create(['name' => 'Personal']);
        $project->tasks()->create(['title' => 'My task']);

        $otherProject = User::factory()->create()->projects()->create(['name' => 'Private']);
        $otherProject->tasks()->create(['title' => 'Another user task']);

        $response = $this->postJson('/api/login', [
            'email' => 'api-user@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Signed in successfully.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'api-user@example.com')
            ->assertJsonPath('user.description', 'API account description.')
            ->assertJsonCount(1, 'user.projects')
            ->assertJsonCount(1, 'user.tasks')
            ->assertJsonPath('user.projects.0.id', $project->id)
            ->assertJsonPath('user.tasks.0.title', 'My task')
            ->assertJsonMissing(['title' => 'Another user task'])
            ->assertJsonMissingPath('user.password');
        $this->withToken($response->json('access_token'))->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'api-user@example.com')
            ->assertJsonPath('data.description', 'API account description.')
            ->assertJsonCount(1, 'data.projects')
            ->assertJsonCount(1, 'data.tasks');
    }

    public function test_api_login_accepts_email_with_different_case_and_surrounding_whitespace(): void
    {
        User::factory()->create([
            'email' => 'api-user@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => ' API-USER@EXAMPLE.COM ',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Signed in successfully.')
            ->assertJsonPath('user.email', 'api-user@example.com');
    }

    public function test_api_workspace_returns_json_for_an_unauthenticated_request(): void
    {
        $this->get('/api/workspace')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_workspace_can_load_api_data_using_its_authenticated_session(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Personal']);
        $project->tasks()->create([
            'title' => 'Completed task',
            'done' => true,
            'priority' => 'medium',
        ]);
        $project->tasks()->create([
            'title' => 'Open task',
            'done' => false,
            'priority' => 'medium',
        ]);

        $this->actingAs($user)
            ->withHeader('Referer', config('app.url').'/workspace/completed')
            ->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['title' => 'Open task'])
            ->assertJsonFragment(['title' => 'Completed task']);
    }

    public function test_api_task_filters_return_completed_today_and_overdue_tasks(): void
    {
        $this->travelTo(now()->startOfDay());

        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Personal']);
        $project->tasks()->create([
            'title' => 'Past due',
            'due_date' => now()->subDay(),
            'priority' => 'high',
        ]);
        $project->tasks()->create([
            'title' => 'Completed',
            'due_date' => now()->subDay(),
            'priority' => 'medium',
            'done' => true,
        ]);
        $project->tasks()->create([
            'title' => 'Due today',
            'due_date' => now(),
            'priority' => 'low',
        ]);
        $project->tasks()->create([
            'title' => 'Upcoming',
            'due_date' => now()->addDay(),
            'priority' => 'medium',
        ]);

        $token = $user->createToken('test-api')->plainTextToken;

        $this->withToken($token)->getJson('/api/tasks?due=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Past due']);
        $this->withToken($token)->getJson('/api/tasks?due=done')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Completed']);
        $this->withToken($token)->getJson('/api/tasks?due=today')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['title' => 'Due today']);
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

    public function test_api_project_creation_persists_description_and_an_available_icon(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-api')->plainTextToken;

        $this->withToken($token)->postJson('/api/projects', [
            'name' => 'Product launch',
            'description' => 'Coordinate the launch.',
            'icon' => 'icon16.png',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Product launch')
            ->assertJsonPath('data.description', 'Coordinate the launch.')
            ->assertJsonPath('data.icon', 'icon16.png');

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Product launch',
            'description' => 'Coordinate the launch.',
            'icon' => 'icon16.png',
        ]);
    }

    public function test_api_project_creation_rejects_an_icon_outside_the_project_icon_folder(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-api')->plainTextToken;

        $this->withToken($token)->postJson('/api/projects', [
            'name' => 'Invalid icon project',
            'icon' => '../people13.png',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('icon');

        $this->assertDatabaseMissing('projects', ['name' => 'Invalid icon project']);
    }

    public function test_a_user_can_update_a_project_description_and_icon(): void
    {
        $user = User::factory()->create();
        $project = $user->projects()->create(['name' => 'Product launch']);
        $token = $user->createToken('test-api')->plainTextToken;

        $this->withToken($token)->patchJson("/api/projects/{$project->id}", [
            'description' => 'Launch details.',
            'icon' => 'icon29.png',
        ])->assertOk()
            ->assertJsonPath('data.description', 'Launch details.')
            ->assertJsonPath('data.icon', 'icon29.png');

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'description' => 'Launch details.',
            'icon' => 'icon29.png',
        ]);
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

    public function test_a_user_can_create_a_task_with_subtasks(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Personal']);
        $token = $user->createToken('test-api')->plainTextToken;

        $this->withToken($token)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Plan the week',
            'subtasks' => [
                ['title' => 'Review calendar', 'done' => false],
                ['title' => 'Set priorities', 'done' => true],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Plan the week')
            ->assertJsonPath('data.subtasks.0.title', 'Review calendar')
            ->assertJsonPath('data.subtasks.1.done', true);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $project->id,
            'title' => 'Plan the week',
            'subtasks' => '[{"title":"Review calendar","done":false},{"title":"Set priorities","done":true}]',
        ]);
    }

    public function test_a_user_cannot_create_a_task_with_an_invalid_subtask_title(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['user_id' => $user->id, 'name' => 'Personal']);
        $token = $user->createToken('test-api')->plainTextToken;

        $this->withToken($token)->postJson("/api/projects/{$project->id}/tasks", [
            'title' => 'Plan the week',
            'subtasks' => [['title' => str_repeat('a', 161), 'done' => false]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('subtasks.0.title');

        $this->assertDatabaseMissing('tasks', ['title' => 'Plan the week']);
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
