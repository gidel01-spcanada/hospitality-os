<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Collection;

class PropertyRecommendationService
{
    public function sort(Collection $properties, array $favoritePropertyIds = []): Collection
    {
        return $properties->sortByDesc(function (Property $property) use ($favoritePropertyIds): float {
            $reviews = $property->establishment?->reviews ?? collect();
            $favoriteScore = in_array($property->id, $favoritePropertyIds, true) ? 100000 : 0;
            $reviewScore = ((float) $reviews->avg('rating') * 100) + min($reviews->count(), 50);
            $priceScore = max(0, 100000 - (float) $property->nightly_rate_xof) / 1000;

            return $favoriteScore + $reviewScore + $priceScore;
        })->values();
    }
}
