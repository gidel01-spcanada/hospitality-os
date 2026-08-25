@extends('layouts.admin')

@section('title', __('messages.admin.edit_property'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ $property->name }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.editor_description') }}</p>
    </div>

    <x-card>
        @if (session('success'))<div class="reservation-success">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="form-alert form-alert-error">@foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach</div>@endif

        <div class="property-editor-tabs" data-property-tabs>
            <div class="property-tab-list" role="tablist" aria-label="Property editor sections">
                @foreach (['general' => __('messages.admin.general_information'), 'photos' => __('messages.admin.photos'), 'rules' => __('messages.admin.pricing_rules'), 'features' => __('messages.admin.features'), 'availability' => __('messages.admin.availability'), 'calendars' => __('messages.admin.calendars')] as $tab => $label)
                    <button type="button" class="property-tab {{ $loop->first ? 'is-active' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="property-panel-{{ $tab }}" data-property-tab="{{ $tab }}">{{ $label }}</button>
                @endforeach
            </div>

            <div id="property-panel-general" class="property-tab-panel is-active" role="tabpanel" data-property-panel="general">
                <div class="admin-panel">
                    <h2>{{ __('messages.admin.general_information') }}</h2>
                    <form method="POST" action="{{ route('admin.properties.update', $property) }}" data-property-editor-form>
                        @csrf @method('PUT')
                        <div class="admin-form-grid">
                            <label><span>{{ __('messages.admin.establishment') }}</span><select name="establishment_id" required>@foreach ($establishments as $establishment)<option value="{{ $establishment->id }}" @selected(old('establishment_id', $property->establishment_id) == $establishment->id)>{{ $establishment->name }}</option>@endforeach</select></label>
                            <label><span>{{ __('messages.admin.property_name') }}</span><input name="name" value="{{ old('name', $property->name) }}" required></label>
                            <label><span>{{ __('messages.admin.property_type') }}</span><select name="property_type" required>@foreach (['apartment' => 'Appartement', 'house' => 'Maison', 'villa' => 'Villa', 'studio' => 'Studio', 'room' => 'Chambre', 'other' => 'Autre'] as $value => $label)<option value="{{ $value }}" @selected(old('property_type', $property->property_type ?: 'apartment') === $value)>{{ $label }}</option>@endforeach</select></label>
                            <label><span>{{ __('messages.admin.slug') }}</span><input name="slug" value="{{ old('slug', $property->slug) }}" required></label>
                        </div>
                        <div class="admin-form-grid">
                            <label><span>{{ __('messages.admin.nightly_rate_xof') }}</span><input type="number" step="0.01" name="nightly_rate_xof" value="{{ old('nightly_rate_xof', $property->nightly_rate_xof) }}" required></label>
                            <label><span>{{ __('messages.admin.nightly_rate_eur') }}</span><input type="number" step="0.01" name="nightly_rate_eur" value="{{ old('nightly_rate_eur', $property->nightly_rate_eur) }}" required></label>
                            <label><span>{{ __('messages.admin.minimum_stay') }}</span><input type="number" name="minimum_stay" min="1" value="{{ old('minimum_stay', $property->minimum_stay) }}" required></label>
                            <label><span>{{ __('messages.admin.maximum_guests') }}</span><input type="number" name="max_guests" min="1" value="{{ old('max_guests', $property->max_guests) }}" required></label>
                            <label><span>{{ __('messages.admin.status') }}</span><select name="status"><option value="draft" @selected(old('status', $property->status) === 'draft')>{{ __('messages.admin.draft') }}</option><option value="published" @selected(old('status', $property->status) === 'published')>{{ __('messages.admin.published') }}</option><option value="archived" @selected(old('status', $property->status) === 'archived')>{{ __('messages.admin.archived') }}</option></select></label>
                        </div>
                        <div class="language-editor">
                            <h3>{{ __('messages.admin.translated_content_heading') }}</h3>
                            <div class="language-tabs" role="tablist" aria-label="{{ __('messages.admin.translated_languages') }}">
                                @foreach (['fr' => 'Français', 'en' => 'English'] as $locale => $language)
                                    <button type="button" class="language-tab {{ $loop->first ? 'is-active' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="property-language-{{ $locale }}" data-language-tab="{{ $locale }}">{{ $language }}</button>
                                @endforeach
                            </div>
                            @foreach (['fr' => 'Français', 'en' => 'English'] as $locale => $language)
                                @php $translation = $property->translations->firstWhere('locale', $locale); @endphp
                                <div id="property-language-{{ $locale }}" class="language-panel {{ $loop->first ? 'is-active' : '' }}" role="tabpanel" data-language-panel="{{ $locale }}" {{ $loop->first ? '' : 'hidden' }}>
                                    <div class="admin-form-grid">
                                        <label><span>{{ __('messages.admin.name') }}</span><input name="translations[{{ $locale }}][name]" value="{{ old('translations.' . $locale . '.name', $translation?->name) }}"></label>
                                        <label class="full-width"><span>{{ __('messages.admin.summary') }}</span><textarea name="translations[{{ $locale }}][summary]" rows="2">{{ old('translations.' . $locale . '.summary', $translation?->summary) }}</textarea></label>
                                        <label class="full-width"><span>{{ __('messages.admin.description') }}</span><textarea name="translations[{{ $locale }}][description]" rows="4">{{ old('translations.' . $locale . '.description', $translation?->description) }}</textarea></label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_general') }}</button></div>
                    </form>
                </div>
            </div>

            <div id="property-panel-photos" class="property-tab-panel" role="tabpanel" data-property-panel="photos" hidden>
                <div class="admin-panel"><h2>{{ __('messages.admin.photos') }}</h2>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.properties.images.upload', $property) }}" class="photo-upload-form" data-property-editor-form>
                        @csrf
                        <label><span>{{ __('messages.admin.upload_photos') }}</span><input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
                        <small>{{ __('messages.admin.upload_photo_help') }}</small>
                        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.upload') }}</button></div>
                    </form>
                    @if ($property->images->isNotEmpty())
                        <form method="POST" action="{{ route('admin.properties.images.update', $property) }}" class="photo-order-form">@csrf @method('PUT')<p class="form-help">{{ __('messages.admin.drag_photo_help') }}</p><div class="admin-photo-list" data-photo-sortable>@foreach ($property->images->sortBy('sort_order') as $image)<div class="admin-photo-row" draggable="true" data-photo-id="{{ $image->id }}"><span class="photo-drag-handle" aria-hidden="true">&#8942;&#8942;</span><img src="{{ asset($image->file_path) }}" alt="{{ $property->name }} photo"><input type="hidden" name="image_order[{{ $image->id }}]" value="{{ $loop->iteration }}" data-photo-order><label><span>{{ __('messages.admin.room_or_area') }}</span><input type="text" name="image_tags[{{ $image->id }}]" value="{{ old('image_tags.' . $image->id, $image->room_tag) }}" maxlength="100" placeholder="Living room, bedroom 1..."></label><label class="checkbox-field"><input type="radio" name="cover_image_id" value="{{ $image->id }}" @checked($image->is_cover || (!$property->images->contains('is_cover', true) && $loop->first))><span>{{ __('messages.admin.use_for_cards') }}</span></label><button type="submit" class="photo-remove-button" name="remove_image_id" value="{{ $image->id }}">{{ __('messages.admin.remove_photo') }}</button></div>@endforeach</div><p class="photo-order-status" data-photo-order-status hidden>{{ __('messages.admin.photo_order_pending') }}</p><div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_photos') }}</button></div></form>
                    @else<p class="form-help">No photos have been imported for this property yet.</p>@endif
                </div>
            </div>

            <div id="property-panel-rules" class="property-tab-panel" role="tabpanel" data-property-panel="rules" hidden>
                <div class="admin-panel"><h2>{{ __('messages.admin.pricing_rules') }}</h2><form method="POST" action="{{ route('admin.properties.price-rules.store', $property) }}">@csrf<div class="admin-form-grid compact"><label><span>{{ __('messages.admin.start') }}</span><input type="date" name="effective_from" required></label><label><span>{{ __('messages.admin.end') }}</span><input type="date" name="effective_to"></label><label><span>{{ __('messages.admin.rate_xof') }}</span><input type="number" step="0.01" name="nightly_rate_xof" value="{{ $property->nightly_rate_xof }}" required></label><label><span>{{ __('messages.admin.rate_eur') }}</span><input type="number" step="0.01" name="nightly_rate_eur" value="{{ $property->nightly_rate_eur }}" required></label><label><span>{{ __('messages.admin.minimum_stay') }}</span><input type="number" name="minimum_stay" min="1" value="{{ $property->minimum_stay }}" required></label><label><span>{{ __('messages.admin.rule_type') }}</span><input name="rule_type" value="standard" required></label></div><div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.add_rule') }}</button></div></form><div class="admin-record-list"><h3>{{ __('messages.admin.created_rules') }}</h3>@forelse ($property->rateRules->sortByDesc('effective_from') as $rule)<div class="admin-record-item"><strong>{{ $rule->rule_type }}</strong><span>{{ $rule->effective_from }}{{ $rule->effective_to ? ' → ' . $rule->effective_to : '' }}</span><span>{{ number_format((float) $rule->nightly_rate_xof, 0, ',', ' ') }} XOF</span></div>@empty<p class="form-help">{{ __('messages.admin.no_rules') }}</p>@endforelse</div></div>
            </div>

            <div id="property-panel-features" class="property-tab-panel" role="tabpanel" data-property-panel="features" hidden>
                <div class="admin-panel"><h2>{{ __('messages.admin.features') }}</h2><form method="POST" action="{{ route('admin.properties.features.store', $property) }}">@csrf<div class="admin-form-grid compact"><label class="full-width"><span>{{ __('messages.admin.feature_name') }}</span><input name="name" placeholder="Private pool, concierge service..." required></label><label class="full-width"><span>{{ __('messages.admin.description') }}</span><textarea name="description" rows="3"></textarea></label><label><span>{{ __('messages.admin.cost_xof') }}</span><input type="number" step="0.01" min="0" name="cost_xof" value="0" required></label><label class="checkbox-field"><input type="checkbox" name="is_active" value="1" checked><span>{{ __('messages.admin.active') }}</span></label></div><div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.add_feature') }}</button></div></form><div class="admin-record-list"><h3>{{ __('messages.admin.selected_features') }}</h3>@forelse ($property->features as $feature)<div class="admin-record-item"><strong>{{ $feature->name }}</strong><span>{{ $feature->is_active ? __('messages.admin.active') : __('messages.admin.inactive') }}</span><span>{{ $feature->cost_xof ? number_format((float) $feature->cost_xof, 0, ',', ' ') . ' XOF' : __('messages.admin.included') }}</span></div>@empty<p class="form-help">{{ __('messages.admin.no_features') }}</p>@endforelse</div></div>
            </div>

            <div id="property-panel-availability" class="property-tab-panel" role="tabpanel" data-property-panel="availability" hidden>
                @php
                    $blockedRanges = $property->availabilityBlocks->map(fn ($block) => [$block->start_date->toDateString(), $block->end_date->toDateString()])->values();
                    $reservedRanges = $property->reservations->where('status', '!=', 'cancelled')->map(fn ($reservation) => [$reservation->check_in->toDateString(), $reservation->check_out->toDateString()])->values();
                    $externalRanges = $property->calendarFeeds->where('is_enabled', true)->flatMap(fn ($feed) => $feed->events->map(fn ($event) => [$event->start_date->toDateString(), $event->end_date->toDateString()]))->values();
                @endphp
                <div class="admin-panel"><h2>{{ __('messages.admin.availability') }}</h2><div class="availability-legend"><span class="availability-key availability-available">{{ __('messages.admin.available') }}</span><span class="availability-key availability-blocked">{{ __('messages.admin.blocked_reserved') }}</span><span class="availability-key availability-external">{{ __('messages.admin.external_bookings') }}</span><span class="availability-key availability-past">{{ __('messages.admin.past') }}</span></div><div class="availability-calendar" data-availability-calendar data-blocked="{{ $blockedRanges->toJson() }}" data-reserved="{{ $reservedRanges->toJson() }}" data-external="{{ $externalRanges->toJson() }}"></div><form method="POST" action="{{ route('admin.properties.availability.store', $property) }}">@csrf<div class="admin-form-grid compact"><label><span>{{ __('messages.admin.blocked_from') }}</span><input type="date" name="start_date" required></label><label><span>{{ __('messages.admin.blocked_to') }}</span><input type="date" name="end_date" required></label><label class="full-width"><span>{{ __('messages.admin.reason') }}</span><input name="reason" placeholder="Maintenance, renovation..." required></label></div><div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.block_dates') }}</button></div></form><div class="admin-record-list"><h3>{{ __('messages.admin.current_blocks') }}</h3>@forelse ($property->availabilityBlocks->sortBy('start_date') as $block)<div class="admin-record-item"><strong>{{ $block->start_date }} → {{ $block->end_date }}</strong><span>{{ $block->reason }}</span><form method="POST" action="{{ route('admin.properties.availability.destroy', [$property, $block]) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost btn-small">{{ __('messages.admin.delete') }}</button></form></div>@empty<p class="form-help">{{ __('messages.admin.no_blocks') }}</p>@endforelse</div></div>
            </div>

            <div id="property-panel-calendars" class="property-tab-panel" role="tabpanel" data-property-panel="calendars" hidden>
                <div class="admin-panel"><h2>{{ __('messages.admin.calendars_impacting') }}</h2><form method="POST" action="{{ route('admin.properties.calendar.store', $property) }}">@csrf<div class="admin-form-grid"><label class="full-width"><span>{{ __('messages.admin.calendar_name') }}</span><input name="name" placeholder="Booking or Airbnb feed" required></label><label class="full-width"><span>{{ __('messages.admin.ics_url') }}</span><input type="url" name="url" placeholder="https://example.com/calendar.ics" required></label><label class="checkbox-field full-width"><input type="checkbox" name="is_enabled" value="1" checked><span>{{ __('messages.admin.enable_automatically') }}</span></label></div><div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_calendar_feed') }}</button></div></form><div class="admin-record-list"><h3>{{ __('messages.admin.connected_calendars') }}</h3>@forelse ($property->calendarFeeds as $feed)<div class="admin-record-item"><form method="POST" action="{{ route('admin.properties.calendar.update', [$property, $feed]) }}">@csrf @method('PUT')<label><span>{{ __('messages.admin.calendar_name') }}</span><input name="name" value="{{ $feed->name }}" required></label><label><span>{{ __('messages.admin.ics_url') }}</span><input type="url" name="url" value="{{ $feed->url }}" required></label><label class="checkbox-field"><input type="checkbox" name="is_enabled" value="1" @checked($feed->is_enabled)><span>{{ __('messages.admin.enable_automatically') }}</span></label><button class="btn btn-primary btn-small" type="submit">{{ __('messages.admin.update') }}</button></form><span>{{ $feed->status }}</span><form method="POST" action="{{ route('admin.properties.calendar.sync', [$property, $feed]) }}">@csrf<button class="btn btn-ghost btn-small" type="submit">{{ __('messages.admin.sync') }}</button></form><form method="POST" action="{{ route('admin.properties.calendar.destroy', [$property, $feed]) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-small" type="submit">{{ __('messages.admin.delete') }}</button></form></div>@empty<p class="form-help">{{ __('messages.admin.no_calendars') }}</p>@endforelse</div><div class="form-actions"><label class="full-width"><span>{{ __('messages.admin.external_calendar_url') }}</span><input type="url" readonly value="{{ route('calendar.public-export', [$property, 'token' => $property->calendar_export_token]) }}"></label><p class="form-help">{{ __('messages.admin.external_calendar_help') }}</p><a class="btn btn-ghost" href="{{ route('admin.properties.calendar.export', $property) }}">{{ __('messages.admin.export_calendar') }}</a></div></div>
            </div>
        </div>
    </x-card>
@endsection
