<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fbfaff">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Bring your tasks, projects, and plans together with TaskFlow.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('taskflow-icon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('taskflow-icon.svg') }}">
    <title>TaskFlow · Make room for what matters</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing-page" data-landing-section="{{ $landingSection ?? '' }}">
    <header class="landing-header">
        <a class="brand landing-brand" href="{{ route('home') }}" aria-label="TaskFlow home">
            <span class="brand-mark">t</span> taskflow
        </a>
        <button class="landing-menu-toggle" id="landing-menu-toggle" type="button" aria-label="Toggle navigation" aria-controls="landing-navigation" aria-expanded="false">
            <span></span><span></span>
        </button>
        <nav class="landing-navigation" id="landing-navigation" aria-label="Main navigation">
            <a href="{{ route('landing.about') }}">About</a>
            <a href="{{ route('landing.features') }}">Features</a>
            <a href="{{ route('landing.how-it-works') }}">How it works</a>
            <a href="{{ route('landing.contact') }}">Contact</a>
        </nav>
        <div class="landing-account-links">
            @auth
                <a class="landing-sign-in" href="{{ route('profile.show') }}" aria-label="Profile for {{ auth()->user()->email }}">
                    Account · <span class="landing-account-email">{{ auth()->user()->email }}</span>
                </a>
            @else
                <a class="landing-sign-in" href="{{ route('login') }}">Sign in</a>
                <a class="button primary landing-register" href="{{ route('register') }}">Register <span aria-hidden="true"></span></a>
            @endauth
        </div>
    </header>

    <main>
        <section class="landing-hero" aria-labelledby="hero-title">
            <div class="landing-hero-copy" data-reveal>
                <p class="landing-kicker"><span class="landing-kicker-dot"></span> A calmer way to get things done</p>
                <h1 id="hero-title">Make room for <span>what matters.</span></h1>
                <p class="landing-hero-description">Your ideas, projects, and everyday tasks — together in one thoughtful space. Plan a little. Breathe a lot.</p>
                <div class="landing-hero-actions">
                    @auth
                        <a class="button primary landing-cta" href="{{ route('home') }}">Get started <span aria-hidden="true"></span></a>
                    @else
                        <a class="button primary landing-cta" href="{{ route('register') }}">Get started <span aria-hidden="true"></span></a>
                    @endauth
                    <a class="landing-text-link" href="{{ route('landing.how-it-works') }}"><span class="landing-play-icon" aria-hidden="true"></span> See how it works</a>
                </div>
                <div class="landing-proof">
                    <div class="landing-proof-avatars" aria-hidden="true"><span>A</span><span>M</span><span>J</span><span></span></div>
                    <p><strong>A little more focus,</strong><br>one day at a time.</p>
                </div>
            </div>

            <div class="landing-hero-art" data-reveal aria-label="A preview of the TaskFlow workspace">
                <div class="landing-orbit landing-orbit-one"></div>
                <div class="landing-orbit landing-orbit-two"></div>
                <div class="landing-floating-note landing-note-top"><span class="landing-note-check">✓</span><span><strong>Small steps add up</strong><small>You're right on track</small></span></div>
                <div class="landing-board">
                    <div class="landing-board-top"><span class="landing-board-dots"><i></i><i></i><i></i></span><span>my workspace</span><span class="landing-board-avatar">A</span></div>
                    <div class="landing-board-body">
                        <div class="landing-board-sidebar"><span class="landing-mini-brand"><i>t</i> taskflow</span><span class="landing-mini-nav active"><b>▦</b> My tasks</span><span class="landing-mini-nav"><b>◷</b> Today</span><span class="landing-mini-nav"><b>□</b> Projects</span><span class="landing-mini-project"><i></i> Personal</span><span class="landing-mini-project"><i></i> Studio</span></div>
                        <div class="landing-board-content">
                            <p class="landing-board-date">MONDAY, OCTOBER 12</p>
                            <h2>A fresh start.</h2>
                            <p class="landing-board-subtitle">A clear mind starts with a clear plan.</p>
                            <div class="landing-board-summary"><span><i class="summary-purple">◷</i><small>Today</small><strong>3 tasks</strong></span><span><i class="summary-green">✓</i><small>Done</small><strong>5 tasks</strong></span></div>
                            <div class="landing-board-task"><i class="landing-task-check"></i><span><strong>Plan the week</strong><small>Personal</small></span><em>Today</em></div>
                            <div class="landing-board-task"><i class="landing-task-check done">✓</i><span><strong class="task-complete">Send project update</strong><small>Studio</small></span><em class="task-done-label">Done</em></div>
                            <div class="landing-board-task"><i class="landing-task-check"></i><span><strong>Make time to reset</strong><small>Personal</small></span><em>4:30 PM</em></div>
                        </div>
                    </div>
                </div>
                <div class="landing-floating-note landing-note-bottom"><span class="landing-note-sparkle" aria-hidden="true">✦</span><span><strong>Your day, in flow</strong><small>One thing at a time</small></span></div>
            </div>
            <a class="landing-scroll-cue" href="{{ route('landing.features') }}"><span aria-hidden="true">↓</span> Scroll to explore</a>
        </section>

        <section class="landing-benefits" aria-label="TaskFlow at a glance" data-reveal>
            <p>Everything you need to move forward</p>
            <div><span>01 <strong>Capture the task</strong></span><i></i><span>02 <strong>Find your focus</strong></span><i></i><span>03 <strong>Celebrate progress</strong></span></div>
        </section>

        <section class="landing-section landing-features" id="features" aria-labelledby="features-title">
            <div class="landing-section-heading" data-reveal>
                <p class="landing-kicker">THE GOOD STUFF</p>
                <h2 id="features-title">A little structure.<br><span>A lot more breathing room.</span></h2>
                <p>Everything has its place, so you can spend less time organizing and more time doing.</p>
            </div>
            <div class="landing-feature-grid">
                <article class="landing-feature-card" data-reveal>
                    <img src="{{ asset('images/icons/focus.svg') }}" alt="" width="52" height="52" loading="lazy">
                    <h3>Find your focus</h3>
                    <p>Keep today's priorities clear, with due dates and gentle reminders when you need them.</p>
                    <a href="{{ route('landing.how-it-works') }}" aria-label="Learn how to find your focus">Explore focus <span aria-hidden="true">→</span></a>
                </article>
                <article class="landing-feature-card" data-reveal>
                    <img src="{{ asset('images/icons/projects.svg') }}" alt="" width="52" height="52" loading="lazy">
                    <h3>Keep projects together</h3>
                    <p>Group related tasks in colorful projects and see the whole picture at a glance.</p>
                    <a href="{{ route('landing.how-it-works') }}" aria-label="Learn how to keep projects together">Explore projects <span aria-hidden="true">→</span></a>
                </article>
                <article class="landing-feature-card" data-reveal>
                    <img src="{{ asset('images/icons/progress.svg') }}" alt="" width="52" height="52" loading="lazy">
                    <h3>Notice your progress</h3>
                    <p>Celebrate what you've finished and make a fresh plan for what comes next.</p>
                    <a href="{{ route('landing.how-it-works') }}" aria-label="Learn how to track your progress">Explore progress <span aria-hidden="true">→</span></a>
                </article>
                <article class="landing-feature-card" data-reveal>
                    <img src="{{ asset('images/icons/reminders.svg') }}" alt="" width="52" height="52" loading="lazy">
                    <h3>Work at your pace</h3>
                    <p>Stay on track with optional reminders, and keep working even when you're offline.</p>
                    <a href="{{ route('landing.how-it-works') }}" aria-label="Learn how TaskFlow helps you work at your pace">Explore reminders <span aria-hidden="true">→</span></a>
                </article>
            </div>
        </section>

        <section class="landing-how" id="how-it-works" aria-labelledby="how-title">
            <div class="landing-how-intro" data-reveal>
                <p class="landing-kicker">SIMPLE BY DESIGN</p>
                <h2 id="how-title">Start where you are.</h2>
                <p>No complicated setup. Just a few small steps to make your day feel more manageable.</p>
                @auth
                    <a class="button primary landing-cta" href="{{ route('home') }}">Open your workspace <span aria-hidden="true"></span></a>
                @else
                    <a class="button primary landing-cta" href="{{ route('register') }}">Create your free account <span aria-hidden="true">→</span></a>
                @endauth
            </div>
            <ol class="landing-steps">
                <li data-reveal><span class="landing-step-number">01</span><div><h3>Get it out of your head</h3><p>Add a task whenever it comes to mind. A title, a due date, or a note — as much or as little as you need.</p></div></li>
                <li data-reveal><span class="landing-step-number">02</span><div><h3>Give it a place</h3><p>Group tasks into projects, then use your Today and Overdue views to decide what deserves your attention.</p></div></li>
                <li data-reveal><span class="landing-step-number">03</span><div><h3>Take the next small step</h3><p>Check things off, see your progress, and make room for whatever matters next.</p></div></li>
            </ol>
        </section>

        <section class="landing-about" id="about" aria-labelledby="about-title">
            <div class="landing-about-image" data-reveal>
                <span class="landing-image-glow"></span>
                <img src="{{ asset('images/taskflow-welcome.jpg') }}" alt="A friendly illustration welcoming you to TaskFlow" loading="lazy" data-copy-alt-en="A friendly illustration welcoming you to TaskFlow" data-copy-alt-km="រូបភាពគំនូរដ៏រួសរាយរាក់ទាក់ សូមស្វាគមន៍មកកាន់ TaskFlow">
                <span class="landing-image-caption"><i>✦</i> <span data-copy-en="Soun Piseth · Cambodia" data-copy-km="សួន ពិសិដ្ឋ · កម្ពុជា">Soun Piseth · Cambodia</span></span>
            </div>
            <div class="landing-about-copy" data-reveal lang="en" aria-live="polite">
                <div class="landing-language-switch" role="group" aria-label="Choose language">
                    <button class="landing-language-button is-active" type="button" data-language="en" aria-label="Show English" aria-pressed="true">
                        <img src="{{ asset('images/icons/englishs.jpg') }}" alt="" width="24" height="16" loading="lazy"> English
                    </button>
                    <button class="landing-language-button" type="button" data-language="km" aria-label="បង្ហាញជាភាសាខ្មែរ" aria-pressed="false">
                        <img src="{{ asset('images/icons/cambodias.png') }}" alt="" width="24" height="16" loading="lazy"> ខ្មែរ
                    </button>
                </div>
                <p class="landing-kicker" data-copy-en="MEET THE CREATOR" data-copy-km="ជួបជាមួយអ្នកបង្កើត">MEET THE CREATOR</p>
                <h2 id="about-title" data-copy-en="Hi, I'm Soun Piseth." data-copy-km="សួស្តី ខ្ញុំឈ្មោះ សួន ពិសិដ្ឋ។">Hi, I'm Soun Piseth.</h2>
                <p data-copy-en="I'm from Cambodia. I created TaskFlow to help make everyday planning feel clear, calm, and manageable." data-copy-km="ខ្ញុំមកពីប្រទេសកម្ពុជា។ ខ្ញុំបានបង្កើត TaskFlow ដើម្បីជួយឱ្យការរៀបចំផែនការប្រចាំថ្ងៃកាន់តែច្បាស់លាស់ ស្ងប់ស្ងាត់ និងងាយស្រួល។">I'm from Cambodia. I created TaskFlow to help make everyday planning feel clear, calm, and manageable.</p>
                <ul>
                    <li><span>✓</span> <span data-copy-en="Made with care in Cambodia" data-copy-km="បង្កើតឡើងដោយយកចិត្តទុកដាក់នៅកម្ពុជា">Made with care in Cambodia</span></li>
                    <li><span>✓</span> <span data-copy-en="A calmer way to organize your day" data-copy-km="វិធីរៀបចំថ្ងៃរបស់អ្នកឱ្យកាន់តែស្ងប់ស្ងាត់">A calmer way to organize your day</span></li>
                    <li><span>✓</span> <span data-copy-en="Your tasks and projects, together" data-copy-km="កិច្ចការ និងគម្រោងរបស់អ្នកនៅជាមួយគ្នា">Your tasks and projects, together</span></li>
                </ul>
                @auth
                    <a class="landing-text-link landing-about-link" href="{{ route('home') }}"><span data-copy-en="Go to your workspace" data-copy-km="ទៅកាន់កន្លែងធ្វើការរបស់អ្នក">Go to your workspace</span> <span aria-hidden="true"></span></a>
                @else
                    <a class="landing-text-link landing-about-link" href="{{ route('register') }}"><span data-copy-en="Make a little room" data-copy-km="បង្កើតកន្លែងតូចមួយ">Make a little room</span> <span aria-hidden="true"></span></a>
                @endauth
            </div>
        </section>

        <section class="landing-contact" id="contact" aria-labelledby="contact-title" data-reveal>
            <div><p class="landing-kicker">ONE STEP IS ENOUGH</p><h2 id="contact-title">Ready for a calmer kind of productive?</h2><p>Start with what matters today. You can figure out the rest as you go.</p></div>
            @auth
                <a class="button landing-contact-button" href="{{ route('home') }}">Go to TaskFlow <span aria-hidden="true"></span></a>
            @else
                <a class="button landing-contact-button" href="{{ route('register') }}">Get started free <span aria-hidden="true"></span></a>
            @endauth
        </section>
    </main>

    <footer class="landing-footer">
        <a class="brand landing-brand" href="{{ route('home') }}"><span class="brand-mark">t</span> taskflow</a>
        <p>A little more focus, every day.</p>
        <div><a href="{{ route('landing.about') }}">About</a><a href="{{ route('landing.features') }}">Features</a><a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Register</a></div>
        <small>© {{ date('Y') }} TaskFlow</small>
    </footer>
</body>
</html>
