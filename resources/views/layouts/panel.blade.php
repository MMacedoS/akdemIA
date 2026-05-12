<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">
    <title>@yield('pageTitle', 'Painel') - AcademAI</title>
    @vite(['resources/js/app.js'])
</head>
<body class="panel-layout">
    <div class="shell" id="platform-shell">
        <header class="mobile-header">
            <button id="mobile-menu-open" class="mobile-menu-btn" type="button" aria-label="Abrir menu">☰</button>
            <div class="mobile-header-main">
                <div class="mobile-header-title">
                    <strong>@yield('headerTitle', 'Dashboard')</strong>
                    <small>AcademAI</small>
                </div>
                <span id="platform-clock-mobile" class="mobile-header-clock" aria-live="polite">--:--:--</span>
            </div>
        </header>

        <button id="sidebar-backdrop" class="sidebar-backdrop" type="button" aria-label="Fechar menu"></button>

        <aside class="sidebar" id="platform-sidebar">
            <div class="sidebar-head">
                <h1 class="brand">AcademAI Panel</h1>
                <button id="mobile-menu-close" class="sidebar-close" type="button" aria-label="Fechar menu">×</button>
            </div>
            <p class="brand-subtitle">Navegacao central da plataforma</p>

            @php
                $tenant = request()->attributes->get('tenant');
                $profileType = auth()->user()?->profileType();
                $user = auth()->user();
                $legacyProfileType = (string) (auth()->user()?->profile_type ?? '');
                $hasSharedTrainerMenu = $profileType === \App\Enums\Role::TRAINER
                    || $legacyProfileType === 'trainee'
                    || ($tenant instanceof \App\Models\Tenant\Tenant && $user?->getRole($tenant) === \App\Enums\Role::TRAINER)
                    || (method_exists($user, 'isTrainee') && $user?->isTrainee());
                $role = $hasSharedTrainerMenu
                    ? 'trainee'
                    : ($tenant instanceof \App\Models\Tenant\Tenant ? $user?->getRole($tenant) : null);
                $canManageOwnLanding = $hasSharedTrainerMenu;
                $trainerSharedNavigationItems = [
                    ['label' => 'Dashboard', 'icon' => 'DB', 'route' => 'trainee.dashboard', 'active' => 'trainee.dashboard'],
                    ['label' => 'Selecionar tenant', 'icon' => 'TN', 'route' => 'tenants.select', 'active' => 'tenants.select*'],
                    ['label' => 'Creditos', 'icon' => 'CR', 'route' => 'trainee.credits.index', 'active' => 'trainee.credits.*'],
                    ['label' => 'Alunos', 'icon' => 'AL', 'route' => 'trainee.students.index', 'active' => 'trainee.students.*'],
                    ['label' => 'Minha landing', 'icon' => 'LP', 'route' => 'my-landing.edit', 'active' => 'my-landing.*'],
                    ['label' => 'Perfil', 'icon' => 'PF', 'route' => 'profile.edit', 'active' => 'profile.*'],
                    ['label' => 'Seguranca', 'icon' => 'SG', 'route' => 'security.edit', 'active' => 'security.*'],
                ];

                $navigationItems = match ($role) {
                    \App\Enums\Role::ADMIN => [
                        ['label' => 'Dashboard', 'icon' => 'DB', 'route' => 'admin.dashboard', 'active' => 'admin.dashboard'],
                        ['label' => 'Landing', 'icon' => 'LP', 'route' => 'admin.landing.edit', 'active' => 'admin.landing.*'],
                        ['label' => 'Creditos', 'icon' => 'CR', 'route' => 'admin.credits.index', 'active' => 'admin.credits.*'],
                        ['label' => 'Usuarios', 'icon' => 'US', 'route' => 'admin.users.index', 'active' => 'admin.users.*'],
                        ['label' => 'Alunos', 'icon' => 'AL', 'route' => 'admin.students.index', 'active' => 'admin.students.*'],
                        ['label' => 'Trainees', 'icon' => 'TE', 'route' => 'admin.trainees.index', 'active' => 'admin.trainees.*'],
                        ['label' => 'Trainers', 'icon' => 'TR', 'route' => 'admin.trainers.index', 'active' => 'admin.trainers.*'],
                        ...((bool) auth()->user()?->isSystemAdmin() ? [
                            ['label' => 'Sistema', 'icon' => 'SY', 'route' => 'system-admin.dashboard', 'active' => 'system-admin.dashboard'],
                            ['label' => 'Tenants', 'icon' => 'TN', 'route' => 'system-admin.tenants.index', 'active' => 'system-admin.tenants.*'],
                            ['label' => 'Trainees', 'icon' => 'TE', 'route' => 'system-admin.trainees.index', 'active' => 'system-admin.trainees.*'],
                            ['label' => 'Usuarios', 'icon' => 'US', 'route' => 'system-admin.users.index', 'active' => 'system-admin.users.*'],
                            ['label' => 'Creditos', 'icon' => 'CR', 'route' => 'system-admin.credits.index', 'active' => 'system-admin.credits.*'],
                            ['label' => 'Pagamento', 'icon' => 'PG', 'route' => 'system-admin.settings.payment.edit', 'active' => 'system-admin.settings.payment.*'],
                            ['label' => 'Legal', 'icon' => 'LG', 'route' => 'system-admin.settings.legal.edit', 'active' => 'system-admin.settings.legal.*'],
                            ['label' => 'Email', 'icon' => 'EM', 'route' => 'system-admin.settings.email.edit', 'active' => 'system-admin.settings.email.*'],
                            ['label' => 'Logs', 'icon' => 'LG', 'route' => 'system-admin.settings.logs.index', 'active' => 'system-admin.settings.logs.*'],
                            ['label' => 'Treinos', 'icon' => 'TR', 'route' => 'system-admin.settings.workouts.edit', 'active' => 'system-admin.settings.workouts.*'],
                            ['label' => 'WorkoutX', 'icon' => 'WX', 'route' => 'system-admin.settings.workoutx.edit', 'active' => 'system-admin.settings.workoutx.*'],
                            ['label' => 'Landing geral', 'icon' => 'LG', 'route' => 'system-admin.landing.edit', 'active' => 'system-admin.landing.*'],
                        ] : []),
                    ],
                    \App\Enums\Role::TRAINER => $trainerSharedNavigationItems,
                    \App\Enums\Role::STUDENT => [
                        ['label' => 'Dashboard', 'icon' => 'DB', 'route' => 'students.dashboard', 'active' => 'students.dashboard'],
                        ['label' => 'Meu treino', 'icon' => 'TR', 'route' => 'students.workout.show', 'active' => 'students.workout.*'],
                        ['label' => 'Minha saude', 'icon' => 'SD', 'route' => 'students.health.edit', 'active' => 'students.health.*'],
                        ['label' => 'Perfil', 'icon' => 'PF', 'route' => 'profile.edit', 'active' => 'profile.*'],
                        ['label' => 'Seguranca', 'icon' => 'SG', 'route' => 'security.edit', 'active' => 'security.*'],
                    ],
                    'trainee' => $trainerSharedNavigationItems,
                    default => [
                        ...((bool) auth()->user()?->isSystemAdmin() ? [
                            ['label' => 'Sistema', 'icon' => 'SY', 'route' => 'system-admin.dashboard', 'active' => 'system-admin.dashboard'],
                            ['label' => 'Tenants', 'icon' => 'TN', 'route' => 'system-admin.tenants.index', 'active' => 'system-admin.tenants.*'],
                            ['label' => 'Trainees', 'icon' => 'TE', 'route' => 'system-admin.trainees.index', 'active' => 'system-admin.trainees.*'],
                            ['label' => 'Usuarios', 'icon' => 'US', 'route' => 'system-admin.users.index', 'active' => 'system-admin.users.*'],
                            ['label' => 'Creditos', 'icon' => 'CR', 'route' => 'system-admin.credits.index', 'active' => 'system-admin.credits.*'],
                            ['label' => 'Pagamento', 'icon' => 'PG', 'route' => 'system-admin.settings.payment.edit', 'active' => 'system-admin.settings.payment.*'],
                            ['label' => 'Legal', 'icon' => 'LG', 'route' => 'system-admin.settings.legal.edit', 'active' => 'system-admin.settings.legal.*'],
                            ['label' => 'Email', 'icon' => 'EM', 'route' => 'system-admin.settings.email.edit', 'active' => 'system-admin.settings.email.*'],
                            ['label' => 'Logs', 'icon' => 'LG', 'route' => 'system-admin.settings.logs.index', 'active' => 'system-admin.settings.logs.*'],
                            ['label' => 'Treinos', 'icon' => 'TR', 'route' => 'system-admin.settings.workouts.edit', 'active' => 'system-admin.settings.workouts.*'],
                            ['label' => 'WorkoutX', 'icon' => 'WX', 'route' => 'system-admin.settings.workoutx.edit', 'active' => 'system-admin.settings.workoutx.*'],
                            ['label' => 'Landing geral', 'icon' => 'LG', 'route' => 'system-admin.landing.edit', 'active' => 'system-admin.landing.*'],
                        ] : []),
                        ...($canManageOwnLanding ? [
                            ['label' => 'Minha landing', 'icon' => 'LP', 'route' => 'my-landing.edit', 'active' => 'my-landing.*'],
                        ] : []),
                        ['label' => 'Perfil', 'icon' => 'PF', 'route' => 'profile.edit', 'active' => 'profile.*'],
                        ['label' => 'Seguranca', 'icon' => 'SG', 'route' => 'security.edit', 'active' => 'security.*'],
                    ],
                };
            @endphp

            <div class="sidebar-nav">
                <div class="nav-section">Navegacao</div>
                <div class="nav-list">
                    @foreach ($navigationItems as $navigationItem)
                        <a href="{{ route($navigationItem['route']) }}" class="nav-link {{ request()->routeIs($navigationItem['active']) ? 'active' : '' }}">
                            <span class="nav-link-left">
                                <span class="nav-icon">{{ $navigationItem['icon'] }}</span>
                                <span class="nav-label">{{ $navigationItem['label'] }}</span>
                            </span>
                            <span class="nav-go">&gt;</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="nav-logout">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-logout-btn">Sair da plataforma</button>
                </form>
            </div>

        </aside>

        <div class="panel">
            <header class="topbar">
                <div>
                    <h2 class="title">@yield('headerTitle', 'Dashboard')</h2>
                    <p class="subtitle">Interface inspirada no estilo Materio</p>
                </div>

                <div class="topbar-right">
                    @yield('headerAction')

                    <div id="platform-clock" class="top-clock" aria-live="polite">--:--:--</div>

                    <details class="account-menu">
                        <summary class="account-toggle" style="list-style:none;">
                            @if (auth()->user()?->avatar_path)
                                <img class="avatar" src="{{ asset('storage/' . auth()->user()->avatar_path) }}" alt="Avatar">
                            @else
                                <span class="avatar">{{ strtoupper(substr((string) auth()->user()?->name, 0, 1)) }}</span>
                            @endif
                            <span>{{ auth()->user()?->name }}</span>
                        </summary>

                        <div class="account-panel">
                            <a href="{{ route('profile.edit') }}" class="account-link">Meu perfil</a>
                            <a href="{{ route('security.edit') }}" class="account-link">Trocar senha</a>
                            <a href="{{ route('appearance.edit') }}" class="account-link">Aparencia</a>
                            <a href="{{ route('drop-account.create') }}" class="account-link">Excluir conta</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="logout-btn">Sair da plataforma</button>
                            </form>
                        </div>
                    </details>
                </div>
            </header>

            @if (session('status'))
                <div class="alert success">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert error">{{ $errors->first() }}</div>
            @endif

            @php
                $systemNotifications = auth()->user()?->unreadNotifications()
                    ->latest()
                    ->limit(5)
                    ->get();
            @endphp

            @if ($systemNotifications && $systemNotifications->isNotEmpty())
                @foreach ($systemNotifications as $notification)
                    @php
                        $payload = (array) $notification->data;
                        $level = (string) ($payload['level'] ?? 'info');
                        $alertClass = match ($level) {
                            'success' => 'success',
                            'error' => 'error',
                            default => 'success',
                        };
                        $title = trim((string) ($payload['title'] ?? $payload['subject'] ?? 'Notificacao interna'));
                        $message = trim((string) ($payload['message'] ?? 'Voce tem uma nova notificacao no sistema.'));
                        $loginUrl = trim((string) ($payload['login_url'] ?? ''));
                        $tenantUrl = trim((string) ($payload['tenant_url'] ?? ''));
                    @endphp
                    <div class="alert {{ $alertClass }}">
                        <strong>{{ $title }}</strong><br>
                        {{ $message }}
                        @if ($loginUrl !== '')
                            <br><a href="{{ $loginUrl }}">Abrir painel</a>
                        @elseif ($tenantUrl !== '')
                            <br><a href="{{ $tenantUrl }}">Abrir tenant</a>
                        @endif
                    </div>
                @endforeach

                @php
                    $systemNotifications->markAsRead();
                @endphp
            @endif

            @yield('content')

            <footer class="platform-footer">
                <span>Projeto: AcademAI</span>
                <span>Data: {{ now()->format('d/m/Y') }}</span>
                <span>Desenvolvedor: Codigo&Rede</span>
            </footer>
        </div>
    </div>

    <script>
        (function () {
            const shell = document.getElementById('platform-shell');
            const openMenuButton = document.getElementById('mobile-menu-open');
            const closeMenuButton = document.getElementById('mobile-menu-close');
            const backdrop = document.getElementById('sidebar-backdrop');
            const navLinks = document.querySelectorAll('#platform-sidebar .nav-link');
            const clock = document.getElementById('platform-clock');
            const mobileClock = document.getElementById('platform-clock-mobile');

            function openSidebar() {
                if (!shell) {
                    return;
                }

                shell.classList.add('sidebar-open');
                document.body.classList.add('no-scroll');
            }

            function closeSidebar() {
                if (!shell) {
                    return;
                }

                shell.classList.remove('sidebar-open');
                document.body.classList.remove('no-scroll');
            }

            if (openMenuButton) {
                openMenuButton.addEventListener('click', openSidebar);
            }

            if (closeMenuButton) {
                closeMenuButton.addEventListener('click', closeSidebar);
            }

            if (backdrop) {
                backdrop.addEventListener('click', closeSidebar);
            }

            navLinks.forEach((link) => {
                link.addEventListener('click', closeSidebar);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closeSidebar();
                }
            });

            function updateClock() {
                const now = new Date();
                const time = now.toLocaleTimeString('pt-BR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                });

                if (clock) {
                    clock.textContent = time;
                }

                if (mobileClock) {
                    mobileClock.textContent = time;
                }
            }

            updateClock();
            setInterval(updateClock, 1000);
        })();
    </script>
</body>
</html>
