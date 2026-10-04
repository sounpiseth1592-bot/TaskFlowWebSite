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
    <title>Profile · TaskFlow</title>
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
        </aside>
        <main class="main-content">
            <header class="topbar">
                <button class="icon-button menu-toggle" id="settings-menu-toggle" aria-label="Toggle menu">☰</button>
                <div class="breadcrumbs"><a href="{{ route('home') }}">Workspace</a><span class="crumb-separator">/</span><strong>Profile</strong></div>
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
                            <a role="menuitem" href="{{ route('profile.show') }}" aria-current="page"><span aria-hidden="true">◎</span> User profile</a>
                            <a role="menuitem" href="{{ route('profile.edit') }}"><span aria-hidden="true">⚙</span> Settings</a>
                        </div>
                    </div>
                </div>
            </header>
            <section class="content-area settings-content">
                <div class="page-heading">
                    <div><p class="eyebrow">YOUR ACCOUNT</p><h1>Profile</h1><p class="muted">Tell people a little about yourself and personalize your account.</p></div>
                </div>

                <section class="settings-section" aria-labelledby="profile-heading">
                    <div class="settings-section-heading"><span class="settings-section-icon">◎</span><div><h2 id="profile-heading">Profile information</h2><p>Update your name, profile photo, and description.</p></div></div>
                    <form class="settings-panel settings-profile-form" id="profile-form" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data" data-google-avatar-url="{{ $user->google_avatar_url }}" data-avatar-action="{{ $user->profileAvatarSelection() }}" data-avatar-preset="{{ $user->avatar_preset }}">
                        @csrf
                        <div class="profile-summary">
                            <span class="profile-preview" id="profile-preview"><span id="profile-preview-initial" class="{{ $user->profilePhotoUrl() ? 'hidden' : '' }}">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span><img id="profile-preview-image" class="{{ $user->profilePhotoUrl() ? '' : 'hidden' }}" src="{{ $user->profilePhotoUrl() }}" alt="" referrerpolicy="no-referrer"></span>
                            <div><strong id="profile-summary-name">{{ $user->name }}</strong><span>Profile photo</span></div>
                            <label class="button secondary profile-upload-button" id="profile-upload-trigger" for="profile-avatar-file">Choose from device</label>
                            <input class="hidden" type="file" id="profile-avatar-file" name="avatar_file" accept="image/png,image/jpeg,image/webp" aria-label="Choose a profile photo from your device">
                        </div>
                        <label class="profile-field">Full name<input type="text" name="name" maxlength="100" value="{{ $user->name }}" autocomplete="name" required></label>
                        <label class="profile-field">Account email<input type="email" value="{{ $user->email }}" readonly><small>Your sign-in email cannot be changed here.</small></label>
                        <label class="profile-field">Description <span class="optional">optional</span>
                            <textarea name="description" rows="4" maxlength="500" placeholder="Write a short introduction about yourself…">{{ $user->description }}</textarea>
                            <small>Up to 500 characters. This description is visible on the public users API.</small>
                        </label>
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
                        <p class="profile-help">Choose a portrait, use your Google photo, or upload a JPG, PNG, or WebP photo from this device (up to 2 MB). Device photos upload automatically when selected. Save profile applies any other changes.</p>
                        <div class="settings-form-actions"><span id="profile-save-status" role="status">Changes are saved when you choose Save profile.</span><button type="submit" class="button primary" id="save-profile">Save profile</button></div>
                    </form>
                </section>
            </section>
        </main>
    </div>
    <div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
