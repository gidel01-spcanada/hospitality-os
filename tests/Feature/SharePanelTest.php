<?php

namespace Tests\Feature;

use App\Models\Property;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_establishment_and_property_pages_share_their_current_filters(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->with('establishment')->firstOrFail();
        $checkIn = now()->addDays(10)->toDateString();
        $checkOut = now()->addDays(13)->toDateString();
        $query = ['check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => 2, 'token' => 'secret-token', 'empty' => ''];

        $pages = [
            route('properties.index') => [__('messages.properties.share_search'), 'data-share-search-form'],
            route('establishments.show', $property->establishment) => [__('messages.establishments.share'), 'data-share-establishment-form'],
            route('properties.show', $property) => [__('messages.properties.share'), 'data-share-booking-form'],
        ];

        foreach ($pages as $url => [$label, $formMarker]) {
            $expected = $url . '?' . http_build_query(['check_in' => $checkIn, 'check_out' => $checkOut, 'guests' => 2]);
            $html = $this->get($url . '?' . http_build_query($query))->assertOk()->assertSee($label)->getContent();
            $this->assertStringContainsString('data-share-url="' . e($expected) . '"', $html);
            $this->assertStringContainsString(urlencode($expected), $html);
            $this->assertStringContainsString($formMarker, $html);
            $this->assertStringNotContainsString('secret-token', $html);
        }
    }
}
