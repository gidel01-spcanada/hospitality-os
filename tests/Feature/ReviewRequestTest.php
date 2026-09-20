<?php

namespace Tests\Feature;

use App\Models\EmailOutbox;
use App\Models\Property;
use App\Models\Reservation;
use App\Models\SiteReview;
use App\Models\User;
use App\Services\ReservationEmailService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ReviewRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_stay_gets_signed_review_link_and_accepts_one_internal_review(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->firstOrFail();
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'REVIEW-REQUEST-1',
            'status' => 'completed',
            'check_in' => '2027-02-01',
            'check_out' => '2027-02-04',
            'adults' => 2,
            'currency' => 'XOF',
            'email' => 'review-request@example.com',
            'total_amount' => 90000,
        ]);

        app(ReservationEmailService::class)->queueStatusUpdate($reservation, 'completed');
        $email = EmailOutbox::query()->where('recipient_email', $reservation->email)->latest()->firstOrFail();
        $reviewUrl = $email->payload['review_url'];

        $this->get($reviewUrl)->assertOk()->assertSee(__('messages.review_request.title'));
        $this->post($reviewUrl, ['rating' => 5, 'review_text' => 'Très bon séjour.'])
            ->assertRedirect();

        $this->assertDatabaseHas('site_reviews', [
            'reservation_id' => $reservation->id,
            'property_id' => $property->id,
            'establishment_id' => $property->establishment_id,
            'rating' => 5,
        ]);
        $this->assertDatabaseCount('site_reviews', 1);

        $this->get($reviewUrl)->assertSee(__('messages.review_request.thanks'));
        $this->get($reviewUrl)->assertSee(__('messages.review_request.already_submitted'));
        $this->post($reviewUrl, ['rating' => 4])->assertConflict();
    }

    public function test_google_review_destination_can_be_configured(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::query()->firstOrFail();
        $this->actingAs($admin)->put(route('admin.establishments.update', $property->establishment), [
            'name' => $property->establishment->name,
            'slug' => $property->establishment->slug,
            'country_code' => $property->establishment->country_code,
            'currency' => $property->establishment->currency,
            'review_channel' => 'both',
            'google_review_url' => 'https://g.page/r/example/review',
            'active_tab' => 'online',
        ])->assertRedirect();

        $this->assertDatabaseHas('establishments', [
            'id' => $property->establishment_id,
            'review_channel' => 'both',
            'google_review_url' => 'https://g.page/r/example/review',
        ]);

        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'reservation_ref' => 'REVIEW-GOOGLE-EMAIL',
            'status' => 'completed',
            'check_in' => '2027-03-01',
            'check_out' => '2027-03-03',
            'adults' => 1,
            'currency' => 'XOF',
            'email' => 'google-review@example.com',
            'total_amount' => 50000,
        ]);
        app(\App\Services\ReservationEmailService::class)->queueStatusUpdate($reservation, 'completed');
        $payload = \App\Models\EmailOutbox::query()->where('recipient_email', $reservation->email)->latest()->firstOrFail()->payload;
        $this->assertSame('both', $payload['review_channel']);
        $this->assertSame('https://g.page/r/example/review', $payload['google_review_url']);
        $this->assertStringContainsString('/review/' . $reservation->id, $payload['review_url']);

        $reviewUrl = $payload['review_url'];
        $this->get($reviewUrl)->assertOk();
        $googleUrl = URL::temporarySignedRoute('reviews.submit.google', now()->addMinutes(5), ['reservation' => $reservation]);
        $this->get($googleUrl)->assertRedirect('https://g.page/r/example/review');

        $expiredUrl = URL::temporarySignedRoute('reviews.submit', now()->subMinute(), ['reservation' => $reservation]);
        $this->get($expiredUrl)->assertForbidden();
    }
}
