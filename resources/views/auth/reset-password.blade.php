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
        @if (request()->query('checkout_return'))
            <input type="hidden" name="checkout_return" value="{{ request()->query('checkout_return') }}">
        @endif

        <p class="form-help">{{ __('messages.account_setup.confirm_password_help') }}</p>

        @if ($errors->hasAny(['email', 'token']))
            <div class="form-alert form-alert-error" role="alert" data-reset-error>
                {{ $errors->first('email') ?: $errors->first('token') }}
                <a class="inline-link" href="{{ route('password.request') }}">{{ __('messages.auth.request_new_link') }}</a>
            </div>
        @endif

        <div class="field-group">
            <label for="password">{{ __('messages.security.new_password') }}</label>
            <input id="password" type="password" name="password" required @error('password') class="is-error" aria-invalid="true" aria-describedby="reset-password-error" @enderror>
            @error('password')
                <span id="reset-password-error" class="form-error">{{ $message }}</span>
            @enderror
        </div>

        <div class="field-group">
            <label for="password_confirmation">{{ __('messages.common.confirm_password') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required @error('password') class="is-error" aria-invalid="true" aria-describedby="reset-password-error" @enderror>
        </div>

        <button type="submit" class="btn btn-primary">{{ __('messages.auth.update_password') }}</button>
    </form>
</section>
@endsection
