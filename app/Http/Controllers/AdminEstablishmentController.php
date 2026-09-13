<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminEstablishmentController extends Controller
{
    public function index(): View
    {
        $establishments = Establishment::query()->withCount('properties')->orderBy('name')->get();

        return view('admin.establishments.index', compact('establishments'));
    }

    public function edit(Establishment $establishment): View
    {
        $establishment->load(['translations', 'properties.images']);

        $paymentProviderReadiness = $this->paymentProviderReadiness();

        return view('admin.establishments.edit', compact('establishment', 'paymentProviderReadiness'));
    }

    public function create(): View
    {
        $establishment = new Establishment(['country_code' => 'BJ', 'currency' => 'XOF']);
        $establishment->setRelation('properties', collect());
        $paymentProviderReadiness = $this->paymentProviderReadiness();

        return view('admin.establishments.edit', compact('establishment', 'paymentProviderReadiness'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedData($request);
        $validated['cover_image'] = $this->storeCoverImage($request, $validated['cover_image'] ?? null, null);
        $validated = $this->coordinatesFromGoogleMapsUrl($validated);
        $validated['payment_methods'] = $this->normalizePaymentMethods($validated['payment_methods'] ?? []);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['features'] = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', implode(',', $validated['features'] ?? [])))));
        $validated['cancellation_fee_percent'] = (float) ($validated['cancellation_fee_percent'] ?? 0);
        $validated['cancellation_fee_days'] = (int) ($validated['cancellation_fee_days'] ?? 0);
        $validated['vat_percent'] = (float) ($validated['vat_percent'] ?? 18.00);
        $validated['vat_included'] = $request->has('vat_included') ? $request->boolean('vat_included') : true;
        $validated['city_tax_type'] = in_array($validated['city_tax_type'] ?? 'percent', ['none', 'per_night', 'per_guest_night', 'percent'], true) ? ($validated['city_tax_type'] ?? 'percent') : 'percent';
        $validated['city_tax_amount'] = (float) ($validated['city_tax_amount'] ?? 5.00);
        $validated['service_fee_percent'] = (float) ($validated['service_fee_percent'] ?? 10.00);
        $establishment = Establishment::create($validated);
        $this->saveTranslations($establishment, $validated['translations'] ?? []);

        return redirect()->route('admin.establishments.index')->with('success', __('messages.flash.establishment_created'));
    }

    public function update(Request $request, Establishment $establishment): RedirectResponse
    {
        $validated = $this->validatedData($request, $establishment);
        $validated['cover_image'] = $this->storeCoverImage($request, $validated['cover_image'] ?? $establishment->cover_image, $establishment);
        $validated = $this->coordinatesFromGoogleMapsUrl($validated);
        $validated['payment_methods'] = $this->normalizePaymentMethods($validated['payment_methods'] ?? $establishment->payment_methods ?? []);
        $validated['slug'] = Str::slug($validated['slug'] ?: $validated['name']);
        $validated['features'] = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', implode(',', $validated['features'] ?? [])))));
        $validated['cancellation_fee_percent'] = $request->has('cancellation_fee_percent') ? (float) $validated['cancellation_fee_percent'] : (float) $establishment->cancellation_fee_percent;
        $validated['cancellation_fee_days'] = $request->has('cancellation_fee_days') ? (int) $validated['cancellation_fee_days'] : (int) $establishment->cancellation_fee_days;
        $validated['vat_percent'] = $request->has('vat_percent') ? (float) $validated['vat_percent'] : (float) $establishment->vat_percent;
        $validated['vat_included'] = $request->has('vat_percent') || $request->has('vat_included') ? $request->boolean('vat_included') : (bool) $establishment->vat_included;
        $cityTaxTypeInput = $validated['city_tax_type'] ?? null;
        $validated['city_tax_type'] = $request->has('city_tax_type') && in_array($cityTaxTypeInput, ['none', 'per_night', 'per_guest_night', 'percent'], true) ? $cityTaxTypeInput : $establishment->city_tax_type;
        $validated['city_tax_amount'] = $request->has('city_tax_amount') ? (float) $validated['city_tax_amount'] : (float) $establishment->city_tax_amount;
        $validated['service_fee_percent'] = $request->has('service_fee_percent') ? (float) $validated['service_fee_percent'] : (float) $establishment->service_fee_percent;
        $establishment->fill($validated)->save();
        $this->saveTranslations($establishment, $validated['translations'] ?? []);

        return redirect()->to(route('admin.establishments.edit', $establishment) . '#' . $this->activeTab($request))
            ->with('success', __('messages.flash.establishment_updated'));
    }

    private function activeTab(Request $request): string
    {
        return in_array($request->input('active_tab'), ['general', 'online', 'taxes', 'translations', 'payments'], true)
            ? $request->input('active_tab')
            : 'general';
    }

    private function validatedData(Request $request, ?Establishment $establishment = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:establishments,slug,' . ($establishment?->id ?? 'NULL')],
            'country_code' => ['required', 'string', 'size:2'],
            'city' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string', 'max:2048'],
            'cover_image_upload' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'property_image_id' => ['nullable', 'integer'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_maps_url' => ['nullable', 'url', 'max:2048'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*.enabled' => ['nullable', 'boolean'],
            'payment_methods.*.mode' => ['nullable', 'in:sandbox,production'],
            'payment_methods.*.instructions' => ['nullable', 'string', 'max:500'],
            'cancellation_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cancellation_fee_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'vat_included' => ['sometimes', 'boolean'],
            'city_tax_type' => ['nullable', 'string', 'in:none,per_night,per_guest_night,percent'],
            'city_tax_amount' => ['nullable', 'numeric', 'min:0'],
            'service_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'features' => ['nullable', 'array'],
            'features.*' => ['nullable', 'string', 'max:100'],
            'translations' => ['nullable', 'array'],
            'translations.fr.name' => ['nullable', 'string', 'max:255'],
            'translations.en.name' => ['nullable', 'string', 'max:255'],
            'translations.fr.description' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.fr.features' => ['nullable', 'string'],
            'translations.en.features' => ['nullable', 'string'],
        ], [
            'cover_image_upload.max' => __('messages.errors.image_file_too_large'),
            'cover_image_upload.uploaded' => __('messages.errors.image_upload_failed'),
        ]);
    }

    private function storeCoverImage(Request $request, ?string $currentPath, ?Establishment $establishment): ?string
    {
        if ($request->hasFile('cover_image_upload')) {
            $directory = rtrim(config('filesystems.public_upload_path'), '/\\') . DIRECTORY_SEPARATOR . 'establishments';
            File::ensureDirectoryExists($directory);
            $file = $request->file('cover_image_upload');
            $filename = now()->format('YmdHisv') . '-' . Str::lower(Str::random(12)) . '.' . pathinfo($file->hashName(), PATHINFO_EXTENSION);
            $file->move($directory, $filename);

            return 'uploads/establishments/' . $filename;
        }

        if ($request->filled('property_image_id') && $establishment?->exists) {
            $propertyImage = $establishment->properties()
                ->with('images')
                ->get()
                ->flatMap->images
                ->firstWhere('id', (int) $request->input('property_image_id'));

            if (! $propertyImage) {
                abort(422, 'The selected property image does not belong to this establishment.');
            }

            return $propertyImage->file_path;
        }

        return $currentPath;
    }

    private function saveTranslations(Establishment $establishment, array $translations): void
    {
        foreach (['fr', 'en'] as $locale) {
            $content = $translations[$locale] ?? [];
            if (! empty(array_filter($content, fn ($value) => filled($value)))) {
                $features = array_values(array_filter(array_map('trim', preg_split('/[,\n]+/', (string) ($content['features'] ?? '')))));
                $establishment->translations()->updateOrCreate(['locale' => $locale], [
                    'name' => $content['name'] ?? null,
                    'description' => $content['description'] ?? null,
                    'features' => $features,
                ]);
            }
        }
    }

    private function normalizePaymentMethods(array $methods): array
    {
        return collect(['pay_later', 'fedapay', 'paypal', 'cinetpay', 'mpesa'])->mapWithKeys(function (string $provider) use ($methods) {
            $method = $methods[$provider] ?? [];
            $enabled = filter_var($method['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $mode = $method['mode'] ?? 'sandbox';

            if ($enabled && $mode === 'production' && ! ($this->paymentProviderReadiness()[$provider]['production_ready'] ?? false)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "payment_methods.$provider.mode" => "Production credentials must be configured in the server environment before activating $provider.",
                ]);
            }

            return [$provider => [
                'enabled' => $enabled,
                'mode' => $mode,
                'instructions' => trim((string) ($method['instructions'] ?? '')),
            ]];
        })->all();
    }

    private function paymentProviderReadiness(): array
    {
        return [
            'pay_later' => ['production_ready' => true],
            'fedapay' => [
                'production_ready' => config('services.fedapay.environment') === 'production'
                    && filled(config('services.fedapay.public_key'))
                    && filled(config('services.fedapay.secret_key')),
            ],
            'paypal' => [
                'production_ready' => config('services.paypal.environment') === 'production'
                    && filled(config('services.paypal.client_id'))
                    && filled(config('services.paypal.client_secret')),
            ],
            'cinetpay' => [
                'production_ready' => config('services.cinetpay.environment') === 'production'
                    && filled(config('services.cinetpay.site_id'))
                    && filled(config('services.cinetpay.api_key')),
            ],
            'mpesa' => [
                'production_ready' => config('services.mpesa.environment') === 'production'
                    && filled(config('services.mpesa.consumer_key'))
                    && filled(config('services.mpesa.consumer_secret'))
                    && filled(config('services.mpesa.shortcode'))
                    && filled(config('services.mpesa.passkey')),
            ],
        ];
    }

    private function coordinatesFromGoogleMapsUrl(array $validated): array
    {
        $url = trim((string) ($validated['google_maps_url'] ?? ''));
        if ($url === '') {
            return $validated;
        }

        $coordinatePair = null;
        $query = parse_url($url, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $parameters);
            foreach (['q', 'query', 'll'] as $key) {
                if (! empty($parameters[$key])) {
                    $coordinatePair = (string) $parameters[$key];
                    break;
                }
            }
        }

        $coordinatePair ??= urldecode($url);
        if (! preg_match('~(?:@|!3d)?(-?\d+(?:\.\d+)?)\s*[,/]\s*(-?\d+(?:\.\d+)?)~', $coordinatePair, $matches)
            && ! preg_match('~!3d(-?\d+(?:\.\d+)?)!4d(-?\d+(?:\.\d+)?)~', $coordinatePair, $matches)) {
            return $validated;
        }

        $latitude = (float) $matches[1];
        $longitude = (float) $matches[2];
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return $validated;
        }

        $validated['latitude'] = $validated['latitude'] ?? $latitude;
        $validated['longitude'] = $validated['longitude'] ?? $longitude;

        return $validated;
    }
}