@extends('layouts.admin')

@section('title', __('messages.admin.edit_property'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ $property->name }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.editor_description') }}</p>
        <div class="admin-page-actions">
            <a class="btn btn-primary" href="{{ route('admin.reservations.create', ['property_id' => $property->id]) }}">{{ __('messages.admin.create_reservation_for_property') }}</a>
        </div>
    </div>

    <x-card class="property-editor-card">
        @if (session('success'))<div class="reservation-success">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="form-alert form-alert-error">@foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach</div>@endif

        <div class="property-editor-tabs" data-property-tabs>
            <div class="property-tab-list" role="tablist" aria-label="{{ __('messages.admin.property_editor_sections') }}">
                @foreach (['general' => __('messages.admin.general_information'), 'copy' => 'Assistant rédaction', 'photos' => __('messages.admin.photos'), 'amenities' => __('messages.admin.amenities'), 'features' => __('messages.admin.features'), 'rules' => __('messages.admin.pricing_rules'), 'availability' => __('messages.admin.availability'), 'calendars' => __('messages.admin.calendars')] as $tab => $label)
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
                            <label><span>{{ __('messages.admin.property_type') }}</span><select name="property_type" required>@foreach (['apartment', 'house', 'villa', 'studio', 'room', 'other'] as $value)<option value="{{ $value }}" @selected(old('property_type', $property->property_type ?: 'apartment') === $value)>{{ __('messages.admin.type_' . $value) }}</option>@endforeach</select></label>
                            <label><span>{{ __('messages.admin.slug') }}</span><input name="slug" value="{{ old('slug', $property->slug) }}" required></label>
                        </div>
                        <div class="admin-form-grid">
                            <label><span>{{ __('messages.admin.nightly_rate_xof') }}</span><input type="number" step="0.01" name="nightly_rate_xof" value="{{ old('nightly_rate_xof', $property->nightly_rate_xof) }}" required></label>
                            <label><span>{{ __('messages.admin.minimum_stay') }}</span><input type="number" name="minimum_stay" min="1" value="{{ old('minimum_stay', $property->minimum_stay) }}" required></label>
                            <label><span>{{ __('messages.admin.maximum_guests') }}</span><input type="number" name="max_guests" min="1" value="{{ old('max_guests', $property->max_guests) }}" required></label>
                            <label><span>{{ __('messages.admin.bedrooms') }}</span><input type="number" name="bedrooms" min="0" value="{{ old('bedrooms', $property->bedrooms) }}" required></label>
                            <label><span>{{ __('messages.admin.bathrooms') }}</span><input type="number" name="bathrooms" min="0" value="{{ old('bathrooms', $property->bathrooms) }}" required></label>
                            <label><span>{{ __('messages.admin.beds') }}</span><input type="number" name="beds" min="0" value="{{ old('beds', $property->beds) }}" required></label>
                            <label><span>{{ __('messages.admin.area') }}</span><input type="number" step="0.01" min="0" name="area" value="{{ old('area', $property->area) }}"></label>
                            <label><span>{{ __('messages.admin.area_unit') }}</span><select name="area_unit"><option value="m2" @selected(old('area_unit', $property->area_unit ?: 'm2') === 'm2')>m²</option><option value="ft2" @selected(old('area_unit', $property->area_unit) === 'ft2')>ft²</option></select></label>
                            <label><span>{{ __('messages.admin.floor') }}</span><input type="number" name="floor" min="0" max="200" value="{{ old('floor', $property->floor) }}"></label>
                            <label><span>{{ __('messages.admin.status') }}</span><select name="status"><option value="draft" @selected(old('status', $property->status) === 'draft')>{{ __('messages.admin.draft') }}</option><option value="published" @selected(old('status', $property->status) === 'published')>{{ __('messages.admin.published') }}</option><option value="archived" @selected(old('status', $property->status) === 'archived')>{{ __('messages.admin.archived') }}</option></select></label>
                            <label class="checkbox-field"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $property->is_active ?? true))><span>{{ __('messages.admin.property_active') }}</span></label>
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
                        <div class="property-media-editor">
                            <h3>{{ __('messages.admin.video_media') }}</h3>
                            <label class="full-width"><span>{{ __('messages.admin.video_urls') }}</span><input type="url" name="video_urls" value="{{ old('video_urls', implode(', ', $property->video_urls ?? [])) }}" placeholder="https://www.youtube.com/watch?v=..." inputmode="url"></label>
                            <p class="form-help">{{ __('messages.admin.video_urls_help') }}</p>
                        </div>
                        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_general') }}</button></div>
                    </form>
                </div>
            </div>

            <div id="property-panel-copy" class="property-tab-panel" role="tabpanel" data-property-panel="copy" hidden>
                <div class="admin-panel">
                    <h2>Assistant rédaction</h2>
                    <section class="ai-copy-assistant" data-ai-copy-assistant data-endpoint="{{ route('admin.properties.improve-copy', $property) }}" data-loading-label="{{ __('messages.ai_copy.loading') }}" data-ready-label="{{ __('messages.ai_copy.ready') }}" data-error-label="{{ __('messages.ai_copy.error') }}" data-applied-label="{{ __('messages.ai_copy.applied') }}" data-discarded-label="{{ __('messages.ai_copy.discarded') }}">
                        <div class="ai-copy-heading">
                            <div>
                                <h3>{{ __('messages.ai_copy.title') }}</h3>
                                <p class="form-help">{{ __('messages.ai_copy.help') }}</p>
                            </div>
                            <button type="button" class="btn btn-ghost" data-ai-copy-generate @disabled(blank(config('services.property_copy.api_key')))>{{ __('messages.ai_copy.generate') }}</button>
                        </div>
                        @if (blank(config('services.property_copy.api_key')))<p class="form-help">{{ __('messages.ai_copy.not_configured') }}</p>@endif
                        <p class="ai-copy-status" data-ai-copy-status role="status" aria-live="polite"></p>
                        <div class="ai-copy-preview" data-ai-copy-preview hidden>
                            <p class="form-help">{{ __('messages.ai_copy.preview_help') }}</p>
                            <div class="ai-copy-preview-grid">
                                @foreach (['fr' => 'Français', 'en' => 'English'] as $locale => $language)
                                    <div>
                                        <h4>{{ $language }}</h4>
                                        <label><span>{{ __('messages.admin.summary') }}</span><textarea rows="2" readonly data-ai-copy-result="summary_{{ $locale }}"></textarea></label>
                                        <label><span>{{ __('messages.admin.description') }}</span><textarea rows="5" readonly data-ai-copy-result="description_{{ $locale }}"></textarea></label>
                                    </div>
                                @endforeach
                            </div>
                            <div class="form-actions"><button type="button" class="btn btn-primary" data-ai-copy-apply>{{ __('messages.ai_copy.apply') }}</button><button type="button" class="btn btn-ghost" data-ai-copy-discard>{{ __('messages.ai_copy.discard') }}</button></div>
                        </div>
                    </section>
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
                    <p id="photo-reorder-help" class="sr-only">{{ __('messages.admin.reorder_photo_help') }}</p>
                    @if ($property->images->isNotEmpty())
                        <form method="POST" action="{{ route('admin.properties.images.update', $property) }}" class="photo-order-form">@csrf @method('PUT')<p class="form-help">{{ __('messages.admin.drag_photo_help') }}</p><div class="admin-photo-list-head" aria-hidden="true"><span>#</span><span></span><span>{{ __('messages.admin.photo_column') }}</span><span>{{ __('messages.admin.room_or_area') }}</span><span>{{ __('messages.admin.cover_column') }}</span><span></span></div><div class="admin-photo-list" data-photo-sortable>@foreach ($property->images->sortBy('sort_order') as $image)<div class="admin-photo-row" draggable="true" data-photo-id="{{ $image->id }}"><span class="photo-position" data-photo-position aria-hidden="true">{{ $loop->iteration }}</span><span class="photo-drag-handle" aria-hidden="true">&#8942;&#8942;</span><img src="{{ asset($image->file_path) }}" alt="{{ $property->name }} photo"><input type="hidden" name="image_order[{{ $image->id }}]" value="{{ $loop->iteration }}" data-photo-order><input type="text" name="image_tags[{{ $image->id }}]" value="{{ old('image_tags.' . $image->id, $image->room_tag) }}" maxlength="100" placeholder="Living room, bedroom 1..." aria-label="{{ __('messages.admin.room_or_area') }}"><label class="photo-cover-toggle" title="{{ __('messages.admin.use_for_cards') }}"><input type="radio" name="cover_image_id" value="{{ $image->id }}" @checked($image->is_cover || (!$property->images->contains('is_cover', true) && $loop->first))><span class="photo-cover-icon" aria-hidden="true">&#9733;</span><span class="sr-only">{{ __('messages.admin.use_for_cards') }}</span></label><button type="submit" class="photo-remove-button" name="remove_image_id" value="{{ $image->id }}" title="{{ __('messages.admin.remove_photo') }}" aria-label="{{ __('messages.admin.remove_photo') }}"><span aria-hidden="true">&#10005;</span></button></div>@endforeach</div><p class="photo-order-status" data-photo-order-status hidden>{{ __('messages.admin.photo_order_pending') }}</p><div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_photos') }}</button></div></form>
                    @else<p class="form-help">{{ __('messages.admin.no_photos') }}</p>@endif
                </div>
            </div>

            <div id="property-panel-amenities" class="property-tab-panel" role="tabpanel" data-property-panel="amenities" hidden>
                <div class="admin-panel">
                    <h2>{{ __('messages.admin.amenities') }}</h2>
                    <form method="POST" action="{{ route('admin.properties.amenities.update', $property) }}" data-property-editor-form>
                        @csrf @method('PUT')
                        @php $selectedAmenityIds = old('amenity_ids', $property->amenities->pluck('id')->all()); @endphp
                        @forelse ($amenities->groupBy(fn ($amenity) => $amenity->category?->id) as $groupedAmenities)
                            @php $category = $groupedAmenities->first()->category; @endphp
                            <fieldset class="amenity-picker-group">
                                <legend>{{ $category ? (app()->getLocale() === 'fr' ? $category->name_fr : $category->name_en) : __('messages.admin.amenities') }}</legend>
                                <div class="amenity-picker-options">
                                    @foreach ($groupedAmenities as $amenity)
                                        <label class="checkbox-field">
                                            <input type="checkbox" name="amenity_ids[]" value="{{ $amenity->id }}" @checked(in_array($amenity->id, array_map('intval', (array) $selectedAmenityIds), true))>
                                            <span>{{ $amenity->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @empty
                            <p class="form-help">{{ __('messages.admin.no_amenities') }}</p>
                        @endforelse
                        <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_amenities') }}</button></div>
                    </form>
                </div>
            </div>

            <div id="property-panel-features" class="property-tab-panel" role="tabpanel" data-property-panel="features" hidden>
                <div class="admin-panel">
                    <div class="admin-panel-heading"><h2>{{ __('messages.admin.features') }}</h2><a class="btn btn-primary" href="{{ route('admin.properties.features.create', $property) }}">{{ __('messages.admin.add_feature') }}</a></div>
                    <div class="admin-record-list property-feature-editor-list">
                        <h3>{{ __('messages.admin.selected_features') }}</h3>
                        @forelse ($property->features as $feature)
                            <article class="property-feature-editor">
                                <form method="POST" action="{{ route('admin.properties.features.update', [$property, $feature]) }}">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="active_tab" value="features">
                                    <div class="admin-form-grid compact">
                                        <label><span>{{ __('messages.admin.feature_name') }}</span><input name="name" value="{{ $feature->name }}" required></label>
                                        <label><span>{{ __('messages.admin.cost_xof') }}</span><input type="number" step="0.01" min="0" name="cost_xof" value="{{ $feature->cost_xof }}" required></label>
                                        <label class="full-width"><span>{{ __('messages.admin.description') }}</span><textarea name="description" rows="2">{{ $feature->description }}</textarea></label>
                                        <label class="checkbox-field"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($feature->is_active)><span>{{ __('messages.admin.active') }}</span></label>
                                    </div>
                                    <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save_feature') }}</button></div>
                                </form>
                                <form method="POST" action="{{ route('admin.properties.features.destroy', [$property, $feature]) }}" class="property-feature-delete" data-confirm-message="{{ __('messages.admin.delete_feature_confirmation', ['name' => $feature->name]) }}">
                                    @csrf @method('DELETE')
                                    <input type="hidden" name="active_tab" value="features">
                                    <button class="btn btn-danger" type="submit">{{ __('messages.admin.delete') }}</button>
                                </form>
                            </article>
                        @empty
                            <p class="form-help">{{ __('messages.admin.no_features') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div id="property-panel-rules" class="property-tab-panel" role="tabpanel" data-property-panel="rules" hidden>                <div class="admin-panel"><div class="admin-panel-heading"><h2>{{ __('messages.admin.pricing_rules') }}</h2><a class="btn btn-primary" href="{{ route('admin.properties.price-rules.create', $property) }}">{{ __('messages.admin.add_rule') }}</a></div><div class="admin-record-list"><h3>{{ __('messages.admin.created_rules') }}</h3>@forelse ($property->rateRules->sortByDesc('effective_from') as $rule)<div class="admin-record-item"><strong>{{ $rule->rule_type }}</strong><span>{{ $rule->effective_from }}{{ $rule->effective_to ? ' → ' . $rule->effective_to : '' }}</span><span>{{ number_format((float) $rule->nightly_rate_xof, 0, ',', ' ') }} XOF</span></div>@empty<p class="form-help">{{ __('messages.admin.no_rules') }}</p>@endforelse</div></div>
            </div>

            <div id="property-panel-availability" class="property-tab-panel" role="tabpanel" data-property-panel="availability" hidden>
                @php
                    $blockedRanges = $property->availabilityBlocks->map(fn ($block) => [$block->start_date->toDateString(), $block->end_date->toDateString()])->values();
                    $reservedRanges = $property->reservations->where('status', '!=', 'cancelled')->map(fn ($reservation) => [$reservation->check_in->toDateString(), $reservation->check_out->toDateString()])->values();
                    $externalRanges = $property->calendarFeeds->where('is_enabled', true)->flatMap(fn ($feed) => $feed->events->map(fn ($event) => [$event->start_date->toDateString(), $event->end_date->toDateString()]))->values();
                @endphp
                <div class="admin-panel"><div class="admin-panel-heading"><h2>{{ __('messages.admin.availability') }}</h2><a class="btn btn-primary" href="{{ route('admin.properties.availability.create', $property) }}">{{ __('messages.admin.block_dates') }}</a></div><div class="availability-legend"><span class="availability-key availability-available">{{ __('messages.admin.available') }}</span><span class="availability-key availability-blocked">{{ __('messages.admin.blocked_reserved') }}</span><span class="availability-key availability-external">{{ __('messages.admin.external_bookings') }}</span><span class="availability-key availability-past">{{ __('messages.admin.past') }}</span></div><div class="availability-calendar" data-availability-calendar data-blocked="{{ $blockedRanges->toJson() }}" data-reserved="{{ $reservedRanges->toJson() }}" data-external="{{ $externalRanges->toJson() }}"></div><div class="admin-record-list"><h3>{{ __('messages.admin.current_blocks') }}</h3>@forelse ($property->availabilityBlocks->sortBy('start_date') as $block)<div class="admin-record-item"><strong>{{ $block->start_date }} → {{ $block->end_date }}</strong><span>{{ $block->reason }}</span><form method="POST" action="{{ route('admin.properties.availability.destroy', [$property, $block]) }}">@csrf @method('DELETE')<button type="submit" class="btn btn-ghost btn-small">{{ __('messages.admin.delete') }}</button></form></div>@empty<p class="form-help">{{ __('messages.admin.no_blocks') }}</p>@endforelse</div></div>
            </div>

            <div id="property-panel-calendars" class="property-tab-panel" role="tabpanel" data-property-panel="calendars" hidden>
                <div class="admin-panel calendar-panel">
                    <div class="calendar-panel-header">
                        <div>
                            <h2>{{ __('messages.admin.calendars_impacting') }}</h2>
                            <p class="form-help">{{ __('messages.admin.add_calendar_feed_help') }}</p>
                        </div>
                        <a class="btn btn-primary" href="{{ route('admin.properties.calendar.create', $property) }}">{{ __('messages.admin.add_calendar_feed_title') }}</a>
                    </div>

                    <div class="calendar-feed-list">
                        @forelse ($property->calendarFeeds as $feed)
                            <div class="calendar-feed-row">
                                <div class="calendar-feed-info">
                                    <div class="calendar-feed-name-row">
                                        <strong>{{ $feed->name }}</strong>
                                        <span class="calendar-feed-provider">{{ ucfirst($feed->provider) }}</span>
                                        <span class="calendar-status-badge calendar-status-{{ $feed->status }}">{{ __('messages.admin.calendar_feed_status_' . $feed->status) }}</span>
                                    </div>
                                    <p class="calendar-feed-meta">
                                        {{ $feed->last_successful_sync_at ? __('messages.admin.calendar_last_sync', ['date' => $feed->last_successful_sync_at->format('d/m/Y H:i')]) : __('messages.admin.never_synced') }}
                                        @if ($feed->last_sync_error)
                                            · <span class="calendar-feed-error">{{ __('messages.admin.calendar_last_error', ['message' => $feed->last_sync_error]) }}</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="calendar-feed-actions">
                                    <form method="POST" action="{{ route('admin.properties.calendar.sync', [$property, $feed]) }}">
                                        @csrf
                                        <button class="btn btn-ghost btn-small" type="submit">{{ __('messages.admin.sync') }}</button>
                                    </form>
                                    <a class="btn btn-ghost btn-small" href="{{ route('admin.properties.calendar.edit', [$property, $feed]) }}">{{ __('messages.admin.edit') }}</a>
                                    <form method="POST" action="{{ route('admin.properties.calendar.destroy', [$property, $feed]) }}" data-confirm-message="{{ __('messages.admin.delete_calendar_confirmation', ['name' => $feed->name]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-ghost btn-small" type="submit">{{ __('messages.admin.delete') }}</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="form-help">{{ __('messages.admin.no_calendars') }}</p>
                        @endforelse
                    </div>
                </div>

                <div class="admin-panel calendar-share-panel">
                    <h2>{{ __('messages.admin.share_calendar_title') }}</h2>
                    <p class="form-help">{{ __('messages.admin.external_calendar_help') }}</p>
                    <div class="calendar-share-row">
                        <input type="url" readonly value="{{ route('calendar.public-export', [$property, 'token' => $property->calendar_export_token]) }}">
                        <button type="button" class="btn btn-ghost" data-copy-text="{{ route('calendar.public-export', [$property, 'token' => $property->calendar_export_token]) }}" data-copy-success="{{ __('messages.admin.calendar_url_copied') }}" data-copy-error="{{ __('messages.admin.calendar_url_copy_failed') }}">{{ __('messages.admin.copy_calendar_url') }}</button>
                        <a class="btn btn-ghost" href="{{ route('admin.properties.calendar.export', $property) }}">{{ __('messages.admin.export_calendar') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </x-card>
@endsection
