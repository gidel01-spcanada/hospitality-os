<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\SiteReview;
use App\Models\Establishment;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Services\PropertyRecommendationService;

class StaticPageController extends Controller
{
    public function establishments(): View
    {
        $establishments = Establishment::query()
            ->published()
            ->with('translations')
            ->withCount(['properties' => fn ($query) => $query->published()])
            ->orderBy('name')
            ->get();
        return view('establishments.index', compact('establishments'));
    }

    public function establishment(Request $request, Establishment $establishment, PublicPropertyController $catalog, PropertyRecommendationService $recommendations): View
    {
        abort_unless($establishment->is_active && $establishment->is_published, 404);
        $establishment->load('translations');
        $allProperties = $establishment->properties()
            ->published()
            ->with(['images' => fn ($query) => $query->orderBy('sort_order'), 'translations', 'amenities', 'features' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('name')
            ->get();
        $catalogData = $catalog->catalogData($request, $recommendations, $establishment);
        $properties = $catalogData['properties'];
        $filters = $catalogData['filters'];
        $reviewProperties = $establishment->properties()->with('translations')->orderBy('name')->get();
        $propertyIds = $reviewProperties->pluck('id');
        $reviewQuery = SiteReview::query()->active()->where(function ($query) use ($establishment, $propertyIds): void {
            $query->where(fn ($legacyQuery) => $legacyQuery->where('establishment_id', $establishment->id)->whereNull('property_id'))
                ->orWhereIn('property_id', $propertyIds);
        });
        $reviewStats = [
            'count' => (clone $reviewQuery)->count(),
            'average' => round((float) (clone $reviewQuery)->avg('rating'), 1),
        ];
        $reviewFilters = $request->validate([
            'review_scope' => ['nullable', 'in:all,establishment,properties'],
            'review_property' => ['nullable', 'integer', \Illuminate\Validation\Rule::in($propertyIds->all())],
        ]);
        if (($reviewFilters['review_scope'] ?? 'all') === 'establishment') {
            unset($reviewFilters['review_property']);
        }
        $reviews = (clone $reviewQuery)
            ->when(($reviewFilters['review_scope'] ?? 'all') === 'establishment', fn ($query) => $query->whereNull('property_id'))
            ->when(($reviewFilters['review_scope'] ?? 'all') === 'properties', fn ($query) => $query->whereNotNull('property_id'))
            ->when($reviewFilters['review_property'] ?? null, fn ($query, $propertyId) => $query->where('property_id', $propertyId))
            ->with('property.translations')
            ->orderByDesc('reviewed_at')->orderByDesc('id')
            ->paginate(6, ['*'], 'reviews_page')->withQueryString()->fragment('establishment-reviews');
        $gallery = collect();
        if (filled($establishment->cover_image)) {
            $gallery->push(['url' => asset($establishment->cover_image), 'alt' => $establishment->localized('name'), 'tag' => null]);
        }
        foreach ($allProperties as $property) {
            $primaryImage = $property->images->firstWhere('is_cover', true);
            $primaryPath = $primaryImage?->file_path ?: $property->cover_image;
            if (filled($primaryPath)) {
                $gallery->push(['url' => asset($primaryPath), 'alt' => $property->localized('name'), 'tag' => null]);
            }
        }
        $gallery = $gallery->merge(collect($establishment->publicImagePaths())->map(fn ($path) => [
            'url' => asset($path), 'alt' => $establishment->localized('name'), 'tag' => null,
        ]))->unique('url')->values();
        $description = trim(strip_tags((string) $establishment->localized('description')));
        if ($description === '' || str_starts_with(strtolower($description), 'draft ')) {
            $description = __('messages.establishments.description_fallback', ['name' => $establishment->localized('name'), 'city' => $establishment->city]);
        }
        $services = collect($establishment->localized('features') ?? [])->merge($allProperties->flatMap(fn ($property) => $property->features->pluck('name')))->filter()->unique()->values();
        $establishmentAmenities = $allProperties->flatMap->amenities->unique('id')->values();
        $nearbyPlaces = collect(data_get($establishment->metadata, 'nearby_points_of_interest.' . app()->getLocale(), []))
            ->filter(fn ($place) => is_array($place) && filled($place['name'] ?? null))->values();

        $contactEmail = filter_var($establishment->email, FILTER_VALIDATE_EMAIL) ? $establishment->email : null;
        $contactPhone = preg_replace('/[^+\d]/', '', (string) $establishment->phone);
        $contactPhone = strlen($contactPhone) >= 6 ? $contactPhone : null;
        $address = str_starts_with(strtolower((string) $establishment->address), 'draft ') ? null : $establishment->address;
        $mapQuery = $establishment->latitude !== null && $establishment->longitude !== null
            ? $establishment->latitude . ',' . $establishment->longitude
            : implode(', ', array_filter([$address, $establishment->city, $establishment->country_code]));
        $structuredData = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LodgingBusiness',
            '@id' => route('establishments.show', $establishment),
            'url' => route('establishments.show', $establishment),
            'name' => $establishment->localized('name'),
            'description' => $description,
            'image' => $gallery->pluck('url')->all(),
            'address' => $mapQuery ? array_filter([
                '@type' => 'PostalAddress', 'streetAddress' => $address,
                'addressLocality' => $establishment->city, 'addressCountry' => $establishment->country_code,
            ]) : null,
            'geo' => $establishment->latitude !== null && $establishment->longitude !== null ? [
                '@type' => 'GeoCoordinates', 'latitude' => (float) $establishment->latitude, 'longitude' => (float) $establishment->longitude,
            ] : null,
            'email' => $contactEmail,
            'telephone' => $contactPhone,
            'aggregateRating' => $reviewStats['count'] ? [
                '@type' => 'AggregateRating', 'ratingValue' => $reviewStats['average'], 'reviewCount' => $reviewStats['count'], 'bestRating' => 5,
            ] : null,
            'amenityFeature' => $establishmentAmenities->map(fn ($amenity) => ['@type' => 'LocationFeatureSpecification', 'name' => $amenity->name, 'value' => true])->all(),
        ]);

        return view('establishments.show', array_merge($catalogData, compact('establishment', 'properties', 'allProperties', 'reviews', 'reviewStats', 'reviewProperties', 'reviewFilters', 'gallery', 'description', 'services', 'establishmentAmenities', 'nearbyPlaces', 'contactEmail', 'contactPhone', 'address', 'mapQuery', 'structuredData')));
    }

    public function contact(): View
    {
        return view('contact');
    }

    public function about(): View
    {
        return view('about');
    }

    public function faq(): View
    {
        return view('faq');
    }

    public function reviews(Request $request): View
    {
        $reviewQuery = SiteReview::query()->active();
        $reviewStats = [
            'count' => (clone $reviewQuery)->count(),
            'average' => round((float) (clone $reviewQuery)->avg('rating'), 1),
        ];
        $reviews = (clone $reviewQuery)->with(['property.translations', 'property.establishment'])->orderByDesc('reviewed_at')->paginate(18)->withQueryString();
        $returnProperty = $request->filled('property')
            ? Property::query()->published()->where('slug', $request->string('property')->toString())->first()
            : null;
        $backUrl = $returnProperty ? route('properties.show', $returnProperty) : route('home');
        $backLabel = $returnProperty ? __('messages.reviews.back_property') : __('messages.reviews.back_home');

        return view('reviews.index', compact('reviews', 'reviewStats', 'backUrl', 'backLabel'));
    }

    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function terms(): View
    {
        return view('legal.terms');
    }

    public function cookies(): View
    {
        return view('legal.cookies');
    }
}
