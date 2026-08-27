<nav class="section-tabs" aria-label="{{ __('messages.admin.title') }}">
    <a class="{{ request()->routeIs('admin.dashboard') ? 'is-active' : '' }}" href="{{ route('admin.dashboard') }}">{{ __('messages.admin.back_dashboard') }}</a>
    @if (auth()->user()->isAdmin())
        <a class="{{ request()->routeIs('admin.users.*') ? 'is-active' : '' }}" href="{{ route('admin.users.index') }}">{{ __('messages.admin.users') }}</a>
        <a class="{{ request()->routeIs('admin.establishments.*') ? 'is-active' : '' }}" href="{{ route('admin.establishments.index') }}">{{ __('messages.admin.establishments') }}</a>
        <a class="{{ request()->routeIs('admin.properties.*') ? 'is-active' : '' }}" href="{{ route('admin.properties.index') }}">{{ __('messages.admin.properties') }}</a>
    @endif
    <a class="{{ request()->routeIs('admin.reservations.*') ? 'is-active' : '' }}" href="{{ route('admin.reservations.index') }}">{{ __('messages.admin.reservations') }}</a>
    <a class="{{ request()->routeIs('admin.messages.*') ? 'is-active' : '' }}" href="{{ route('admin.messages.index') }}">{{ __('messages.messages.title') }}</a>
    @if (auth()->user()->isAdmin())
        <a class="{{ request()->routeIs('admin.reviews') ? 'is-active' : '' }}" href="{{ route('admin.reviews') }}">{{ __('messages.admin.reviews') }}</a>
        <a class="{{ request()->routeIs('admin.settings') ? 'is-active' : '' }}" href="{{ route('admin.settings') }}">{{ __('messages.admin.site_settings') }}</a>
    @endif
</nav>