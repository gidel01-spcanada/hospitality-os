<nav class="section-tabs account-tabs" aria-label="{{ __('messages.account.navigation') }}" data-account-tabs>
    <a class="{{ request()->routeIs('dashboard', 'dashboard.reservations.*') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">{{ __('messages.dashboard.reservations') }}</a>
    @if (auth()->user()?->role === 'customer')
        <a class="{{ request()->routeIs('messages.*') ? 'is-active' : '' }}" href="{{ route('messages.index') }}">{{ __('messages.messages.title') }}</a>
    @endif
    <a class="{{ request()->routeIs('account.profile') ? 'is-active' : '' }}" href="{{ route('account.profile') }}">{{ __('messages.profile.tabs.profile') }}</a>
    <a class="{{ request()->routeIs('account.preferences') ? 'is-active' : '' }}" href="{{ route('account.preferences') }}">{{ __('messages.profile.tabs.preferences') }}</a>
    <a class="{{ request()->routeIs('account.security') ? 'is-active' : '' }}" href="{{ route('account.security') }}">{{ __('messages.profile.tabs.security') }}</a>
</nav>