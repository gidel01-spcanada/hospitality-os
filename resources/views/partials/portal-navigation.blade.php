@php($portalUser = auth()->user())
<aside class="portal-sidebar" data-portal-sidebar>
    <div class="portal-sidebar-heading">
        <span>{{ $portalUser->role === 'customer' ? __('messages.dashboard.account_type') : __('messages.dashboard.staff_account_type') }}</span>
        <strong>{{ __('messages.admin.' . $portalUser->role) }}</strong>
    </div>
    <nav aria-label="{{ __('messages.account.navigation') }}">
        <a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard', 'dashboard.reservations.*')) aria-current="page" @endif>
            {{ $portalUser->role === 'customer' ? __('messages.dashboard.reservations') : __('messages.dashboard.title') }}
        </a>
        @if ($portalUser->role === 'customer')
            <a href="{{ route('messages.index') }}" @if (request()->routeIs('messages.*')) aria-current="page" @endif>{{ __('messages.messages.title') }}</a>
        @elseif ($portalUser->canManageReservations())
            <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>{{ __('messages.admin.dashboard') }}</a>
            <a href="{{ route('admin.reservations.index') }}" @if (request()->routeIs('admin.reservations.*', 'admin.bookings.*')) aria-current="page" @endif>{{ __('messages.admin.reservations') }}</a>
            <a href="{{ route('admin.messages.index') }}" @if (request()->routeIs('admin.messages.*')) aria-current="page" @endif>{{ __('messages.messages.title') }}</a>
            <a href="{{ route('admin.cleaning.index') }}" @if (request()->routeIs('admin.cleaning.*')) aria-current="page" @endif>{{ __('messages.cleaning.title') }}</a>
            @if ($portalUser->isEstablishmentManager())
                <a href="{{ route('admin.reports.index') }}" @if (request()->routeIs('admin.reports.*')) aria-current="page" @endif>{{ __('messages.reports.title') }}</a>
                <a href="{{ route('admin.properties.index') }}" @if (request()->routeIs('admin.properties.*')) aria-current="page" @endif>{{ __('messages.admin.properties') }}</a>
                <a href="{{ route('admin.establishments.index') }}" @if (request()->routeIs('admin.establishments.*')) aria-current="page" @endif>{{ __('messages.admin.establishments') }}</a>
                <a href="{{ route('admin.preferences') }}" @if (request()->routeIs('admin.preferences', 'admin.profile')) aria-current="page" @endif>{{ __('messages.profile.tabs.preferences') }}</a>
            @endif
        @endif
        <a href="{{ route('account.profile') }}" @if (request()->routeIs('account.profile', 'account.profile.update')) aria-current="page" @endif>{{ __('messages.profile.tabs.profile') }}</a>
        @unless ($portalUser->isEstablishmentManager())
            <a href="{{ route('account.preferences') }}" @if (request()->routeIs('account.preferences')) aria-current="page" @endif>{{ __('messages.profile.tabs.preferences') }}</a>
        @endunless
        <a href="{{ route('account.security') }}" @if (request()->routeIs('account.security')) aria-current="page" @endif>{{ __('messages.profile.tabs.security') }}</a>
        <a class="portal-support-link" href="{{ route('contact') }}">{{ __('messages.footer.need_help') }}</a>
    </nav>
</aside>