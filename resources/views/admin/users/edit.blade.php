@extends('layouts.admin')

@section('title', $managedUser->exists ? __('messages.admin.edit_user') : __('messages.admin.add_user'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ $managedUser->exists ? $managedUser->name : __('messages.admin.add_user') }}</h1>
        <p class="admin-page-description">{{ $managedUser->exists ? __('messages.admin.user_editor_description') : __('messages.admin.create_user_description') }}</p>
    </div>
    <x-card>
            @if ($errors->any())<div class="form-alert form-alert-error">@foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach</div>@endif
            <form method="POST" action="{{ $managedUser->exists ? route('admin.users.update', $managedUser) : route('admin.users.store') }}">
                @csrf @if($managedUser->exists) @method('PUT') @endif
                <div class="form-grid">
                    <div><label for="name">{{ __('messages.admin.name') }}</label><input id="name" name="name" value="{{ old('name', $managedUser->name) }}" required></div>
                    <div><label for="email">{{ __('messages.common.email') }}</label><input id="email" type="email" name="email" value="{{ old('email', $managedUser->email) }}" required></div>
                    <div><label for="role">{{ __('messages.admin.role') }}</label><select id="role" name="role"><option value="customer" @selected(old('role', $managedUser->role) === 'customer')>{{ __('messages.admin.customer') }}</option><option value="concierge" @selected(old('role', $managedUser->role) === 'concierge')>{{ __('messages.admin.concierge') }}</option><option value="host" @selected(old('role', $managedUser->role) === 'host')>{{ __('messages.admin.host') }}</option><option value="admin" @selected(old('role', $managedUser->role) === 'admin')>{{ __('messages.admin.administrator') }}</option></select></div>
                    <div><label for="locale">{{ __('messages.admin.language') }}</label><select id="locale" name="locale"><option value="fr" @selected(old('locale', $managedUser->locale) === 'fr')>Français</option><option value="en" @selected(old('locale', $managedUser->locale) === 'en')>English</option></select></div>
                    <div class="full-width">
                        <label for="establishments">{{ __('messages.admin.host_establishments') }}</label>
                        <select id="establishments" name="establishments[]" multiple size="5">
                            @foreach ($establishments as $establishment)
                                <option value="{{ $establishment->id }}" @selected(in_array($establishment->id, old('establishments', $assignedEstablishmentIds)))>{{ $establishment->name }}</option>
                            @endforeach
                        </select>
                        <p class="form-help">{{ __('messages.admin.host_establishments_help') }}</p>
                    </div>
                    @if (! $managedUser->exists)
                        <div><label for="password">{{ __('messages.admin.initial_password') }}</label><input id="password" type="password" name="password" required></div>
                        <div><label for="password_confirmation">{{ __('messages.common.confirm_password') }}</label><input id="password_confirmation" type="password" name="password_confirmation" required></div>
                    @endif
                    <label class="checkbox-field"><input type="checkbox" name="email_verified" value="1" @checked(old('email_verified', (bool) $managedUser->email_verified_at))><span>{{ __('messages.admin.email_verified') }}</span></label>
                    <label class="checkbox-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser->exists ? $managedUser->is_active : true))><span>{{ __('messages.admin.account_active') }}</span></label>
                    <label class="checkbox-field"><input type="checkbox" name="email_booking_updates" value="1" @checked(old('email_booking_updates', $managedUser->email_booking_updates))><span>{{ __('messages.admin.reservation_updates') }}</span></label>
                    <label class="checkbox-field"><input type="checkbox" name="email_message_updates" value="1" @checked(old('email_message_updates', $managedUser->email_message_updates ?? true))><span>{{ __('messages.profile.message_updates') }}</span></label>
                    <label class="checkbox-field"><input type="checkbox" name="email_marketing" value="1" @checked(old('email_marketing', $managedUser->email_marketing))><span>{{ __('messages.admin.marketing_offers') }}</span></label>
                    <label class="checkbox-field"><input type="checkbox" name="email_newsletter" value="1" @checked(old('email_newsletter', $managedUser->email_newsletter))><span>{{ __('messages.admin.newsletter') }}</span></label>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_user') }}</button><a class="btn btn-ghost" href="{{ route('admin.users.index') }}">{{ __('messages.admin.back') }}</a></div>
            </form>
    </x-card>
@endsection