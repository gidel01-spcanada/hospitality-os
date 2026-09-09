@extends('layouts.app')

@section('title', __('messages.profile.title'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-emerald">{{ __('messages.dashboard.account_type') }}</span>
            <h1>{{ __('messages.profile.title') }}</h1>
            <p>{{ __('messages.profile.subtitle') }}</p>
        </div>
    </section>

    <section class="container dashboard-grid">
        <div class="summary-card">
            <h2>{{ __('messages.profile.account_overview') }}</h2>
            <ul>
                <li><strong>{{ __('messages.profile.email') }}</strong><span>{{ $user->email }}</span></li>
                <li><strong>{{ __('messages.profile.role') }}</strong><span>{{ $user->role }}</span></li>
                <li><strong>{{ __('messages.profile.status') }}</strong><span>{{ $user->email_verified_at ? __('messages.dashboard.verified') : __('messages.dashboard.pending_verification') }}</span></li>
            </ul>
        </div>

        <div class="summary-card">
            <h2>{{ __('messages.profile.personal') }}</h2>
            @if (session('status'))
                <div class="alert success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('account.profile.update') }}">
                @csrf
                <div>
                    <label for="name">{{ __('messages.profile.full_name') }}</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">{{ __('messages.profile.save') }}</button>
                    <a href="{{ route('dashboard') }}" class="btn btn-ghost">{{ __('messages.profile.back') }}</a>
                </div>
            </form>
        </div>
    </section>
@endsection
