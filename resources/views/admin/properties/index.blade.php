@extends('layouts.app')

@section('title', __('messages.admin.properties_management'))

@section('content')
    <section class="page-hero compact-hero">
        <div class="container">
            <span class="badge badge-gold">Administration</span>
            <h1>{{ __('messages.admin.properties') }}</h1>
            <p>{{ __('messages.admin.properties_description') }}</p>
        </div>
    </section>

    <section class="container admin-list-shell">
        @if (session('success'))
            <div class="reservation-success">{{ session('success') }}</div>
        @endif

        <form method="GET" action="{{ route('admin.properties.index') }}" class="property-filters">
            <div class="form-grid">
                <div>
                    <label for="establishment">{{ __('messages.common.establishment') }}</label>
                    <select id="establishment" name="establishment">
                        <option value="">{{ __('messages.properties.all_establishments') }}</option>
                        @foreach ($establishments as $establishment)
                            <option value="{{ $establishment->id }}" @selected($establishmentId == $establishment->id)>{{ $establishment->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">{{ __('messages.properties.apply') }}</button>
                <a class="btn btn-ghost" href="{{ route('admin.properties.index') }}">{{ __('messages.properties.reset') }}</a>
            </div>
        </form>

        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>{{ __('messages.admin.name') }}</th>
                        <th>{{ __('messages.admin.type') }}</th>
                        <th>{{ __('messages.common.establishment') }}</th>
                        <th>{{ __('messages.admin.rate') }}</th>
                        <th>{{ __('messages.reservation.status') }}</th>
                        <th>{{ __('messages.admin.availability_status') }}</th>
                        <th>{{ __('messages.admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($properties as $property)
                        <tr>
                            <td><strong>{{ $property->name }}</strong><br><small>{{ $property->slug }}</small></td>
                            <td>{{ $property->property_type ?: 'apartment' }}</td>
                            <td>
                                @if ($property->establishment)
                                    <a href="{{ route('admin.establishments.edit', $property->establishment) }}">{{ $property->establishment->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ number_format((float) $property->nightly_rate_xof, 0, ',', ' ') }} XOF</td>
                            <td>{{ $property->status }}</td>
                            <td>
                                {{ $property->availabilityBlocks()->count() > 0 || $property->rateRules()->count() > 0 ? __('messages.admin.active_rules') : __('messages.admin.no_rules_short') }}
                            </td>
                            <td>
                                <a class="btn btn-ghost btn-small" href="{{ route('admin.properties.edit', $property) }}">{{ __('messages.admin.edit') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="empty-state-inline">{{ __('messages.admin.no_properties') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
