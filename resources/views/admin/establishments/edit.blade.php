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
                @foreach (['general' => __('messages.admin.general_information'), 'online' => __('messages.admin.online_information'), 'translations' => __('messages.admin.translations'), 'payments' => __('messages.admin.payment_methods_heading')] as $tab => $label)
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
                        <div><label for="city">{{ __('messages.admin.city') }}</label><input id="city" name="city" value="{{ old('city', $establishment->city) }}"></div>
                        <div><label for="address">{{ __('messages.admin.address') }}</label><input id="address" name="address" value="{{ old('address', $establishment->address) }}"></div>
                    </div>
                </div>
                <div id="establishment-panel-online" class="property-tab-panel" role="tabpanel" data-property-panel="online" hidden>
                    <div class="form-grid">
                        <div class="full-width">
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
                        <label for="cover_image">{{ __('messages.admin.existing_url_path') }}</label>
                        <input id="cover_image" type="text" name="cover_image" value="{{ old('cover_image', $establishment->cover_image) }}" placeholder="https://... ou uploads/..."></div>
                        <div><label for="email">{{ __('messages.common.email') }}</label><input id="email" type="email" name="email" value="{{ old('email', $establishment->email) }}"></div>
                        <div><label for="phone">{{ __('messages.admin.phone') }}</label><input id="phone" name="phone" value="{{ old('phone', $establishment->phone) }}"></div>
                        <div><label for="latitude">{{ __('messages.admin.latitude') }}</label><input id="latitude" type="number" step="any" name="latitude" value="{{ old('latitude', $establishment->latitude) }}"></div>
                        <div><label for="longitude">{{ __('messages.admin.longitude') }}</label><input id="longitude" type="number" step="any" name="longitude" value="{{ old('longitude', $establishment->longitude) }}"></div>
                        <div class="full-width"><label for="google_maps_url">{{ __('messages.admin.google_maps_url') }}</label><input id="google_maps_url" type="url" name="google_maps_url" value="{{ old('google_maps_url', $establishment->google_maps_url) }}" placeholder="https://maps.google.com/..."></div>
                    </div>
                </div>
                <div id="establishment-panel-payments" class="property-tab-panel" role="tabpanel" data-property-panel="payments" hidden>
                    <div class="payment-method-editor">
                    <h2>{{ __('messages.admin.payment_methods_heading') }}</h2>
                    <div class="form-grid">
                        @foreach (['pay_later' => __('messages.admin.deferred_payment'), 'fedapay' => __('messages.admin.fedapay_sandbox'), 'paypal' => __('messages.admin.paypal_sandbox')] as $provider => $label)
                            @php $method = $establishment->payment_methods[$provider] ?? []; @endphp
                            <div class="payment-method-card">
                                <label class="checkbox-field"><input type="checkbox" name="payment_methods[{{ $provider }}][enabled]" value="1" @checked(old('payment_methods.' . $provider . '.enabled', $method['enabled'] ?? $provider === 'pay_later'))><span>{{ $label }}</span></label>
                                <label><span>{{ __('messages.admin.instructions') }}</span><input name="payment_methods[{{ $provider }}][instructions]" value="{{ old('payment_methods.' . $provider . '.instructions', $method['instructions'] ?? '') }}" placeholder="{{ __('messages.admin.optional_customer_instructions') }}"></label>
                            </div>
                        @endforeach
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
        </div>
    </x-card>
@endsection