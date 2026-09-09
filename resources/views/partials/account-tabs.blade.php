<nav class="section-tabs" aria-label="{{ __('messages.account.navigation') }}">
    <a class="{{ request()->routeIs('dashboard', 'dashboard.reservations.*') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">{{ __('messages.dashboard.reservations') }}</a>
    <a class="{{ request()->routeIs('account.settings') ? 'is-active' : '' }}" href="{{ route('account.settings') }}">{{ __('messages.profile.tabs.settings') }}</a>
    <a class="{{ request()->routeIs('account.profile') ? 'is-active' : '' }}" href="{{ route('account.profile') }}">{{ __('messages.profile.tabs.profile') }}</a>
    <a class="{{ request()->routeIs('account.preferences') ? 'is-active' : '' }}" href="{{ route('account.preferences') }}">{{ __('messages.profile.tabs.preferences') }}</a>
    <a class="{{ request()->routeIs('account.security') ? 'is-active' : '' }}" href="{{ route('account.security') }}">{{ __('messages.profile.tabs.security') }}</a>
</nav>