<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f8f8fb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('taskflow-icon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('taskflow-icon.svg') }}">
    <title>{{ $mode === 'login' ? 'Sign in' : 'Create account' }} · TaskFlow</title>
    @vite(['resources/css/app.css'])
</head>
<body class="auth-page">
    <main class="auth-shell">
        <header class="auth-header">
            <a class="brand auth-brand" href="{{ route('home') }}"><span class="brand-mark">t</span> taskflow</a>
        </header>
        <section class="auth-card">
            <div class="auth-card-heading">
                <p class="eyebrow">{{ $mode === 'login' ? 'WELCOME BACK' : 'GET STARTED' }}</p>
                <a class="auth-back-link" href="{{ route('home') }}" aria-label="Back to home" title="Back to home"><span aria-hidden="true">←</span></a>
            </div>
            <h1>{{ $mode === 'login' ? 'Good to see you.' : 'Make room for what matters.' }}</h1>
            <p class="muted">{{ $mode === 'login' ? 'Sign in to pick up where you left off.' : 'Create an account and bring your plans together.' }}</p>
            <form method="POST" action="{{ $mode === 'login' ? '/auth/login' : '/auth/register' }}" class="auth-form">
                @csrf
                @if ($mode === 'register')
                    <label>Your name<input name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Alex Morgan"></label>
                @endif
                <label>Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com"></label>
                <label>Password<input type="password" name="password" required autocomplete="{{ $mode === 'login' ? 'current-password' : 'new-password' }}" placeholder="At least 8 characters"></label>
                @if ($mode === 'register')
                    <label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password" placeholder="Type it once more"></label>
                @endif
                @if ($mode === 'login')
                    <label class="remember"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                @endif
                @if ($errors->any())
                    <p class="form-error">{{ $errors->first() }}</p>
                @endif
                <button class="button primary full" type="submit">{{ $mode === 'login' ? 'Sign in' : 'Create account' }} <span>→</span></button>
            </form>
            @if (config('services.google.client_id'))
                <div class="auth-divider"><span>OR CONTINUE WITH</span></div>
                <a class="button google-button full" href="{{ route('google.redirect') }}"><span class="google-g">G</span> Continue with Google</a>
            @endif
            <p class="auth-switch">{{ $mode === 'login' ? 'New to TaskFlow?' : 'Already have an account?' }} <a href="{{ route($mode === 'login' ? 'register' : 'login') }}">{{ $mode === 'login' ? 'Create an account' : 'Sign in' }}</a></p>
        </section>
        <p class="auth-foot">A little more focus, every day.</p>
    </main>
</body>
</html>
