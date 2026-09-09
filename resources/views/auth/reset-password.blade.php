@extends('layouts.app')

@section('title', __('messages.auth.reset_title'))

@section('content')
<section class="page-hero compact-hero">
    <div class="container narrow">
        <span class="badge badge-emerald">{{ __('messages.auth.forgot_badge') }}</span>
        <h1>{{ __('messages.auth.reset_title') }}</h1>
        <p>{{ __('messages.auth.reset_subtitle') }}</p>
    </div>
</section>

<section class="container auth-panel">
    <form method="POST" action="{{ route('password.update') }}" class="auth-form">
        @csrf

        <input type="hidden" name="token" value="{{ $token }}">
        <input type="hidden" name="email" value="{{ $email ?? old('email') }}">

        <p class="form-help">{{ __('messages.account_setup.confirm_password_help') }}</p>

        <div class="field-group">
            <label for="password">{{ __('messages.security.new_password') }}</label>
            <input id="password" type="password" name="password" required>
            @error('password')
                <span class="error-text">{{ $message }}</span>
            @enderror
        </div>

        <div class="field-group">
            <label for="password_confirmation">{{ __('messages.common.confirm_password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('messages.auth.update_password') }}</button>
    </form>
</section>
@endsection
