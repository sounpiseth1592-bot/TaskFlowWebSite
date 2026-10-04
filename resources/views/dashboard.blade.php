<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f8f8fb">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="{{ asset('taskflow-icon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('taskflow-icon.svg') }}">
    <title>TaskFlow · Your day, in flow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-page"
    data-user-name="{{ $user->name }}"
    data-user-email="{{ $user->email }}"
    data-profile-photo-url="{{ $user->profilePhotoUrl() }}"
    data-avatar-action="{{ $user->profileAvatarSelection() }}"
    data-avatar-preset="{{ $user->avatar_preset }}"
    data-workspace-filter="{{ $workspaceFilter }}"
    data-workspace-project-id="{{ $workspaceProject?->id }}"
    data-project-url-template="{{ $projectUrlTemplate }}">
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ route('workspace.index') }}"><span class="brand-mark">t</span> taskflow</a>
            <button class="button primary new-task-side" id="new-task-side"><span></span> New task <kbd>N</kbd></button>
            <p class="nav-label">WORKSPACE</p>
            <nav class="main-nav" aria-label="Task filters">
                <a class="nav-item {{ $workspaceFilter === 'all' && ! $workspaceProject ? 'active' : '' }}" href="{{ route('workspace.index') }}" data-filter="all"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg></span> All tasks <span class="nav-count" id="count-all">0</span></a>
                <a class="nav-item {{ $workspaceFilter === 'today' && ! $workspaceProject ? 'active' : '' }}" href="{{ route('workspace.today') }}" data-filter="today"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="M12 7v5l3 2"/></svg></span> Today <span class="nav-count" id="count-today">0</span></a>
                <a class="nav-item {{ $workspaceFilter === 'overdue' && ! $workspaceProject ? 'active' : '' }}" href="{{ route('workspace.overdue') }}" data-filter="overdue"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4m8-4v4M4 10h16m-8 3v3m0-3h.01"/></svg></span> Overdue <span class="nav-count" id="count-overdue">0</span></a>
                <a class="nav-item {{ $workspaceFilter === 'done' && ! $workspaceProject ? 'active' : '' }}" href="{{ route('workspace.completed') }}" data-filter="done"><span class="nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8"/><path d="m8.5 12 2.2 2.2 4.8-4.8"/></svg></span> Completed</a>
            </nav>
            <div class="project-heading"><p class="nav-label">YOUR PROJECTS</p><button class="icon-button" id="add-project" aria-label="Add project">+</button></div>
            <nav class="project-nav" id="project-nav" aria-label="Projects"></nav>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" id="menu-toggle" aria-label="Toggle menu">☰</button>
                <div class="breadcrumbs"><span>Workspace</span><span class="crumb-separator">/</span><strong id="current-section">All tasks</strong></div>
                <div class="topbar-actions">
                    <span class="sync-status" id="sync-status"><i></i> All changes saved</span>
                    <button class="icon-button theme-toggle" id="theme-button" type="button" data-theme-toggle data-dark-icon="{{ asset('images/icons/dark_mode.png') }}" data-light-icon="{{ asset('images/icons/lightmode.png') }}" aria-label="Switch to dark mode" title="Switch to dark mode">
                        <img src="{{ asset('images/icons/dark_mode.png') }}" alt="" aria-hidden="true">
                    </button>
                    <div class="account-menu">
                        <button class="account-menu-trigger" id="account-menu-button" type="button" aria-label="Open account menu" aria-haspopup="true" aria-expanded="false" aria-controls="account-menu">
                            <span class="avatar" id="user-avatar"><span id="user-avatar-initial">A</span><img id="user-avatar-image" class="hidden" alt="" referrerpolicy="no-referrer"></span>
                            <span class="account-menu-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div class="account-menu-panel hidden" id="account-menu" role="menu">
                            <div class="account-menu-identity"><strong id="user-name"></strong><small id="user-email"></small></div>
                            <a role="menuitem" href="{{ route('profile.show') }}"><span aria-hidden="true">◎</span> User profile</a>
                            <a role="menuitem" href="{{ route('profile.edit') }}"><span aria-hidden="true">⚙</span> Settings</a>
                        </div>
                    </div>
                </div>
            </header>
            <section class="content-area">
                <div class="page-heading">
                    <div><p class="eyebrow" id="date-label"></p><h1 id="page-title">All tasks</h1><p class="muted" id="page-subtitle">A clear mind starts with a clear plan.</p></div>
                    <button class="button primary" id="new-task"><span></span> Add a task</button>
                </div>
                <div class="overview-strip" id="overview-strip">
                    <div class="overview-card"><span class="overview-icon purple"><img src="{{ $workspaceIcons['projects'] }}" alt="" width="36" height="36"></span><div><small>Projects</small><strong id="overview-projects">0</strong></div></div>
                    <div class="overview-card"><span class="overview-icon purple"><img src="{{ $workspaceIcons['tasks'] }}" alt="" width="36" height="36"></span><div><small>All tasks</small><strong id="overview-all">0</strong></div></div>
                    <div class="overview-card"><span class="overview-icon purple"><img src="{{ $workspaceIcons['in_progress'] }}" alt="" width="36" height="36"></span><div><small>In progress</small><strong id="overview-open">0</strong></div></div>
                    <div class="overview-card"><span class="overview-icon purple"><img src="{{ $workspaceIcons['due_today'] }}" alt="" width="36" height="36"></span><div><small>Due today</small><strong id="overview-today">0 tasks</strong></div></div>
                    <div class="overview-card"><span class="overview-icon amber"><img src="{{ $workspaceIcons['overdue'] }}" alt="" width="36" height="36"></span><div><small>Past due</small><strong id="overview-overdue">0 tasks</strong></div></div>
                    <div class="overview-card"><span class="overview-icon green"><img src="{{ $workspaceIcons['completed'] }}" alt="" width="36" height="36"></span><div><small>Completed</small><strong id="overview-done">0 tasks</strong></div></div>
                </div>
                <section class="workspace-dashboard hidden" id="workspace-dashboard" aria-labelledby="workspace-dashboard-title">
                    <div class="workspace-dashboard-heading">
                        <div><h2 id="workspace-dashboard-title">Project progress</h2><p>See every project and its task progress at a glance.</p></div>
                        <button class="button secondary" type="button" id="dashboard-add-project"><span></span> New project</button>
                    </div>
                    <div class="workspace-project-grid" id="workspace-project-grid" aria-live="polite"></div>
                </section>
                <div class="list-toolbar"><div class="list-title"><h2 id="list-heading">Your tasks</h2><span class="task-total" id="visible-count">0</span></div><button class="sort-button" id="sort-button">↕ <span>Due date</span></button></div>
                <div class="task-list" id="task-list"></div>
            </section>
        </main>
    </div>
    <div class="modal-backdrop hidden" id="task-modal">
        <section class="modal-card" role="dialog" aria-modal="true" aria-labelledby="task-modal-title">
            <div class="modal-heading"><div><p class="eyebrow">YOUR TASK</p><h2 id="task-modal-title">Create a task</h2></div><button class="icon-button close-modal" aria-label="Close">×</button></div>
            <form id="task-form">
                <input type="hidden" name="task_id">
                <label>What needs to get done?<input name="title" maxlength="160" required placeholder="e.g. Review the project proposal"></label>
                <label>Notes <span class="optional">optional</span><textarea name="notes" rows="3" maxlength="10000" placeholder="Add a little context…"></textarea></label>
                <div class="form-row"><label>Project<select name="project_id" required></select></label><label>Due date<input type="datetime-local" name="due_date"></label></div>
                <div class="form-row"><label>Priority<select name="priority"><option value="low">Low</option><option value="medium" selected>Medium</option><option value="high">High</option></select></label><label class="modal-check"><input type="checkbox" name="done"> Mark as complete</label></div>
                <div class="modal-actions"><button type="button" class="button danger-link hidden" id="delete-task">Delete task</button><span></span><button type="button" class="button secondary close-modal">Cancel</button><button type="submit" class="button primary">Save task</button></div>
            </form>
        </section>
    </div>
    <div class="modal-backdrop hidden" id="project-modal">
        <section class="modal-card compact" role="dialog" aria-modal="true" aria-labelledby="project-modal-title">
            <div class="modal-heading"><div><p class="eyebrow">YOUR WORKSPACE</p><h2 id="project-modal-title">New project</h2></div><button class="icon-button close-modal" aria-label="Close">×</button></div>
            <form id="project-form"><input type="hidden" name="project_id"><label>Project name<input name="name" maxlength="80" required placeholder="e.g. Product launch"></label><label>Project color<input class="color-input" type="color" name="color" value="#8977f8"></label><div class="modal-actions"><button type="button" class="button danger-link hidden" id="delete-project">Delete</button><span></span><button type="button" class="button secondary close-modal">Cancel</button><button type="submit" class="button primary">Save project</button></div></form>
        </section>
    </div>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
