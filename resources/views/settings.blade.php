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
            <a class="button primary new-task-side settings-back-link" href="{{ route('home') }}"><span>←</span> Back to tasks</a>
            <p class="nav-label">WORKSPACE</p>
            <nav class="main-nav" aria-label="Workspace navigation">
                <a class="nav-item" href="{{ route('home') }}"><span class="nav-icon">▦</span> All tasks</a>
            </nav>
            <div class="sidebar-bottom">
                <a class="nav-item active" href="{{ route('profile.edit') }}" aria-current="page"><span class="nav-icon">⚙</span> Settings</a>
                <div class="account">
                    <span class="avatar" id="settings-sidebar-avatar">
                        <span id="settings-sidebar-initial" class="{{ $user->profilePhotoUrl() ? 'hidden' : '' }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <img id="settings-sidebar-image" class="{{ $user->profilePhotoUrl() ? '' : 'hidden' }}" src="{{ $user->profilePhotoUrl() }}" alt="">
                    </span>
                    <span class="account-copy"><strong id="settings-sidebar-name">{{ $user->name }}</strong><small>{{ $user->email }}</small></span>
                </div>
            </div>
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" id="settings-menu-toggle" aria-label="Toggle menu">☰</button>
                <div class="breadcrumbs"><a href="{{ route('home') }}">Workspace</a><span class="crumb-separator">/</span><strong>Settings</strong></div>
                <div class="topbar-actions"><span class="sync-status"><i></i> Your account</span></div>
            </header>
            <section class="content-area settings-content">
                <div class="page-heading">
                    <div><p class="eyebrow">YOUR WORKSPACE</p><h1>Settings</h1><p class="muted">Personalize your account and how TaskFlow works for you.</p></div>
                </div>

                <section class="settings-section" aria-labelledby="profile-heading">
                    <div class="settings-section-heading"><span class="settings-section-icon">◎</span><div><h2 id="profile-heading">Profile & account</h2><p>Update your name and profile photo.</p></div></div>
                    <form class="settings-panel settings-profile-form" id="profile-form" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" data-google-avatar-url="{{ $user->google_avatar_url }}" data-avatar-action="{{ $user->profileAvatarSelection() }}" data-avatar-preset="{{ $user->avatar_preset }}">
                        @csrf
                        <div class="profile-summary">
                            <span class="profile-preview" id="profile-preview"><span id="profile-preview-initial" class="{{ $user->profilePhotoUrl() ? 'hidden' : '' }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><img id="profile-preview-image" class="{{ $user->profilePhotoUrl() ? '' : 'hidden' }}" src="{{ $user->profilePhotoUrl() }}" alt=""></span>
                            <div><strong id="profile-summary-name">{{ $user->name }}</strong><span>Profile photo</span></div>
                            <label class="button secondary profile-upload-button" id="profile-upload-trigger" for="profile-avatar-file">Upload photo</label>
                            <input class="hidden" type="file" id="profile-avatar-file" name="avatar_file" accept="image/png,image/jpeg,image/webp">
                        </div>
                        <label class="profile-field">Full name<input type="text" name="name" maxlength="100" value="{{ $user->name }}" autocomplete="name" required></label>
                        <label class="profile-field">Account email<input type="email" value="{{ $user->email }}" readonly><small>Your sign-in email cannot be changed here.</small></label>
                        <fieldset class="profile-avatar-picker">
                            <legend>Choose an avatar</legend>
                            <input type="hidden" name="avatar_action" id="profile-avatar-action" value="{{ $user->profileAvatarSelection() }}" disabled>
                            <input type="hidden" name="avatar_preset" id="profile-avatar-preset" value="{{ $user->avatar_preset }}">
                            <div class="profile-avatar-options" id="profile-avatar-options">
                                @foreach ($avatarPresets as $preset)
                                    <button class="profile-avatar-option {{ $user->avatar_preset === $preset ? 'is-selected' : '' }}" type="button" data-avatar-preset="{{ $preset }}" aria-label="Choose avatar {{ str_replace('people', '', $preset) }}" aria-pressed="{{ $user->avatar_preset === $preset ? 'true' : 'false' }}">
                                        <img src="{{ asset('images/icons/'.$preset.'.png') }}" alt="" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                        <div class="profile-avatar-actions">
                            @if ($user->google_avatar_url)
                                <button class="button secondary {{ $user->profileAvatarSelection() === 'google' ? 'is-selected' : '' }}" type="button" id="use-google-avatar" data-profile-avatar-choice="google" aria-pressed="{{ $user->profileAvatarSelection() === 'google' ? 'true' : 'false' }}">Use Google photo</button>
                            @endif
                            <button class="button secondary {{ $user->profileAvatarSelection() === 'initials' ? 'is-selected' : '' }}" type="button" id="use-initials-avatar" data-profile-avatar-choice="initials" aria-pressed="{{ $user->profileAvatarSelection() === 'initials' ? 'true' : 'false' }}">Use initials</button>
                        </div>
                        <p class="profile-help">Choose a portrait, use your Google profile photo, or upload a JPG, PNG, or WebP image up to 2 MB.</p>
                        <div class="settings-form-actions"><span id="profile-save-status" role="status">Changes are saved when you choose Save profile.</span><button type="submit" class="button primary" id="save-profile">Save profile</button></div>
                    </form>
                </section>

                <section class="settings-section" aria-labelledby="preferences-heading">
                    <div class="settings-section-heading"><span class="settings-section-icon">☼</span><div><h2 id="preferences-heading">Preferences</h2><p>Make TaskFlow feel comfortable to use.</p></div></div>
                    <div class="settings-panel settings-options">
                        <div class="settings-option"><div><strong>Appearance</strong><p>Switch between light and dark mode on this device.</p></div><button class="button secondary" id="settings-theme" type="button">Dark mode</button></div>
                        <div class="settings-option"><div><strong>Due date reminders</strong><p>Allow browser notifications for tasks that are coming due.</p></div><button class="button secondary" id="enable-reminders" type="button">Enable notifications</button></div>
                    </div>
                </section>

                <section class="settings-section" aria-labelledby="about-heading">
                    <div class="settings-section-heading"><span class="settings-section-icon">✦</span><div><h2 id="about-heading">About TaskFlow</h2><p>A calmer place to organize your day.</p></div></div>
                    <div class="settings-panel settings-about"><div><strong>TaskFlow</strong><p>Your plans, priorities, and progress in one simple workspace.</p></div><a class="button secondary" href="{{ route('home') }}">Back to tasks</a></div>
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
