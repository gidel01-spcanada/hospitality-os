{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" @if(auth()->user()?->theme) data-theme="{{ auth()->user()->theme }}" @endif>
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="@yield('description', 'Afrik Appart Admin Dashboard')" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    
    <title>@yield('title', 'Admin Dashboard') - Afrik Appart</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="admin-layout">
    <!-- Admin Shell -->
    <div class="admin-shell">
        <!-- Topbar -->
        <div class="admin-topbar">
            <div class="admin-topbar-left">
                <button class="admin-sidebar-toggle" id="sidebarToggle" aria-label="{{ __('messages.admin.toggle_navigation') }}">
                    <x-admin-icon name="menu" class="icon icon-md" />
                </button>
                
                <a href="/admin" class="admin-topbar-brand">
                    {{ __('messages.admin.title') }}
                </a>
            </div>

            <div class="admin-topbar-right">
                <!-- Public site -->
                <a class="btn btn-ghost" href="{{ route('home') }}" target="_blank" rel="noopener" aria-label="{{ __('messages.admin.view_site') }}" title="{{ __('messages.admin.view_site') }}">
                    <x-admin-icon name="globe" class="icon icon-md" />
                </a>

                <!-- Booking queue -->
                <a class="btn btn-ghost" href="{{ route('admin.bookings.index') }}" aria-label="{{ __('messages.admin.open_reservations') }}" title="{{ __('messages.admin.open_reservations') }}">
                    <x-admin-icon name="calendar" class="icon icon-md" />
                </a>

                <!-- User Menu -->
                <div class="user-menu" style="position: relative;">
                    <button class="btn btn-ghost admin-user-menu-toggle" id="userMenuToggle" aria-label="{{ __('messages.admin.user_menu') }}">
                        <span class="admin-user-menu-name">{{ auth()->user()->name }}</span>
                        <x-admin-icon name="user-circle" class="icon icon-sm" />
                    </button>
                    
                    <div id="userDropdown" class="user-dropdown" style="display: none; position: absolute; right: 0; top: 100%; background: white; border: 1px solid var(--border-default); border-radius: var(--radius-md); min-width: 200px; box-shadow: var(--shadow-md); z-index: var(--z-dropdown);">
                        <a href="{{ route('account.profile') }}" class="user-dropdown-item" style="display: block; padding: var(--space-3); text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-default);">
                            {{ __('messages.profile.tabs.profile') }}
                        </a>
                        <a href="{{ route('home') }}" class="user-dropdown-item" style="display: block; padding: var(--space-3); text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-default);">
                            {{ __('messages.admin.view_site') }}
                        </a>
                        <form method="POST" action="/logout" style="display: inline;">
                            @csrf
                            <button type="submit" class="user-dropdown-item" style="width: 100%; text-align: left; padding: var(--space-3); background: none; border: none; cursor: pointer; color: var(--color-error-600); font-weight: var(--font-medium);">
                                {{ __('messages.nav.logout') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Navigation -->
        <nav class="admin-sidebar" id="adminSidebar">
            <ul class="admin-nav">
                <!-- Dashboard -->
                <li class="admin-nav-section">
                    <div class="admin-nav-item">
                        <a href="/admin" class="admin-nav-link {{ request()->is('admin') ? 'is-active' : '' }}">
                            <x-admin-icon name="dashboard" />
                            <span>{{ __('messages.admin.dashboard') }}</span>
                        </a>
                    </div>
                </li>

                <li class="admin-nav-section">
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.messages.index') }}" class="admin-nav-link {{ request()->routeIs('admin.messages.*') ? 'is-active' : '' }}">
                            <x-admin-icon name="messages" />
                            <span>{{ __('messages.messages.title') }}</span>
                        </a>
                    </div>
                </li>

                <!-- Management -->
                <li class="admin-nav-section">
                    <div class="admin-nav-section-title">{{ __('messages.admin.management') }}</div>

                    @if (auth()->user()->isAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.users.index') }}" class="admin-nav-link {{ request()->is('admin/users*') ? 'is-active' : '' }}">
                            <x-admin-icon name="users" />
                            <span>{{ __('messages.admin.users_title') }}</span>
                        </a>
                    </div>
                    @endif

                    <div class="admin-nav-item">
                        <a href="/admin/bookings" class="admin-nav-link {{ request()->is('admin/bookings*') ? 'is-active' : '' }}">
                            <x-admin-icon name="reservations" />
                            <span>{{ __('messages.admin.reservations') }}</span>
                        </a>
                    </div>

                    <div class="admin-nav-item">
                        <a href="{{ route('admin.cleaning.index') }}" class="admin-nav-link {{ request()->routeIs('admin.cleaning.*') ? 'is-active' : '' }}">
                            <x-admin-icon name="cleaning" />
                            <span>{{ __('messages.cleaning.title') }}</span>
                        </a>
                    </div>

                    @if (auth()->user()->isEstablishmentManager())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.reports.index') }}" class="admin-nav-link {{ request()->routeIs('admin.reports.*') ? 'is-active' : '' }}">
                            <x-admin-icon name="reports" />
                            <span>{{ __('messages.reports.title') }}</span>
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()->isEstablishmentManager())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.properties.index') }}" class="admin-nav-link {{ request()->is('admin/properties*') ? 'is-active' : '' }}">
                            <x-admin-icon name="properties" />
                            <span>{{ __('messages.admin.properties') }}</span>
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()->isEstablishmentManager())
                        <div class="admin-nav-item">
                            <a href="{{ route('admin.establishments.index') }}" class="admin-nav-link {{ request()->is('admin/establishments*') ? 'is-active' : '' }}">
                                <x-admin-icon name="establishments" />
                                <span>{{ __('messages.admin.establishments') }}</span>
                            </a>
                        </div>
                    @endif
                </li>

                <!-- Settings -->
                <li class="admin-nav-section">
                    <div class="admin-nav-section-title">{{ __('messages.admin.settings_title') }}</div>

                    @if (auth()->user()->isAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.amenities.index') }}" class="admin-nav-link {{ request()->is('admin/amenities*') ? 'is-active' : '' }}">
                            <x-admin-icon name="amenities" />
                            <span>{{ __('messages.admin.amenities_menu') }}</span>
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()->isAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.settings') }}" class="admin-nav-link {{ request()->is('admin/settings*') ? 'is-active' : '' }}">
                            <x-admin-icon name="settings" />
                            <span>{{ __('messages.admin.settings') }}</span>
                        </a>
                    </div>
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.logs') }}" class="admin-nav-link {{ request()->routeIs('admin.logs') ? 'is-active' : '' }}">
                            <x-admin-icon name="reports" />
                            <span>{{ __('messages.admin.logs') }}</span>
                        </a>
                    </div>
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.audit-logs') }}" class="admin-nav-link {{ request()->routeIs('admin.audit-logs') ? 'is-active' : '' }}">
                            <x-admin-icon name="reports" />
                            <span>Journal des modifications</span>
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()->isPlatformAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('platform.tenants.index') }}" class="admin-nav-link {{ request()->is('platform/*') ? 'is-active' : '' }}">
                            <x-admin-icon name="tenants" />
                            <span>{{ __('messages.platform.tenants_title') }}</span>
                        </a>
                    </div>
                    @endif
                </li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-content">
                <!-- Breadcrumbs -->
                @if ($breadcrumbs ?? null)
                    <nav class="breadcrumbs" aria-label="Breadcrumb">
                        <span class="breadcrumb-item">
                            <a href="/admin">{{ __('messages.nav.home') }}</a>
                        </span>
                        @foreach ($breadcrumbs as $crumb)
                            <span class="breadcrumb-separator">›</span>
                            @if ($loop->last)
                                <span class="breadcrumb-item is-current">{{ $crumb['title'] }}</span>
                            @else
                                <span class="breadcrumb-item">
                                    <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                                </span>
                            @endif
                        @endforeach
                    </nav>
                @endif

                <!-- Flash Messages -->
                @if ($message = session('success') ?? session('status'))
                    <div class="alert alert-success admin-flash" role="status" aria-live="polite">
                        <strong>{{ __('messages.common.success') }}</strong> {{ $message }}
                    </div>
                @endif

                @if ($message = session('error'))
                    <div class="alert alert-error admin-flash" role="alert" aria-live="assertive">
                        <strong>{{ __('messages.common.error') }}</strong> {{ $message }}
                    </div>
                @endif

                <!-- Page Content -->
                @yield('content')
            </div>
        </main>
    </div>

    <!-- Sidebar Toggle Script -->
    <script>
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('adminSidebar');
            if (window.matchMedia('(max-width: 767px)').matches) {
                sidebar.classList.toggle('is-open');
            } else {
                sidebar.classList.toggle('collapsed');
            }
        });

        // User dropdown menu
        document.getElementById('userMenuToggle').addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = document.getElementById('userDropdown');
            dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
        });

        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('userDropdown');
            const userMenu = document.querySelector('.user-menu');
            if (!userMenu.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    </script>

    @stack('scripts')
    @include('partials.confirm-dialog')
</body>
</html>
