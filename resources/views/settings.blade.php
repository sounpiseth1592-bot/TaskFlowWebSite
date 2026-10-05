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
    <title>Settings · TaskFlow</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="settings-page" data-user-name="{{ $user->name }}" data-user-email="{{ $user->email }}" data-profile-photo-url="{{ $user->profilePhotoUrl() }}">
    <div class="app-layout">
        <aside class="sidebar">
            <a class="brand" href="{{ route('home') }}"><span class="brand-mark">t</span> taskflow</a>
            <a class="button primary new-task-side settings-back-link" href="{{ route('home') }}"><span></span> Back to tasks</a>
            <p class="nav-label">WORKSPACE</p>
            <nav class="main-nav" aria-label="Workspace navigation">
                <a class="nav-item" href="{{ route('home') }}"><span class="nav-icon">▦</span> All tasks</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" id="settings-menu-toggle" aria-label="Toggle menu">☰</button>
                <div class="breadcrumbs"><a href="{{ route('home') }}">Workspace</a><span class="crumb-separator">/</span><strong>Settings</strong></div>
                <div class="topbar-actions">
                    <span class="sync-status"><i></i> Your account</span>
                    <button class="icon-button theme-toggle" id="settings-theme-toggle" type="button" data-theme-toggle data-dark-icon="{{ asset('images/icons/dark_mode.png') }}" data-light-icon="{{ asset('images/icons/lightmode.png') }}" aria-label="Switch to dark mode" title="Switch to dark mode">
                        <img src="{{ asset('images/icons/dark_mode.png') }}" alt="" aria-hidden="true">
                    </button>
                    <div class="account-menu">
                        <button class="account-menu-trigger" id="account-menu-button" type="button" aria-label="Open account menu" aria-haspopup="true" aria-expanded="false" aria-controls="account-menu">
                            <span class="avatar" id="user-avatar">
                                <span id="user-avatar-initial" class="{{ $user->profilePhotoUrl() ? 'hidden' : '' }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                <img id="user-avatar-image" class="{{ $user->profilePhotoUrl() ? '' : 'hidden' }}" src="{{ $user->profilePhotoUrl() }}" alt="" referrerpolicy="no-referrer">
                            </span>
                            <span class="account-menu-chevron" aria-hidden="true">⌄</span>
                        </button>
                        <div class="account-menu-panel hidden" id="account-menu" role="menu">
                            <div class="account-menu-identity"><strong id="user-name">{{ $user->name }}</strong><small id="user-email">{{ $user->email }}</small></div>
                            <a role="menuitem" href="{{ route('profile.show') }}"><span aria-hidden="true">◎</span> User profile</a>
                            <a role="menuitem" href="{{ route('profile.edit') }}" aria-current="page"><span aria-hidden="true">⚙</span> Settings</a>
                        </div>
                    </div>
                </div>
            </header>
            <section class="content-area settings-content">
                <div class="page-heading">
                    <div><p class="eyebrow">YOUR WORKSPACE</p><h1>Settings</h1><p class="muted">Manage your preferences, API access, and account security.</p></div>
                </div>

                <section class="settings-section" aria-labelledby="api-token-heading">
                    <div class="settings-section-heading"><span class="settings-section-icon">⌘</span><div><h2 id="api-token-heading">API access token</h2><p>Create a personal token for API requests as {{ $user->email }}.</p></div></div>
                    <div class="settings-panel settings-api-token">
                        <p>Generate a 30-day bearer token for this account. It is shown only once; keep it private and use it in the Authorization header.</p>
                        @isset($apiToken)
                            <label class="profile-field" for="api-token-value">Copy your new token
                                <textarea id="api-token-value" class="api-token-value" rows="3" readonly>{{ $apiToken }}</textarea>
                            </label>
                            <p class="profile-help">This token will not be shown again. Generate a new one if you lose it; doing so revokes the previous token created here.</p>
                        @endisset
                        <form method="POST" action="{{ route('profile.api-token') }}">
                            @csrf
                            <button class="button primary" type="submit">{{ isset($apiToken) ? 'Replace API token' : 'Generate API token' }}</button>
                        </form>
                    </div>
                </section>

                <section class="settings-section" aria-labelledby="preferences-heading">
                    <div class="settings-section-heading"><span class="settings-section-icon">☼</span><div><h2 id="preferences-heading">Preferences</h2><p>Make TaskFlow feel comfortable to use.</p></div></div>
                    <div class="settings-panel settings-options">
                        <div class="settings-option"><div><strong>Appearance</strong><p>Switch between light and dark mode on this device.</p></div><button class="button secondary" id="settings-theme" type="button">Dark mode</button></div>
                        <div class="settings-option"><div><strong>Due date reminders</strong><p>Allow browser notifications for tasks that are coming due.</p></div><button class="button secondary" id="enable-reminders" type="button">Enable notifications</button></div>
                    </div>
                </section>

                <section class="settings-section settings-account-section" aria-labelledby="account-actions-heading">
                    <div class="settings-section-heading"><span class="settings-section-icon">↗</span><div><h2 id="account-actions-heading">Account actions</h2><p>Sign out when you are finished on this device.</p></div></div>
                    <div class="settings-panel settings-about"><div><strong>{{ $user->name }}</strong><p>{{ $user->email }}</p></div><form method="POST" action="/logout">@csrf<button class="button danger-button" type="submit">Sign out</button></form></div>
                </section>
            </section>
        </main>
    </div>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
