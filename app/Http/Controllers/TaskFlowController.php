<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\AssetController;
use App\Http\Resources\AccountResource;
use App\Http\Resources\UserResource;
use App\Mail\GoogleLoginNotificationMail;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class TaskFlowController extends Controller
{
    public function dashboard(): View|RedirectResponse
    {
        if (! Auth::check()) {
            return view('home');
        }

        if (Auth::user()->can('access-admin')) {
            return redirect()->route('admin.index');
        }

        return $this->workspaceView();
    }

    public function workspacePage(string $filter = 'all'): View
    {
        return $this->workspaceView($filter);
    }

    public function workspaceProject(Project $project): View
    {
        $this->authorizeProject($project);

        return $this->workspaceView(project: $project);
    }

    private function workspaceView(string $filter = 'all', ?Project $project = null): View
    {
        $user = Auth::user();

        return view('dashboard', [
            'user' => $user,
            'avatarPresets' => User::PROFILE_AVATAR_PRESETS,
            'workspaceFilter' => $filter,
            'workspaceProject' => $project,
            'workspaceIcons' => AssetController::workspaceIconUrls(),
            'projectUrlTemplate' => route('workspace.projects.show', ['project' => '__PROJECT_ID__']),
        ]);
    }

    public function landingSection(string $section): View
    {
        return view('home', ['landingSection' => $section]);
    }

    public function showLogin(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('home') : view('auth', ['mode' => 'login']);
    }

    public function showRegister(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('home') : view('auth', ['mode' => 'register']);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (
            mb_strtolower(trim($data['email']))
            === mb_strtolower((string) config('admin.email'))
        ) {
            return back()->withErrors([
                'email' => 'This email is reserved for the configured administrator account.',
            ])->onlyInput('email');
        }

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those details do not match an account.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return Auth::user()->can('access-admin')
            ? redirect()->route('admin.index')
            : redirect()->intended(route('home'));
    }

    public function redirectToGoogle(Request $request): JsonResponse|RedirectResponse
    {
        $expectsJson = $request->expectsJson() || $request->query('format') === 'json';

        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            if ($expectsJson) {
                return response()->json([
                    'message' => 'Google sign-in is not configured.',
                ], 503);
            }

            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in is not configured. Add GOOGLE_CLIENT_SECRET to the server environment.',
            ]);
        }

        if (! $request->hasSession()) {
            return response()->json([
                'message' => 'Google sign-in must be started from a browser session.',
            ], 400);
        }

        if ($expectsJson) {
            $request->session()->put('google_auth_json_response', true);
        } else {
            $request->session()->forget('google_auth_json_response');
        }

        $callback = parse_url(config('services.google.redirect'));
        $callbackHost = $callback['host'] ?? null;
        $callbackPort = $callback['port'] ?? null;

        if (
            $callbackHost
            && (
                request()->getHost() !== $callbackHost
                || (request()->getPort() !== ($callbackPort ?? (request()->isSecure() ? 443 : 80)))
            )
        ) {
            $origin = ($callback['scheme'] ?? request()->getScheme()).'://'.$callbackHost;
            if ($callbackPort) {
                $origin .= ':'.$callbackPort;
            }

            return redirect()->away($origin.'/auth/google');
        }

        $googleDriver = Socialite::driver('google');

        if ($expectsJson) {
            $googleDriver->with([
                'prompt' => 'consent select_account',
                'access_type' => 'offline',
            ]);
        }

        return $googleDriver->redirect();
    }

    public function redirectToGoogleApi(Request $request): JsonResponse|RedirectResponse
    {
        $request->query->set('format', 'json');

        return $this->redirectToGoogle($request);
    }

    public function handleGoogleCallback(Request $request): JsonResponse|RedirectResponse
    {
        $expectsJson = $request->expectsJson()
            || $request->session()->pull('google_auth_json_response', false);

        if ($request->filled('error')) {
            return $this->googleAuthError($request, 'Google sign-in was cancelled or denied.', 401, $expectsJson);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException|GuzzleException $exception) {
            Log::warning('Google OAuth callback failed.', ['exception' => $exception::class]);

            return $this->googleAuthError(
                $request,
                'Google sign-in could not be verified. Please try again.',
                401,
                $expectsJson,
            );
        }

        $profile = $googleUser->getRaw();
        if (
            ! $googleUser->getId()
            || ! $googleUser->getEmail()
            || ($profile['email_verified'] ?? false) !== true
            || ! filter_var($googleUser->getEmail(), FILTER_VALIDATE_EMAIL)
        ) {
            return $this->googleAuthError(
                $request,
                'Google did not provide a verified email address for this account.',
                422,
                $expectsJson,
            );
        }

        $googleId = (string) $googleUser->getId();
        $email = mb_strtolower($googleUser->getEmail());
        $googleAvatarUrl = $this->trustedGoogleAvatarUrl($googleUser->getAvatar());
        $user = User::where('google_id', $googleId)->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && $user->google_id && $user->google_id !== $googleId) {
            return $this->googleAuthError(
                $request,
                'This email is linked to a different Google account.',
                409,
                $expectsJson,
            );
        }

        if (! $user) {
            if (strcasecmp($email, (string) config('admin.email')) === 0) {
                return $this->googleAuthError(
                    $request,
                    'The administrator account must be created through the server setup command.',
                    403,
                    $expectsJson,
                );
            }

            $user = User::create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'password' => Str::random(64),
                'google_id' => $googleId,
                'email_verified_at' => now(),
                'google_avatar_url' => $googleAvatarUrl,
            ]);
        } else {
            $googleAttributes = [
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ];

            if ($googleAvatarUrl) {
                $googleAttributes['google_avatar_url'] = $googleAvatarUrl;
            }

            $user->forceFill($googleAttributes)->save();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $notificationSent = $this->sendGoogleLoginNotification($user);

        if ($expectsJson) {
            $token = $user->createToken('Google sign-in', ['*'], now()->addDays(30));

            return response()->json([
                'message' => 'Signed in with Google successfully.',
                'access_token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'notification_sent' => $notificationSent,
                'user' => AccountResource::make($user)->resolve($request),
            ])->header('Cache-Control', 'no-store, private');
        }

        return $user->can('access-admin')
            ? redirect()->route('admin.index')
            : redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function projects(): JsonResponse
    {
        return response()->json([
            'data' => Auth::user()->projects()->withCount(['tasks', 'tasks as open_tasks_count' => fn ($query) => $query->where('done', false)])->get(),
        ]);
    }

    public function storeProject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $project = Auth::user()->projects()->create($data);

        return response()->json(['data' => $project], 201);
    }

    public function showProject(Project $project): JsonResponse
    {
        $this->authorizeProject($project);

        return response()->json([
            'data' => $project->loadCount([
                'tasks',
                'tasks as open_tasks_count' => fn ($query) => $query->where('done', false),
            ]),
        ]);
    }

    public function updateProject(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $project->update($request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'color' => ['sometimes', 'required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]));

        return response()->json(['data' => $project->fresh()]);
    }

    public function destroyProject(Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $project->delete();

        return response()->json(['message' => 'Project deleted.']);
    }

    public function tasks(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $tasks = $project->tasks()->latest();
        $this->applyDueFilter($tasks, $request->query('due'));

        return response()->json(['data' => $tasks->get()]);
    }

    public function allTasks(Request $request): JsonResponse
    {
        $tasks = Task::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', Auth::id()))
            ->latest();
        $this->applyDueFilter($tasks, $request->query('due'));

        return response()->json(['data' => $tasks->get()]);
    }

    public function workspace(Request $request): JsonResponse
    {
        $user = Auth::user();
        $projects = $user->projects()
            ->withCount([
                'tasks',
                'tasks as open_tasks_count' => fn ($query) => $query->where('done', false),
            ])
            ->get();
        $tasks = Task::query()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->get();

        return response()->json([
            'data' => [
                'user' => UserResource::make($user)->resolve($request),
                'projects' => $projects,
                'tasks' => $tasks,
            ],
        ]);
    }

    public function storeTask(Request $request, Project $project): JsonResponse
    {
        $this->authorizeProject($project);
        $data = $this->validatedTask($request);
        unset($data['project_id']);
        $task = $project->tasks()->create($data);

        return response()->json(['data' => $task], 201);
    }

    public function showTask(Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($task->project_id === $project->id, 404);

        return response()->json(['data' => $task]);
    }

    public function updateTask(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($task->project_id === $project->id, 404);
        $task->update($this->validatedTask($request, partial: true));

        return response()->json(['data' => $task->fresh()]);
    }

    public function destroyTask(Project $project, Task $task): JsonResponse
    {
        $this->authorizeProject($project);
        abort_unless($task->project_id === $project->id, 404);
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    private function authorizeProject(Project $project): void
    {
        abort_unless($project->user_id === Auth::id(), 404);
    }

    private function trustedGoogleAvatarUrl(?string $avatarUrl): ?string
    {
        if (! $avatarUrl) {
            return null;
        }

        $url = parse_url($avatarUrl);
        $host = strtolower($url['host'] ?? '');

        if (
            ($url['scheme'] ?? null) !== 'https'
            || ! ($host === 'googleusercontent.com' || str_ends_with($host, '.googleusercontent.com'))
        ) {
            return null;
        }

        return $avatarUrl;
    }

    private function googleAuthError(
        Request $request,
        string $message,
        int $status,
        bool $expectsJson,
    ): JsonResponse|RedirectResponse {
        return $expectsJson
            ? response()->json(['message' => $message], $status)
            : redirect()->route('login')->withErrors(['google' => $message]);
    }

    private function sendGoogleLoginNotification(User $user): bool
    {
        $mailer = config('mail.default');
        $mailerConfig = config("mail.mailers.{$mailer}", []);

        if (
            ($mailerConfig['transport'] ?? null) === 'smtp'
            && strtolower($mailerConfig['host'] ?? '') === 'smtp.gmail.com'
            && (! filled($mailerConfig['username'] ?? null) || ! filled($mailerConfig['password'] ?? null))
        ) {
            Log::warning('Google sign-in notification was skipped because Gmail SMTP credentials are missing. Configure MAIL_USERNAME and MAIL_PASSWORD with a Gmail address and Google App Password.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        try {
            Mail::to($user->email)->send(new GoogleLoginNotificationMail(
                $user->name,
                $user->email,
                now()->toDayDateTimeString(),
            ));

            return config('mail.default') !== 'log';
        } catch (TransportExceptionInterface $exception) {
            Log::warning('Google sign-in notification could not be delivered.', [
                'user_id' => $user->id,
                'exception' => $exception::class,
            ]);

            return false;
        }
    }

    private function validatedTask(Request $request, bool $partial = false): array
    {
        return $request->validate([
            'title' => [$partial ? 'sometimes' : 'required', 'string', 'max:160'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'required', Rule::in(['low', 'medium', 'high'])],
            'done' => ['sometimes', 'boolean'],
            'project_id' => ['sometimes', 'required', Rule::exists('projects', 'id')->where('user_id', Auth::id())],
        ]);
    }

    private function applyDueFilter($query, ?string $filter): void
    {
        if ($filter === 'today') {
            $query->whereDate('due_date', today())->where('done', false);
        } elseif ($filter === 'overdue') {
            $query->whereDate('due_date', '<', today())->where('done', false);
        } elseif ($filter === 'done') {
            $query->where('done', true);
        } elseif ($filter === 'all' || $filter === null) {
            return;
        } else {
            abort(422, 'Unsupported task filter.');
        }
    }
}
