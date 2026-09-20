<?php

namespace Tests\Unit;

use App\Models\Amenity;
use App\Models\Property;
use App\Services\PropertyCopyImprover;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PropertyCopyImproverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_only_copy_and_verified_facts_and_caches_the_preview(): void
    {
        $this->seed(DatabaseSeeder::class);
        config([
            'services.property_copy.api_key' => 'test-key',
            'services.property_copy.base_url' => 'https://api.groq.com/openai/v1',
            'services.property_copy.model' => 'openai/gpt-oss-20b',
        ]);
        $property = Property::query()->firstOrFail();
        $amenity = Amenity::query()->firstOrFail();
        $property->amenities()->sync([$amenity->id]);
        $originalName = $property->name;
        $copy = [
            'summary_fr' => 'Appartement calme.',
            'description_fr' => 'Un appartement pour trois personnes.',
            'summary_en' => 'Quiet apartment.',
            'description_en' => 'An apartment for three guests.',
        ];
        $suggestion = [
            'summary_fr' => 'Un appartement calme et accueillant.',
            'description_fr' => 'Profitez d’un appartement confortable pour trois personnes.',
            'summary_en' => 'A calm and welcoming apartment.',
            'description_en' => 'Enjoy a comfortable apartment for three guests.',
        ];

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($suggestion, JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);

        $service = app(PropertyCopyImprover::class);
        $this->assertSame($suggestion, $service->improve($property, $copy));
        $this->assertSame($suggestion, $service->improve($property, $copy));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($amenity): bool {
            $payload = json_decode($request['messages'][1]['content'], true);

            return $request->url() === 'https://api.groq.com/openai/v1/chat/completions'
                && $request['model'] === 'openai/gpt-oss-20b'
                && $payload['verified_facts']['amenities'] === [$amenity->name]
                && ! array_key_exists('address', $payload['verified_facts']);
        });
        $this->assertSame($originalName, $property->fresh()->name);
    }
}