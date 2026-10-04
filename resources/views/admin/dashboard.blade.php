<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f8f8fb">
    <link rel="icon" type="image/svg+xml" href="{{ asset('taskflow-icon.svg') }}">
    <title>Admin dashboard · TaskFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-page">
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="brand" href="{{ route('admin.index') }}"><span class="brand-mark">t</span> taskflow</a>
            <p class="nav-label">ADMINISTRATION</p>
            <a class="nav-item active" href="#overview"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg></span> Overview</a>
            <a class="nav-item" href="#users"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="3.5"/><path d="M5 20c.8-3.5 3.1-5.2 7-5.2s6.2 1.7 7 5.2"/></svg></span> Users</a>
            <a class="nav-item" href="#projects"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3.5 7.5h7l2 2h8v9h-17z"/><path d="M3.5 7.5v-2h7l2 2"/></svg></span> Projects</a>
            <a class="nav-item" href="#tasks"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M9 6h11M9 12h11M9 18h11"/><path d="m3.5 6 .8.8L5.8 5m-2.3 7 .8.8 1.5-1.8m-2.3 7 .8.8 1.5-1.8"/></svg></span> Tasks</a>
            <div class="admin-sidebar-bottom">
                <a class="nav-item" href="{{ route('home') }}"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M14 4h6v6m-.5-5.5L10 14"/><path d="M18 13v6H4V5h6"/></svg></span> Workspace</a>
                <form method="POST" action="{{ url('/logout') }}">
                    @csrf
                    <button class="nav-item admin-signout" type="submit"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 4H5v16h5m5-4 4-4-4-4m4 4H9"/></svg></span> Sign out</button>
                </form>
            </div>
        </aside>

        <main class="admin-main">
            <header class="admin-topbar">
                <div class="breadcrumbs"><span>TaskFlow</span><span class="crumb-separator">/</span><strong>Admin dashboard</strong></div>
                <span class="admin-identity"><span class="admin-status-dot"></span> {{ auth()->user()->email }}</span>
            </header>

            <div class="admin-content">
                <section class="admin-heading" id="overview">
                    <div><p class="eyebrow">CONTROL CENTER</p><h1>Admin dashboard</h1><p class="muted">Manage accounts and keep every workspace organized.</p></div>
                    <span class="admin-access-badge">Administrator access</span>
                </section>

                @if (session('status'))
                    <div class="admin-flash" role="status">{{ session('status') }}</div>
                @endif

                @isset($apiToken)
                    <section class="admin-token-notice" aria-labelledby="admin-token-heading">
                        <div><h2 id="admin-token-heading">New API token for {{ $apiTokenUserName }}</h2><p>Copy it now. For security, this token is shown only once.</p></div>
                        <textarea class="admin-token-value" rows="2" readonly aria-label="New API token">{{ $apiToken }}</textarea>
                    </section>
                @endisset

                <section class="admin-stats" aria-label="TaskFlow totals">
                    <article class="admin-stat"><span class="overview-icon purple">◎</span><div><small>Users</small><strong>{{ number_format($stats['users']) }}</strong></div></article>
                    <article class="admin-stat"><span class="overview-icon purple">◫</span><div><small>Projects</small><strong>{{ number_format($stats['projects']) }}</strong></div></article>
                    <article class="admin-stat"><span class="overview-icon amber">☷</span><div><small>Tasks</small><strong>{{ number_format($stats['tasks']) }}</strong></div></article>
                    <article class="admin-stat"><span class="overview-icon green">✓</span><div><small>Completed tasks</small><strong>{{ number_format($stats['completed']) }}</strong></div></article>
                </section>

                <section class="admin-section" id="users">
                    <div class="admin-section-heading"><div><p class="eyebrow">ACCOUNTS</p><h2>User management</h2><p>View, update, remove accounts, or issue a 30-day API token.</p></div><span class="admin-count">{{ $users->count() }} recent</span></div>
                    <div class="admin-record-list">
                        @forelse ($users as $user)
                            <article class="admin-record">
                                <div class="admin-record-title"><span class="admin-avatar">
                                    <span class="{{ $user->profilePhotoUrl() ? 'hidden' : '' }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    @if ($user->profilePhotoUrl())
                                        <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }} profile avatar" referrerpolicy="no-referrer">
                                    @endif
                                </span><div><strong>{{ $user->name }}</strong><small>{{ $user->projects_count }} projects · {{ $user->tokens_count }} tokens</small></div>@if (strcasecmp($user->email, (string) config('admin.email')) === 0)<span class="admin-role-pill">Admin</span>@endif</div>
                                <form class="admin-edit-grid admin-user-form" method="POST" action="{{ route('admin.users.update', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label>Name<input name="name" value="{{ $user->name }}" maxlength="100" required></label>
                                    <label>Email<input name="email" type="email" value="{{ $user->email }}" {{ strcasecmp($user->email, (string) config('admin.email')) === 0 ? 'readonly' : '' }} required></label>
                                    <button class="button secondary" type="submit">Save user</button>
                                </form>
                                <div class="admin-record-actions">
                                    <form method="POST" action="{{ route('admin.users.token', $user) }}">
                                        @csrf
                                        <button class="button secondary" type="submit">Generate token</button>
                                    </form>
                                    @if (strcasecmp($user->email, (string) config('admin.email')) !== 0)
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user and all of their projects and tasks?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="button danger-button" type="submit">Delete user</button>
                                        </form>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="admin-empty">No user accounts are available.</p>
                        @endforelse
                    </div>
                </section>

                <section class="admin-section" id="projects">
                    <div class="admin-section-heading"><div><p class="eyebrow">WORKSPACES</p><h2>Project management</h2><p>Rename projects, update their color or owner, and remove projects.</p></div><span class="admin-count">{{ $projects->count() }} recent</span></div>
                    <div class="admin-record-list">
                        @forelse ($projects as $project)
                            <article class="admin-record">
                                <div class="admin-record-title"><span class="admin-project-dot" style="--project-color: {{ $project->color }}"></span><div><strong>{{ $project->name }}</strong><small>{{ $project->tasks_count }} tasks · {{ $project->user?->email ?? 'No owner' }}</small></div></div>
                                <form class="admin-edit-grid admin-project-form" method="POST" action="{{ route('admin.projects.update', $project) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label>Project name<input name="name" value="{{ $project->name }}" maxlength="80" required></label>
                                    <label>Project color<input name="color" type="color" value="{{ $project->color }}" required></label>
                                    <label>Owner<select name="user_id" required>
                                        @unless ($users->contains('id', $project->user_id))
                                            <option value="{{ $project->user_id }}" selected>{{ $project->user?->name }} · {{ $project->user?->email }}</option>
                                        @endunless
                                        @foreach ($users as $user)<option value="{{ $user->id }}" @selected($project->user_id === $user->id)>{{ $user->name }} · {{ $user->email }}</option>@endforeach
                                    </select></label>
                                    <button class="button secondary" type="submit">Save project</button>
                                </form>
                                <div class="admin-record-actions"><form method="POST" action="{{ route('admin.projects.destroy', $project) }}" onsubmit="return confirm('Delete this project and all of its tasks?')">@csrf @method('DELETE')<button class="button danger-button" type="submit">Delete project</button></form></div>
                            </article>
                        @empty
                            <p class="admin-empty">No projects have been created yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="admin-section" id="tasks">
                    <div class="admin-section-heading"><div><p class="eyebrow">WORK TO DO</p><h2>Task management</h2><p>Edit task details and status, or remove a task.</p></div><span class="admin-count">{{ $tasks->count() }} recent</span></div>
                    <div class="admin-record-list">
                        @forelse ($tasks as $task)
                            <article class="admin-record">
                                <div class="admin-record-title"><span class="admin-task-state {{ $task->done ? 'is-done' : '' }}">{{ $task->done ? '✓' : '•' }}</span><div><strong>{{ $task->title }}</strong><small>{{ $task->project?->name ?? 'No project' }} · {{ $task->project?->user?->name ?? 'No owner' }}</small></div></div>
                                <form class="admin-edit-grid admin-task-form" method="POST" action="{{ route('admin.tasks.update', $task) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label class="admin-task-title-field">Task title<input name="title" value="{{ $task->title }}" maxlength="160" required></label>
                                    <label>Project<select name="project_id" required>
                                        @unless ($projects->contains('id', $task->project_id))
                                            <option value="{{ $task->project_id }}" selected>{{ $task->project?->name }} · {{ $task->project?->user?->name }}</option>
                                        @endunless
                                        @foreach ($projects as $project)<option value="{{ $project->id }}" @selected($task->project_id === $project->id)>{{ $project->name }} · {{ $project->user?->name }}</option>@endforeach
                                    </select></label>
                                    <label>Due date<input name="due_date" type="datetime-local" value="{{ $task->due_date?->format('Y-m-d\TH:i') }}"></label>
                                    <label>Priority<select name="priority">@foreach (['low', 'medium', 'high'] as $priority)<option value="{{ $priority }}" @selected($task->priority === $priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
                                    <label>Status<select name="done"><option value="0" @selected(! $task->done)>Open</option><option value="1" @selected($task->done)>Completed</option></select></label>
                                    <label class="admin-task-notes-field">Notes<textarea name="notes" rows="2" maxlength="10000">{{ $task->notes }}</textarea></label>
                                    <button class="button secondary" type="submit">Save task</button>
                                </form>
                                <div class="admin-record-actions"><form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task?')">@csrf @method('DELETE')<button class="button danger-button" type="submit">Delete task</button></form></div>
                            </article>
                        @empty
                            <p class="admin-empty">No tasks have been created yet.</p>
                        @endforelse
                    </div>
                </section>
                <p class="admin-limit-note">Showing up to 100 most recently updated records in each section.</p>
            </div>
        </main>
    </div>
</body>
</html>
