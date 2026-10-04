<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'data' => $this->dashboardApiData($request),
            ]);
        }

        return $this->dashboardView();
    }

    public function updateUser(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $emailRules = strcasecmp($user->email, (string) config('admin.email')) === 0
            ? ['required', 'email', Rule::in([$user->email])]
            : [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($user): void {
                    if (
                        is_string($value)
                        && strcasecmp($value, (string) config('admin.email')) === 0
                        && strcasecmp($user->email, (string) config('admin.email')) !== 0
                    ) {
                        $fail('This email is reserved for the configured administrator account.');
                    }
                },
            ];

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => $emailRules,
        ]);

        $user->update($data);

        if ($request->expectsJson()) {
            return response()->json([
                'data' => UserResource::make($user->fresh())->resolve($request),
            ]);
        }

        return back()->with('status', 'User account updated.');
    }

    public function destroyUser(Request $request, User $user): RedirectResponse|JsonResponse
    {
        abort_if(strcasecmp($user->email, (string) config('admin.email')) === 0, 403);

        $user->tokens()->delete();
        $user->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'User account and its projects were deleted.']);
        }

        return back()->with('status', 'User account and its projects were deleted.');
    }

    public function createToken(Request $request, User $user): Response|JsonResponse
    {
        $tokenName = 'Admin generated token';
        $newToken = $user->createToken($tokenName, ['*'], now()->addDays(30));

        $user->tokens()
            ->where('name', $tokenName)
            ->where('id', '!=', $newToken->accessToken->getKey())
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => [
                    'access_token' => $newToken->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $newToken->accessToken->expires_at?->toISOString(),
                    'user' => UserResource::make($user)->resolve($request),
                ],
            ])->header('Cache-Control', 'no-store, private');
        }

        return response()->view('admin.dashboard', [
            ...$this->dashboardData(),
            'apiToken' => $newToken->plainTextToken,
            'apiTokenUserName' => $user->name,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function updateProject(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $project->update($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]));

        if ($request->expectsJson()) {
            $project = $project->fresh()->load([
                'user:id,name,email,avatar_preset,avatar_path,google_avatar_url',
            ])->loadCount('tasks');

            return response()->json([
                'data' => $this->projectApiData($project),
            ]);
        }

        return back()->with('status', 'Project updated.');
    }

    public function destroyProject(Request $request, Project $project): RedirectResponse|JsonResponse
    {
        $project->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Project and its tasks were deleted.']);
        }

        return back()->with('status', 'Project and its tasks were deleted.');
    }

    public function updateTask(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $task->update($request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            'done' => ['required', 'boolean'],
        ]));

        if ($request->expectsJson()) {
            $task = $task->fresh()->load([
                'project:id,name,user_id',
                'project.user:id,name,avatar_preset,avatar_path,google_avatar_url',
            ]);

            return response()->json([
                'data' => $this->taskApiData($task),
            ]);
        }

        return back()->with('status', 'Task updated.');
    }

    public function destroyTask(Request $request, Task $task): RedirectResponse|JsonResponse
    {
        $task->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Task deleted.']);
        }

        return back()->with('status', 'Task deleted.');
    }

    private function dashboardView(): View
    {
        return view('admin.dashboard', $this->dashboardData());
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardData(): array
    {
        return [
            'users' => User::withCount(['projects', 'tokens'])->latest()->limit(100)->get(),
            'projects' => Project::with([
                'user:id,name,email,avatar_preset,avatar_path,google_avatar_url',
            ])
                ->withCount('tasks')
                ->latest()
                ->limit(100)
                ->get(),
            'tasks' => Task::with([
                'project:id,name,user_id',
                'project.user:id,name,avatar_preset,avatar_path,google_avatar_url',
            ])
                ->latest()
                ->limit(100)
                ->get(),
            'stats' => [
                'users' => User::count(),
                'projects' => Project::count(),
                'tasks' => Task::count(),
                'completed' => Task::where('done', true)->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     stats: array<string, int>,
     *     users: array<int, array<string, mixed>>,
     *     projects: array<int, array<string, mixed>>,
     *     tasks: array<int, array<string, mixed>>
     * }
     */
    private function dashboardApiData(Request $request): array
    {
        $data = $this->dashboardData();

        return [
            'stats' => $data['stats'],
            'users' => $data['users']
                ->map(fn (User $user): array => [
                    ...UserResource::make($user)->resolve($request),
                    'projects_count' => $user->projects_count,
                    'tokens_count' => $user->tokens_count,
                ])
                ->all(),
            'projects' => $data['projects']
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'color' => $project->color,
                    'tasks_count' => $project->tasks_count,
                    'created_at' => $project->created_at?->toISOString(),
                    'updated_at' => $project->updated_at?->toISOString(),
                    'user' => $project->user ? $this->userAvatarData($project->user, true) : null,
                ])
                ->all(),
            'tasks' => $data['tasks']
                ->map(fn (Task $task): array => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'notes' => $task->notes,
                    'due_date' => $task->due_date?->toISOString(),
                    'priority' => $task->priority,
                    'done' => $task->done,
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name,
                        'user' => $task->project->user ? $this->userAvatarData($task->project->user) : null,
                    ] : null,
                ])
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string, email?: string, avatar_url: string|null}
     */
    private function userAvatarData(User $user, bool $includeEmail = false): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            ...($includeEmail ? ['email' => $user->email] : []),
            'avatar_url' => $user->profilePhotoUrl(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function projectApiData(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'color' => $project->color,
            'tasks_count' => $project->tasks_count,
            'user' => $project->user ? $this->userAvatarData($project->user, true) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskApiData(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'notes' => $task->notes,
            'due_date' => $task->due_date?->toISOString(),
            'priority' => $task->priority,
            'done' => $task->done,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
                'user' => $task->project->user
                    ? $this->userAvatarData($task->project->user)
                    : null,
            ] : null,
        ];
    }
}
