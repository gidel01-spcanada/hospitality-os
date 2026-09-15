@extends('layouts.admin')

@section('title', __('messages.admin.edit_establishment'))

@section('content')
    <div class="admin-page-header">
        <h1 class="admin-page-title">{{ $establishment->name }}</h1>
        <p class="admin-page-description">{{ __('messages.admin.establishment_editor_description') }}</p>
    </div>
    <x-card>
        @php
            $countries = [
                'BJ' => 'Bénin', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'CM' => 'Cameroun', 'CF' => 'Centrafrique', 'TD' => 'Tchad',
                'KM' => 'Comores', 'CD' => 'Congo-Kinshasa', 'CG' => 'Congo-Brazzaville', 'CI' => 'Côte d’Ivoire', 'DJ' => 'Djibouti',
                'GQ' => 'Guinée équatoriale', 'ER' => 'Érythrée', 'ET' => 'Éthiopie', 'GA' => 'Gabon', 'GM' => 'Gambie', 'GH' => 'Ghana',
                'GN' => 'Guinée', 'GW' => 'Guinée-Bissau', 'KE' => 'Kenya', 'LR' => 'Liberia', 'MG' => 'Madagascar', 'MW' => 'Malawi',
                'ML' => 'Mali', 'MR' => 'Mauritanie', 'MU' => 'Maurice', 'MA' => 'Maroc', 'MZ' => 'Mozambique', 'NA' => 'Namibie',
                'NE' => 'Niger', 'NG' => 'Nigeria', 'RW' => 'Rwanda', 'SN' => 'Sénégal', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone',
                'SO' => 'Somalie', 'ZA' => 'Afrique du Sud', 'SS' => 'Soudan du Sud', 'SD' => 'Soudan', 'TZ' => 'Tanzanie', 'TG' => 'Togo',
                'TN' => 'Tunisie', 'UG' => 'Ouganda', 'ZM' => 'Zambie', 'ZW' => 'Zimbabwe',
            ];
            $currencies = [
                'XOF' => 'Franc CFA BCEAO', 'XAF' => 'Franc CFA BEAC', 'DZD' => 'Dinar algérien', 'AOA' => 'Kwanza angolais',
                'BWP' => 'Pula botswanais', 'BIF' => 'Franc burundais', 'CVE' => 'Escudo cap-verdien', 'CDF' => 'Franc congolais',
                'DJF' => 'Franc djiboutien', 'EGP' => 'Livre égyptienne', 'ETB' => 'Birr éthiopien', 'GMD' => 'Dalasi gambien',
                'GHS' => 'Cedi ghanéen', 'GNF' => 'Franc guinéen', 'KES' => 'Shilling kényan', 'LRD' => 'Dollar libérien',
                'MGA' => 'Ariary malgache', 'MWK' => 'Kwacha malawite', 'MRU' => 'Ouguiya mauritanienne', 'MUR' => 'Roupie mauricienne',
                'MAD' => 'Dirham marocain', 'MZN' => 'Metical mozambicain', 'NAD' => 'Dollar namibien', 'NGN' => 'Naira nigérian',
                'RWF' => 'Franc rwandais', 'STN' => 'Dobra santoméen', 'SCR' => 'Roupie seychelloise', 'SLL' => 'Leone sierra-léonais',
                'SOS' => 'Shilling somalien', 'ZAR' => 'Rand sud-africain', 'SSP' => 'Livre sud-soudanaise', 'SDG' => 'Livre soudanaise',
                'SZL' => 'Lilangeni swazi', 'TZS' => 'Shilling tanzanien', 'TND' => 'Dinar tunisien', 'UGX' => 'Shilling ougandais',
                'ZMW' => 'Kwacha zambien', 'ZWL' => 'Dollar zimbabwéen',
                'EUR' => 'Euro', 'USD' => 'Dollar américain', 'GBP' => 'Livre sterling', 'CAD' => 'Dollar canadien',
            ];
        @endphp
        @if ($errors->any())<div class="form-alert form-alert-error">@foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach</div>@endif
        <div class="property-editor-tabs" data-property-tabs>
            <div class="property-tab-list" role="tablist" aria-label="{{ __('messages.admin.establishment_editor_sections') }}">
                @foreach (['general' => __('messages.admin.general_information'), 'online' => __('messages.admin.online_information'), 'taxes' => __('messages.admin.taxes_heading'), 'translations' => __('messages.admin.translations'), 'payments' => __('messages.admin.payment_methods_heading'), 'reviews' => __('messages.admin.reviews_tab'), 'hosts' => __('messages.admin.hosts_tab')] as $tab => $label)
                    <button type="button" class="property-tab {{ $loop->first ? 'is-active' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="establishment-panel-{{ $tab }}" data-property-tab="{{ $tab }}">{{ $label }}</button>
                @endforeach
            </div>
            <form method="POST" enctype="multipart/form-data" action="{{ $establishment->exists ? route('admin.establishments.update', $establishment) : route('admin.establishments.store') }}">
                @csrf @if($establishment->exists) @method('PUT') @endif
                <div id="establishment-panel-general" class="property-tab-panel is-active" role="tabpanel" data-property-panel="general">
                    <div class="form-grid">
                        <div><label for="name">{{ __('messages.admin.form_name') }}</label><input id="name" name="name" value="{{ old('name', $establishment->name) }}" required></div>
                        <div><label for="slug">{{ __('messages.admin.slug') }}</label><input id="slug" name="slug" value="{{ old('slug', $establishment->slug) }}" required></div>
                        <div><label for="country_code">{{ __('messages.admin.country') }}</label><select id="country_code" name="country_code" required>@foreach ($countries as $code => $country)<option value="{{ $code }}" @selected(old('country_code', $establishment->country_code) === $code)>{{ $country }} ({{ $code }})</option>@endforeach</select></div>
                        <div><label for="currency">{{ __('messages.admin.currency') }}</label><select id="currency" name="currency" required>@foreach ($currencies as $code => $currency)<option value="{{ $code }}" @selected(old('currency', $establishment->currency) === $code)>{{ $currency }} ({{ $code }})</option>@endforeach</select></div>
                        <div><label for="secondary_currency">{{ __('messages.admin.secondary_currency') }}</label><select id="secondary_currency" name="secondary_currency"><option value="">{{ __('messages.admin.secondary_currency_none') }}</option>@foreach ($currencies as $code => $currency)<option value="{{ $code }}" @selected(old('secondary_currency', $establishment->secondary_currency) === $code)>{{ $currency }} ({{ $code }})</option>@endforeach</select></div>
                        <div><label for="secondary_currency_rate">{{ __('messages.admin.secondary_currency_rate') }}</label><input id="secondary_currency_rate" type="number" step="0.000001" min="0.000001" name="secondary_currency_rate" value="{{ old('secondary_currency_rate', $establishment->secondary_currency_rate ?? 1) }}"></div>
                        <div class="full-width"><label class="checkbox-field"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $establishment->is_active ?? true))><span>{{ __('messages.admin.establishment_active') }}</span></label></div>
                        <div><label for="city">{{ __('messages.admin.city') }}</label><input id="city" name="city" value="{{ old('city', $establishment->city) }}"></div>
                        <div><label for="address">{{ __('messages.admin.address') }}</label><input id="address" name="address" value="{{ old('address', $establishment->address) }}"></div>
                    </div>
                </div>
                <div id="establishment-panel-online" class="property-tab-panel" role="tabpanel" data-property-panel="online" hidden>
                    <div class="form-grid">
                        <div class="full-width establishment-cover-image-field">
                        <label for="cover_image_upload">{{ __('messages.admin.cover_photo') }}</label>
                        @if ($establishment->cover_image)
                            <img class="establishment-cover-preview" src="{{ asset($establishment->cover_image) }}" alt="{{ $establishment->name }}">
                        @endif
                        <input id="cover_image_upload" type="file" name="cover_image_upload" accept="image/jpeg,image/png,image/webp">
                        @if ($establishment->properties->flatMap->images->isNotEmpty())
                            @php
                                $selectedPropertyImage = $establishment->properties->flatMap->images->firstWhere('file_path', $establishment->cover_image);
                            @endphp
                            <label for="property_image_id">{{ __('messages.admin.select_property_photo') }}</label>
                            <select id="property_image_id" name="property_image_id">
                                <option value="">{{ __('messages.admin.choose_photo') }}</option>
                                @foreach ($establishment->properties as $property)
                                    @foreach ($property->images->sortBy('sort_order') as $image)
                                        <option value="{{ $image->id }}" @selected(old('property_image_id') == $image->id || (!old('property_image_id') && $selectedPropertyImage?->id === $image->id))>{{ $property->name }} — {{ $image->room_tag ?: $image->file_name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        @endif
                        <div class="establishment-cover-image-path">
                            <label for="cover_image">{{ __('messages.admin.existing_url_path') }}</label>
                            <input id="cover_image" type="text" name="cover_image" value="{{ old('cover_image', $establishment->cover_image) }}" placeholder="https://... ou uploads/...">
                        </div></div>
                        <div><label for="email">{{ __('messages.common.email') }}</label><input id="email" type="email" name="email" value="{{ old('email', $establishment->email) }}"></div>
                        <div><label for="phone">{{ __('messages.admin.phone') }}</label><input id="phone" name="phone" value="{{ old('phone', $establishment->phone) }}"></div>
                        <div><label for="latitude">{{ __('messages.admin.latitude') }}</label><input id="latitude" type="number" step="any" name="latitude" value="{{ old('latitude', $establishment->latitude) }}"></div>
                        <div><label for="longitude">{{ __('messages.admin.longitude') }}</label><input id="longitude" type="number" step="any" name="longitude" value="{{ old('longitude', $establishment->longitude) }}"></div>
                        <div class="full-width"><label for="google_maps_url">{{ __('messages.admin.google_maps_url') }}</label><input id="google_maps_url" type="url" name="google_maps_url" value="{{ old('google_maps_url', $establishment->google_maps_url) }}" placeholder="https://maps.google.com/..."></div>
                    </div>
                </div>
                <div id="establishment-panel-taxes" class="property-tab-panel" role="tabpanel" data-property-panel="taxes" hidden>
                    <div class="payment-method-editor">
                        <h2>{{ __('messages.admin.taxes_heading') }}</h2>
                        <div class="form-grid">
                            <div>
                                <label for="vat_percent">{{ __('messages.admin.vat_percent') }}</label>
                                <input id="vat_percent" type="number" step="0.01" min="0" max="100" name="vat_percent" value="{{ old('vat_percent', $establishment->vat_percent ?? 18) }}">
                            </div>
                            <div class="field-group" style="align-self: center;">
                                <label class="checkbox-field" style="margin-top: 1.2rem;">
                                    <input type="checkbox" name="vat_included" value="1" @checked(old('vat_included', $establishment->vat_included ?? true))>
                                    <span>{{ __('messages.admin.vat_included') }}</span>
                                </label>
                            </div>
                            <div>
                                <label for="service_fee_percent">{{ __('messages.admin.service_fee_percent') }}</label>
                                <input id="service_fee_percent" type="number" step="0.01" min="0" max="100" name="service_fee_percent" value="{{ old('service_fee_percent', $establishment->service_fee_percent ?? 10) }}">
                            </div>
                            <div>
                                <label for="city_tax_type">{{ __('messages.admin.city_tax_type') }}</label>
                                <select id="city_tax_type" name="city_tax_type">
                                    <option value="none" @selected(old('city_tax_type', $establishment->city_tax_type ?? 'percent') === 'none')>{{ __('messages.admin.city_tax_type_none') }}</option>
                                    <option value="per_night" @selected(old('city_tax_type', $establishment->city_tax_type ?? 'percent') === 'per_night')>{{ __('messages.admin.city_tax_type_per_night') }}</option>
                                    <option value="per_guest_night" @selected(old('city_tax_type', $establishment->city_tax_type ?? 'percent') === 'per_guest_night')>{{ __('messages.admin.city_tax_type_per_guest_night') }}</option>
                                    <option value="percent" @selected(old('city_tax_type', $establishment->city_tax_type ?? 'percent') === 'percent')>{{ __('messages.admin.city_tax_type_percent') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="city_tax_amount">{{ __('messages.admin.city_tax_amount') }}</label>
                                <input id="city_tax_amount" type="number" step="0.01" min="0" name="city_tax_amount" value="{{ old('city_tax_amount', $establishment->city_tax_amount ?? 5) }}">
                            </div>
                            <p class="form-help full-width">{{ __('messages.admin.taxes_help') }}</p>
                        </div>
                    </div>                    <div class="payment-method-editor" style="margin-top: 1.5rem;">
                        <h2>{{ __('messages.admin.electricity_heading') }}</h2>
                        <div class="form-grid">
                            <div class="full-width">
                                <label class="checkbox-field">
                                    <input type="checkbox" name="electricity_billed_separately" value="1" @checked(old('electricity_billed_separately', $establishment->electricity_billed_separately ?? false))>
                                    <span>{{ __('messages.admin.electricity_billed_separately') }}</span>
                                </label>
                            </div>
                            <div class="full-width">
                                <label for="electricity_policy_note">{{ __('messages.admin.electricity_policy_note') }}</label>
                                <textarea id="electricity_policy_note" name="electricity_policy_note" rows="3" placeholder="{{ __('messages.properties.electricity_note') }}">{{ old('electricity_policy_note', $establishment->electricity_policy_note) }}</textarea>
                            </div>
                            <p class="form-help full-width">{{ __('messages.admin.electricity_policy_help') }}</p>
                        </div>
                    </div>                </div>
                <div id="establishment-panel-payments" class="property-tab-panel" role="tabpanel" data-property-panel="payments" hidden>
                    <div class="payment-method-editor">
                    <h2>{{ __('messages.admin.payment_methods_heading') }}</h2>
                    <div class="form-grid">
                        @foreach (['pay_later' => __('messages.admin.deferred_payment'), 'fedapay' => __('messages.admin.fedapay_sandbox'), 'paypal' => __('messages.admin.paypal_sandbox'), 'interac' => __('messages.admin.interac'), 'wise' => __('messages.admin.wise'), 'revolut' => __('messages.admin.revolut')] as $provider => $label)
                            @php $method = $establishment->payment_methods[$provider] ?? []; @endphp
                            <div class="payment-method-card">
                                <label class="checkbox-field"><input type="checkbox" name="payment_methods[{{ $provider }}][enabled]" value="1" @checked(old('payment_methods.' . $provider . '.enabled', $method['enabled'] ?? $provider === 'pay_later'))><span>{{ $label }}</span></label>
                                @if (array_key_exists($provider, $paymentProviderReadiness))
                                    @php
                                        $productionReady = $paymentProviderReadiness[$provider]['production_ready'] ?? false;
                                        $selectedMode = old('payment_methods.' . $provider . '.mode', $method['mode'] ?? 'sandbox');
                                    @endphp
                                    <label><span>{{ __('messages.admin.payment_mode') }}</span><select name="payment_methods[{{ $provider }}][mode]">
                                        <option value="sandbox" @selected($selectedMode === 'sandbox')>{{ __('messages.admin.payment_mode_sandbox') }}</option>
                                        <option value="production" @selected($selectedMode === 'production') @disabled(! $productionReady)>{{ __('messages.admin.payment_mode_production') }}{{ $productionReady ? '' : ' — ' . __('messages.admin.payment_production_unavailable') }}</option>
                                    </select></label>
                                @endif
                                <label><span>{{ __('messages.admin.instructions') }}</span><input name="payment_methods[{{ $provider }}][instructions]" value="{{ old('payment_methods.' . $provider . '.instructions', $method['instructions'] ?? '') }}" placeholder="{{ __('messages.admin.optional_customer_instructions') }}"></label>
                                @if ($provider === 'interac')
                                    <label><span>{{ __('messages.admin.interac_email') }}</span><input type="email" name="payment_methods[interac][email]" value="{{ old('payment_methods.interac.email', $method['email'] ?? '') }}"></label>
                                    <label><span>{{ __('messages.admin.interac_security_question') }}</span><input name="payment_methods[interac][security_question]" value="{{ old('payment_methods.interac.security_question', $method['security_question'] ?? '') }}"></label>
                                    <label><span>{{ __('messages.admin.interac_security_answer') }}</span><input name="payment_methods[interac][security_answer]" value="{{ old('payment_methods.interac.security_answer', $method['security_answer'] ?? '') }}"></label>
                                @endif
                                @if ($provider === 'wise')
                                    <label><span>{{ __('messages.admin.wise_email') }}</span><input type="email" name="payment_methods[wise][email]" value="{{ old('payment_methods.wise.email', $method['email'] ?? '') }}"></label>
                                @endif
                                @if ($provider === 'revolut')
                                    <label><span>{{ __('messages.admin.revolut_email') }}</span><input type="email" name="payment_methods[revolut][email]" value="{{ old('payment_methods.revolut.email', $method['email'] ?? '') }}"></label>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    </div>
                    <div class="payment-method-editor">
                        <h2>{{ __('messages.admin.cancellation_policy') }}</h2>
                        <div class="form-grid">
                            <div>
                                <label for="cancellation_fee_percent">{{ __('messages.admin.cancellation_fee_percent') }}</label>
                                <input id="cancellation_fee_percent" type="number" step="0.01" min="0" max="100" name="cancellation_fee_percent" value="{{ old('cancellation_fee_percent', $establishment->cancellation_fee_percent ?? 0) }}">
                            </div>
                            <div>
                                <label for="cancellation_fee_days">{{ __('messages.admin.cancellation_fee_days') }}</label>
                                <input id="cancellation_fee_days" type="number" min="0" max="365" name="cancellation_fee_days" value="{{ old('cancellation_fee_days', $establishment->cancellation_fee_days ?? 0) }}">
                            </div>
                            <p class="form-help full-width">{{ __('messages.admin.cancellation_policy_help') }}</p>
                        </div>
                    </div>
                </div>
                <div id="establishment-panel-translations" class="property-tab-panel" role="tabpanel" data-property-panel="translations" hidden>
                    <div class="language-editor">
                    <h2>{{ __('messages.admin.translated_content_heading') }}</h2>
                    <div class="language-tabs" role="tablist" aria-label="{{ __('messages.admin.translated_languages') }}">
                        @foreach (['fr' => 'Français', 'en' => 'English'] as $locale => $language)
                            <button type="button" class="language-tab {{ $loop->first ? 'is-active' : '' }}" role="tab" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="establishment-language-{{ $locale }}" data-language-tab="{{ $locale }}">{{ $language }}</button>
                        @endforeach
                    </div>
                    @foreach (['fr' => 'Français', 'en' => 'English'] as $locale => $language)
                        @php $translation = $establishment->translations?->firstWhere('locale', $locale); @endphp
                        <div id="establishment-language-{{ $locale }}" class="language-panel {{ $loop->first ? 'is-active' : '' }}" role="tabpanel" data-language-panel="{{ $locale }}" {{ $loop->first ? '' : 'hidden' }}>
                            <div class="form-grid">
                                <div><label>{{ __('messages.admin.name') }}</label><input name="translations[{{ $locale }}][name]" value="{{ old('translations.' . $locale . '.name', $translation?->name) }}"></div>
                                <div class="full-width"><label>{{ __('messages.admin.description') }}</label><textarea name="translations[{{ $locale }}][description]" rows="4">{{ old('translations.' . $locale . '.description', $translation?->description) }}</textarea></div>
                                <div class="full-width"><label>{{ __('messages.admin.features') }}</label><input name="translations[{{ $locale }}][features]" value="{{ old('translations.' . $locale . '.features', implode(', ', $translation?->features ?? [])) }}"></div>
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit">{{ __('messages.admin.save') }}</button><a class="btn btn-ghost" href="{{ route('admin.establishments.index') }}">{{ __('messages.admin.back') }}</a></div>
            </form>

            @if ($establishment->exists)
                <div id="establishment-panel-reviews" class="property-tab-panel" role="tabpanel" data-property-panel="reviews" hidden>
                    <h2>{{ __('messages.admin.reviews_tab') }}</h2>
                    <p class="form-help">{{ __('messages.admin.reviews_tab_description') }}</p>

                    <h3 class="stacked-title">{{ __('messages.admin.add_review') }}</h3>
                    <form method="POST" action="{{ route('admin.establishments.reviews.store', $establishment) }}">
                        @csrf
                        <div class="admin-form-grid compact">
                            <label>
                                <span>{{ __('messages.admin.source') }}</span>
                                <select name="source">
                                    <option value="booking">Booking.com</option>
                                    <option value="airbnb">Airbnb</option>
                                    <option value="google">Google</option>
                                </select>
                            </label>
                            <label>
                                <span>{{ __('messages.admin.reviewer') }}</span>
                                <input type="text" name="reviewer_name" required>
                            </label>
                            <label>
                                <span>{{ __('messages.admin.rating') }}</span>
                                <input type="number" name="rating" min="1" max="5" value="5" required>
                            </label>
                            <label>
                                <span>{{ __('messages.admin.review_date') }}</span>
                                <input type="date" name="reviewed_at" value="{{ now()->toDateString() }}">
                            </label>
                            <label class="full-width">
                                <span>{{ __('messages.admin.review_text') }}</span>
                                <textarea name="review_text" rows="3"></textarea>
                            </label>
                            <label class="full-width">
                                <span>{{ __('messages.admin.source_url') }}</span>
                                <input type="url" name="source_url" placeholder="https://...">
                            </label>
                            <label class="checkbox-field">
                                <input type="checkbox" name="is_active" value="1" checked>
                                <span>{{ __('messages.admin.show_home') }}</span>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">{{ __('messages.admin.save_review') }}</button>
                        </div>
                    </form>

                    <h3 class="stacked-title">{{ __('messages.admin.import_csv') }}</h3>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.establishments.reviews.import', $establishment) }}">
                        @csrf
                        <div class="form-grid">
                            <div>
                                <label for="import-format">{{ __('messages.admin.import_format') }}</label>
                                <select id="import-format" name="format" required>
                                    <option value="booking">{{ __('messages.admin.import_format_booking') }}</option>
                                    <option value="airbnb">{{ __('messages.admin.import_format_airbnb') }}</option>
                                    <option value="google">{{ __('messages.admin.import_format_google') }}</option>
                                    <option value="generic">{{ __('messages.admin.import_format_generic') }}</option>
                                </select>
                            </div>
                            <div>
                                <label for="import-file">{{ __('messages.admin.import_file') }}</label>
                                <input id="import-file" type="file" name="import_file" accept=".csv,text/csv,text/plain">
                            </div>
                            <div class="full-width">
                                <label for="import-csv">{{ __('messages.admin.import_or_paste_csv') }}</label>
                                <textarea id="import-csv" name="csv" rows="4"></textarea>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-ghost">{{ __('messages.admin.import') }}</button>
                        </div>
                    </form>

                    <h3 class="stacked-title">{{ __('messages.admin.saved_reviews') }}</h3>
                    @if ($reviews->isEmpty())
                        <p>{{ __('messages.admin.no_reviews') }}</p>
                    @else
                        <div class="feature-grid review-admin-grid">
                            @foreach ($reviews as $review)
                                <article class="feature-card review-admin-card">
                                    <div class="icon">★</div>
                                    <h4>{{ $review->reviewer_name }}</h4>
                                    <p>{{ $review->source_label }}</p>
                                    <strong>{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</strong>
                                    @if ($review->review_text)<p>{{ $review->review_text }}</p>@endif

                                    <form method="POST" action="{{ route('admin.establishments.reviews.update', [$establishment, $review]) }}" class="review-admin-form">
                                        @csrf
                                        @method('PUT')
                                        <label>
                                            <span>{{ __('messages.admin.source') }}</span>
                                            <select name="source">
                                                <option value="booking" @selected($review->source === 'booking')>Booking.com</option>
                                                <option value="airbnb" @selected($review->source === 'airbnb')>Airbnb</option>
                                                <option value="google" @selected($review->source === 'google')>Google</option>
                                            </select>
                                        </label>
                                        <label>
                                            <span>{{ __('messages.admin.reviewer') }}</span>
                                            <input type="text" name="reviewer_name" value="{{ $review->reviewer_name }}" required>
                                        </label>
                                        <label>
                                            <span>{{ __('messages.admin.rating') }}</span>
                                            <input type="number" name="rating" min="1" max="5" value="{{ $review->rating }}" required>
                                        </label>
                                        <label>
                                            <span>{{ __('messages.admin.review_date') }}</span>
                                            <input type="date" name="reviewed_at" value="{{ $review->reviewed_at?->toDateString() ?? now()->toDateString() }}">
                                        </label>
                                        <label>
                                            <span>{{ __('messages.admin.review_text') }}</span>
                                            <textarea name="review_text" rows="3">{{ $review->review_text }}</textarea>
                                        </label>
                                        <label>
                                            <span>{{ __('messages.admin.source_url') }}</span>
                                            <input type="url" name="source_url" value="{{ $review->source_url }}">
                                        </label>
                                        <label class="checkbox-field">
                                            <input type="checkbox" name="is_active" value="1" {{ $review->is_active ? 'checked' : '' }}>
                                            <span>{{ __('messages.admin.show_home') }}</span>
                                        </label>
                                        <div class="review-admin-actions">
                                            <button type="submit" class="btn btn-ghost">{{ __('messages.admin.save_review') }}</button>
                                        </div>
                                    </form>

                                    <form method="POST" action="{{ route('admin.establishments.reviews.destroy', [$establishment, $review]) }}" class="review-delete-form">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger">{{ __('messages.admin.delete') }}</button>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            @if ($establishment->exists)
                <div id="establishment-panel-hosts" class="property-tab-panel" role="tabpanel" data-property-panel="hosts" hidden>
                    <h2>{{ __('messages.admin.hosts_tab') }}</h2>
                    <p class="form-help">{{ __('messages.admin.hosts_tab_description') }}</p>

                    <h3 class="stacked-title">{{ __('messages.admin.add_host') }}</h3>
                    <form method="POST" action="{{ route('admin.establishments.hosts.store', $establishment) }}">
                        @csrf
                        <div class="admin-form-grid compact">
                            <label>
                                <span>{{ __('messages.admin.host_name') }}</span>
                                <input type="text" name="name" required>
                            </label>
                            <label>
                                <span>{{ __('messages.common.email') }}</span>
                                <input type="email" name="email" required>
                            </label>
                            <label>
                                <span>{{ __('messages.admin.initial_password') }}</span>
                                <input type="password" name="password" required>
                            </label>
                            <label>
                                <span>{{ __('messages.common.confirm_password') }}</span>
                                <input type="password" name="password_confirmation" required>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">{{ __('messages.admin.add_host') }}</button>
                        </div>
                    </form>

                    <h3 class="stacked-title">{{ __('messages.admin.hosts_assigned') }}</h3>
                    @if ($establishment->hosts->isEmpty())
                        <p>{{ __('messages.admin.no_hosts_assigned') }}</p>
                    @else
                        <div class="feature-grid review-admin-grid">
                            @foreach ($establishment->hosts as $assignedHost)
                                <article class="feature-card review-admin-card">
                                    <h4>{{ $assignedHost->name }}</h4>
                                    <p>{{ $assignedHost->email }}</p>
                                    <form method="POST" action="{{ route('admin.establishments.hosts.destroy', [$establishment, $assignedHost]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger">{{ __('messages.admin.remove_host') }}</button>
                                    </form>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-card>
@endsection