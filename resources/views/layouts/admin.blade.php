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
                <button class="admin-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <svg class="icon icon-md" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M3 6.75A.75.75 0 013.75 6h16.5a.75.75 0 010 1.5H3.75A.75.75 0 013 6.75zM3 12a.75.75 0 01.75-.75h16.5a.75.75 0 010 1.5H3.75A.75.75 0 013 12zm0 5.25a.75.75 0 01.75-.75h16.5a.75.75 0 010 1.5H3.75a.75.75 0 01-.75-.75z" clip-rule="evenodd" />
                    </svg>
                </button>
                
                <a href="/admin" class="text-lg font-bold text-primary" style="text-decoration: none;">
                    Afrik Appart Admin
                </a>
            </div>

            <div class="admin-topbar-right">
                <!-- Booking queue -->
                <a class="btn btn-ghost" href="{{ route('admin.bookings.index') }}" aria-label="Open booking queue" title="Booking queue">
                    <svg class="icon icon-md" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M5.85 3.5a.75.75 0 00-1.117-1.007A19.5 19.5 0 005.503 19.5h12.994a19.5 19.5 0 00.617-16.007.75.75 0 00-1.117 1.007A18 18 0 0018.503 19.5H5.503a18 18 0 00.347-15.993z" />
                    </svg>
                </a>

                <!-- User Menu -->
                <div class="user-menu" style="position: relative;">
                    <button class="btn btn-ghost" id="userMenuToggle" aria-label="User menu" style="display: flex; align-items: center; gap: 8px;">
                        <span>{{ auth()->user()->name }}</span>
                        <svg class="icon icon-sm" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M18.685 19.097A9.723 9.723 0 0021.75 12c0-5.385-4.365-9.75-9.75-9.75S2.25 6.615 2.25 12a9.723 9.723 0 003.065 7.097A9.716 9.716 0 0012 21.75a9.716 9.716 0 006.685-2.653z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    
                    <div id="userDropdown" class="user-dropdown" style="display: none; position: absolute; right: 0; top: 100%; background: white; border: 1px solid var(--border-default); border-radius: var(--radius-md); min-width: 200px; box-shadow: var(--shadow-md); z-index: var(--z-dropdown);">
                        <a href="/admin/profile" class="user-dropdown-item" style="display: block; padding: var(--space-3); text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-default);">
                            My Profile
                        </a>
                        <a href="/admin/preferences" class="user-dropdown-item" style="display: block; padding: var(--space-3); text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-default);">
                            Preferences
                        </a>
                        <form method="POST" action="/logout" style="display: inline;">
                            @csrf
                            <button type="submit" class="user-dropdown-item" style="width: 100%; text-align: left; padding: var(--space-3); background: none; border: none; cursor: pointer; color: var(--color-error-600); font-weight: var(--font-medium);">
                                Logout
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
                            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M11.47 3.841a.75.75 0 015.06 0l7.915 4.027A.75.75 0 0124 7.574V20.25a.75.75 0 01-.75.75H2.75A.75.75 0 012 20.25V7.574a.75.75 0 01.395-.659l7.915-4.027zM9 6.75V15a.75.75 0 001.5 0V6.75H9zm0 0h1.5m3 0H15V15a.75.75 0 001.5 0V6.75h-1.5m0 0h1.5M9 15h6" />
                            </svg>
                            <span>Dashboard</span>
                        </a>
                    </div>
                </li>

                <!-- Management -->
                <li class="admin-nav-section">
                    <div class="admin-nav-section-title">Management</div>
                    
                    @if (auth()->user()->isAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.users.index') }}" class="admin-nav-link {{ request()->is('admin/users*') ? 'is-active' : '' }}">
                            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 001.591-.079 9.018 9.018 0 002.997-.960 7.5 7.5 0 015.562-2.035 7.5 7.5 0 015.562 2.035 9.018 9.018 0 002.997.96 9.337 9.337 0 001.591.079 9.38 9.38 0 002.625-.372V9a6 6 0 00-9-5.582v.003a6 6 0 01-7.356 9.017.75.75 0 11-.564-1.41 7.5 7.5 0 009.213-7.844V9a6 6 0 00-9 5.582v10.128z" />
                            </svg>
                            <span>Users</span>
                        </a>
                    </div>
                    @endif

                    <div class="admin-nav-item">
                        <a href="/admin/bookings" class="admin-nav-link {{ request()->is('admin/bookings*') ? 'is-active' : '' }}">
                            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6.584 2.915A3 3 0 0013.9 1h.2a3 3 0 013 3v2h3.97a3 3 0 012.992 3.718A20.086 20.086 0 0121.93 10H21a.75.75 0 100 1.5h.97l-.978 12.71a3 3 0 01-2.991 2.79h-15.946a3 3 0 01-2.993-2.79L1.03 11.5H0a.75.75 0 000 1.5h.07A20.086 20.086 0 012.116 8.633 3 3 0 015.108 6.915h3.976v-2a3 3 0 011.5-2.585zM5.5 4v2h13v-2a1.5 1.5 0 00-1.5-1.5h-.2a1.5 1.5 0 00-1.5 1.5H7a1.5 1.5 0 00-1.5-1.5 1.5 1.5 0 00-1.5 1.5z" />
                            </svg>
                            <span>Bookings</span>
                        </a>
                    </div>

                    @if (auth()->user()->isAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.properties.index') }}" class="admin-nav-link {{ request()->is('admin/properties*') ? 'is-active' : '' }}">
                            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19.5 6h-15v9h15V6z" />
                                <path fill-rule="evenodd" d="M3.751 2.25a.75.75 0 00-.75.75v16.5a.75.75 0 001.5 0V3a.75.75 0 00-.75-.75zM20.25 2.25a.75.75 0 00-.75.75v16.5a.75.75 0 001.5 0V3a.75.75 0 00-.75-.75z" clip-rule="evenodd" />
                            </svg>
                            <span>Properties</span>
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()->isAdmin())
                        <div class="admin-nav-item">
                            <a href="{{ route('admin.establishments.index') }}" class="admin-nav-link {{ request()->is('admin/establishments*') ? 'is-active' : '' }}">
                                <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M3 2.25a.75.75 0 0 1 .75.75v18a.75.75 0 0 1-1.5 0V3A.75.75 0 0 1 3 2.25Zm6 3a.75.75 0 0 1 .75.75v15a.75.75 0 0 1-1.5 0V6A.75.75 0 0 1 9 5.25Zm6-3a.75.75 0 0 1 .75.75v18a.75.75 0 0 1-1.5 0V3a.75.75 0 0 1 .75-.75Zm6 3a.75.75 0 0 1 .75.75v15a.75.75 0 0 1-1.5 0V6a.75.75 0 0 1 .75-.75Z" clip-rule="evenodd" />
                                </svg>
                                <span>Establishments</span>
                            </a>
                        </div>
                    @endif
                </li>

                <!-- Settings -->
                <li class="admin-nav-section">
                    <div class="admin-nav-section-title">Settings</div>

                    @if (auth()->user()->isAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.amenities.index') }}" class="admin-nav-link {{ request()->is('admin/amenities*') ? 'is-active' : '' }}">
                            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M11.078 2.25c-.929 0-1.734.578-2.025 1.461L7.432 5.307H3.75A2.25 2.25 0 001.5 7.557v10.886A2.25 2.25 0 003.75 20.5h16.5a2.25 2.25 0 002.25-2.25V7.557a2.25 2.25 0 00-2.25-2.25H16.568l-1.621-3.846a2.25 2.25 0 00-2.025-1.461H11.078zm-2.05 6.369a.75.75 0 10-1.06 1.06L10.94 14l-2.972 2.97a.75.75 0 101.06 1.061L12 15.06l2.97 2.972a.75.75 0 10 1.061-1.06L13.06 14l2.97-2.97a.75.75 0 10-1.06-1.06L12 12.94l-2.97-2.97z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ __('messages.admin.amenities_title') }}</span>
                        </a>
                    </div>
                    @endif

                    @if (auth()->user()->isAdmin())
                    <div class="admin-nav-item">
                        <a href="{{ route('admin.settings') }}" class="admin-nav-link {{ request()->is('admin/settings*') ? 'is-active' : '' }}">
                            <svg class="icon" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M11.078 2.25c-.929 0-1.734.578-2.025 1.461L7.432 5.307H3.75A2.25 2.25 0 001.5 7.557v10.886A2.25 2.25 0 003.75 20.5h16.5a2.25 2.25 0 002.25-2.25V7.557a2.25 2.25 0 00-2.25-2.25H16.568l-1.621-3.846a2.25 2.25 0 00-2.025-1.461H11.078zm-2.05 6.369a.75.75 0 10-1.06 1.06L10.94 14l-2.972 2.97a.75.75 0 101.06 1.061L12 15.06l2.97 2.972a.75.75 0 10 1.061-1.06L13.06 14l2.97-2.97a.75.75 0 10-1.06-1.06L12 12.94l-2.97-2.97z" clip-rule="evenodd" />
                            </svg>
                            <span>Settings</span>
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
                            <a href="/admin">Home</a>
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
                    <div class="alert alert-success" role="alert" style="background-color: var(--color-success-100); border-left: 4px solid var(--color-success-600); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-6); color: var(--color-success-700);">
                        <strong>Success!</strong> {{ $message }}
                    </div>
                @endif

                @if ($message = session('error'))
                    <div class="alert alert-error" role="alert" style="background-color: var(--color-error-100); border-left: 4px solid var(--color-error-600); padding: var(--space-4); border-radius: var(--radius-md); margin-bottom: var(--space-6); color: var(--color-error-700);">
                        <strong>Error!</strong> {{ $message }}
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
</body>
</html>
