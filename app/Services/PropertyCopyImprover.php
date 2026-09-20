<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PropertyCopyImprover
{
    public function improve(Property $property, array $copy): array
    {
        $apiKey = (string) config('services.property_copy.api_key');
        if ($apiKey === '') {
            throw new RuntimeException(__('messages.ai_copy.not_configured'));
        }

        $property->loadMissing(['amenities', 'features']);
        $facts = [
            'property_type' => $property->property_type,
            'maximum_guests' => $property->max_guests,
            'bedrooms' => $property->bedrooms,
            'beds' => $property->beds,
            'bathrooms' => $property->bathrooms,
            'area' => $property->area ? $property->area.' '.$property->area_unit : null,
            'minimum_stay' => $property->minimum_stay,
            'amenities' => $property->amenities->pluck('name')->filter()->values()->all(),
            'included_features' => $property->features->where('is_active', true)->pluck('name')->filter()->values()->all(),
        ];
        $input = ['current_copy' => $copy, 'verified_facts' => array_filter($facts, fn ($value) => $value !== null && $value !== [])];
        $cacheKey = 'property-copy:'.hash('sha256', json_encode([
            config('services.property_copy.provider'),
            config('services.property_copy.model'),
            $input,
        ], JSON_UNESCAPED_UNICODE));

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($apiKey, $input): array {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout(20)
                ->retry(2, 250, throw: false)
                ->post(rtrim((string) config('services.property_copy.base_url'), '/').'/chat/completions', [
                    'model' => config('services.property_copy.model'),
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instruction()],
                        ['role' => 'user', 'content' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)],
                    ],
                ]);

            if (! $response->successful()) {
                throw new RuntimeException(__('messages.ai_copy.provider_unavailable'));
            }

            $content = $response->json('choices.0.message.content');
            $result = is_string($content) ? json_decode($content, true) : null;
            $required = ['summary_fr', 'description_fr', 'summary_en', 'description_en'];
            if (! is_array($result) || collect($required)->contains(fn ($key) => ! is_string($result[$key] ?? null) || trim($result[$key]) === '')) {
                throw new RuntimeException(__('messages.ai_copy.invalid_response'));
            }

            return collect($required)->mapWithKeys(fn ($key) => [$key => trim($result[$key])])->all();
        });
    }

    private function instruction(): string
    {
        return <<<'PROMPT'
Improve the guest-facing copy for this short-stay rental in both French and English. Preserve every factual detail. Do not invent amenities, locations, addresses, distances, views, accessibility features, policies, awards, or claims. Use only facts in verified_facts and information already present in current_copy. Use warm, concise, natural language. Summaries must be brief and descriptions easy to scan. Do not mention that AI was used. Return only a JSON object with exactly these string keys: summary_fr, description_fr, summary_en, description_en.
PROMPT;
    }
}