<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Reservation;
use App\Models\ReservationGuest;
use App\Models\User;
use App\Models\PaymentAttempt;
use App\Models\Receipt;
use App\Jobs\CompletePastReservations;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationWorkflowAndEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_stay_notes_appear_in_confirmation_email_checkout_and_customer_reservation_page(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = \App\Models\Establishment::create([
            'tenant_id' => $tenant->id, 'name' => 'Notes residence', 'slug' => 'notes-residence', 'currency' => 'XOF',
            'electricity_billed_separately' => true, 'electricity_policy_note' => 'Électricité facturée au compteur : 150 XOF/kWh.',
            'cancellation_fee_percent' => 20, 'cancellation_fee_days' => 7,
        ]);
        $property = $establishment->properties()->create(['name' => 'Notes suite', 'slug' => 'notes-suite', 'currency' => 'XOF']);
        $customer = User::factory()->create(['role' => 'customer', 'locale' => 'fr']);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'user_id' => $customer->id, 'reservation_ref' => 'NOTES-001', 'status' => 'pending_payment',
            'email' => $customer->email, 'locale' => 'fr', 'check_in' => now()->addDays(10), 'check_out' => now()->addDays(12),
            'adults' => 1, 'currency' => 'XOF', 'subtotal' => 50000, 'total_amount' => 50000,
        ]);
        app()->setLocale('fr');
        $cancellationNote = __('messages.properties.cancellation_fee_note', ['percent' => '20', 'days' => 7]);

        app(\App\Services\ReservationEmailService::class)->queueForReservation($reservation, 'reservation_created');
        $payload = \App\Models\EmailOutbox::latest('id')->firstOrFail()->payload;
        $html = html_entity_decode(view('emails.reservation-created', $payload)->render());
        $this->assertStringContainsString('Électricité facturée au compteur : 150 XOF/kWh.', $html);
        $this->assertStringContainsString($cancellationNote, $html);
        $this->assertStringContainsString(__('messages.transactional.important_notes'), $html);

        $checkout = html_entity_decode($this->withSession(['checkout_identity_skipped' => [$reservation->id]])
            ->get(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]))->assertOk()->getContent());
        $this->assertStringContainsString('Électricité facturée au compteur : 150 XOF/kWh.', $checkout);
        $this->assertStringContainsString($cancellationNote, $checkout);

        $this->actingAs($customer)->get(route('dashboard.reservations.show', $reservation))->assertOk()
            ->assertSee('data-stay-notes', false)
            ->assertSee('Électricité facturée au compteur : 150 XOF/kWh.');

        $establishment->update(['electricity_billed_separately' => false, 'cancellation_fee_percent' => 0]);
        $this->assertSame([], \App\Support\ReservationSummary::make($reservation->fresh())['stay_notes']);
    }
    public function test_reservation_summary_uses_only_verified_same_currency_payments_and_saved_prices(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = \App\Models\Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Summary residence', 'slug' => 'summary-residence', 'currency' => 'EUR']);
        $property = $establishment->properties()->create(['name' => 'Summary home', 'slug' => 'summary-home', 'currency' => 'EUR', 'nightly_rate_xof' => 99999]);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'reservation_ref' => 'SUMMARY-001', 'status' => 'pending_payment',
            'email' => 'summary@example.com', 'check_in' => now()->addDays(3), 'check_out' => now()->addDays(5),
            'adults' => 2, 'currency' => 'EUR', 'subtotal' => 200, 'fees' => 30, 'taxes' => 20, 'total_amount' => 250,
        ]);
        $reservation->priceLines()->create(['label' => 'Nuit(s) x 2', 'amount' => 200, 'currency' => 'EUR']);
        $summary = \App\Support\ReservationSummary::make($reservation, 'fr');
        $this->assertSame('0.00', $summary['paid']);
        $this->assertSame('250.00', $summary['due']);
        $this->assertSame('pending', $summary['payment_status']);
        $this->assertSame('100.00', $summary['lines'][0]['nightly_rate']);
        $attempt = $reservation->paymentAttempts()->create(['provider' => 'wise', 'provider_reference' => 'summary-paid-1', 'idempotency_key' => 'summary-paid-1', 'status' => 'paid', 'currency' => 'EUR', 'amount' => 100]);
        $reservation->paymentAttempts()->create(['provider' => 'paypal', 'provider_reference' => 'summary-hold', 'idempotency_key' => 'summary-hold', 'status' => 'paid', 'currency' => 'EUR', 'amount' => 250, 'payload' => ['is_guarantee' => true]]);
        $summary = \App\Support\ReservationSummary::make($reservation);
        $this->assertSame('100.00', $summary['paid']);
        $this->assertSame('150.00', $summary['due']);
        $this->assertSame('partial', $summary['payment_status']);
        $reservation->update(['status' => 'pending_validation']);
        $this->assertSame('pending_validation', \App\Support\ReservationSummary::make($reservation)['payment_status']);
        $attempt->update(['amount' => 250]);
        $reservation->update(['status' => 'confirmed']);
        $summary = \App\Support\ReservationSummary::make($reservation);
        $this->assertSame('0.00', $summary['due']);
        $this->assertSame('confirmed', $summary['payment_status']);
        $attempt->update(['amount' => 100]);
        $reservation->paymentAttempts()->create(['provider' => 'paypal', 'provider_reference' => 'foreign-payment', 'idempotency_key' => 'foreign-payment', 'status' => 'paid', 'currency' => 'USD', 'amount' => 50]);
        $summary = \App\Support\ReservationSummary::make($reservation);
        $this->assertNull($summary['due']);
        $this->assertSame('unreconciled', $summary['payment_status']);
        app(\App\Services\ReservationEmailService::class)->queueForReservation($reservation, 'reservation_received');
        $notification = \App\Models\EmailOutbox::latest('id')->firstOrFail();
        $html = view('emails.reservation-received', $notification->payload)->render();
        $this->assertStringContainsString(__('messages.reservation_summary.balance_unknown'), $html);
        $this->assertStringContainsString('50 USD', $html);
        $attempt->update(['amount' => 250]);
        $this->assertSame('0.00', \App\Support\ReservationSummary::make($reservation)['due']);
        $this->assertEquals(250, (float) $reservation->fresh()->total_amount);
    }

    public function test_booking_email_and_checkout_share_summary_for_all_payment_states_and_delayed_sending(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = \App\Models\Establishment::create([
            'tenant_id' => $tenant->id, 'name' => 'Résidence Email', 'slug' => 'email-residence', 'currency' => 'EUR',
            'cover_image' => 'uploads/establishments/email-cover.jpg', 'city' => 'Cotonou', 'address' => '12 Avenue Marina',
            'payment_methods' => ['pay_later' => ['enabled' => true, 'mode' => 'manual']],
        ]);
        $establishment->translations()->create(['locale' => 'en', 'name' => 'Email Residence']);
        $property = $establishment->properties()->create(['name' => 'Suite Email', 'slug' => 'email-suite', 'currency' => 'EUR', 'nightly_rate_xof' => 99999, 'max_guests' => 4, 'bedrooms' => 2, 'beds' => 2, 'bathrooms' => 1]);
        $property->translations()->create(['locale' => 'en', 'name' => 'Email Suite']);
        $property->images()->create(['file_path' => 'uploads/properties/email-suite.jpg', 'file_name' => 'suite.jpg', 'is_cover' => true]);
        $customer = User::factory()->create(['role' => 'customer', 'locale' => 'en', 'email_verified_at' => now(), 'last_login_at' => now()]);
        $guest = ReservationGuest::create(['full_name' => $customer->name, 'email' => $customer->email]);
        $reservation = Reservation::create([
            'property_id' => $property->id, 'user_id' => $customer->id, 'guest_id' => $guest->id,
            'reservation_ref' => 'MAIL-SUMMARY-001', 'email' => $customer->email, 'locale' => 'en', 'status' => 'pending_payment',
            'check_in' => now()->addDays(3), 'check_out' => now()->addDays(5), 'adults' => 2, 'children' => 1, 'infants' => 0,
            'currency' => 'EUR', 'subtotal' => 220, 'fees' => 20, 'taxes' => 20, 'total_amount' => 250,
        ]);
        $reservation->priceLines()->createMany([
            ['label' => 'Nuit(s) x 2', 'amount' => 200, 'currency' => 'EUR'],
            ['label' => 'Option: Breakfast', 'amount' => 20, 'currency' => 'EUR'],
            ['label' => 'Service fee', 'amount' => 20, 'currency' => 'EUR'],
            ['label' => 'VAT (18% incl.)', 'amount' => 33.56, 'currency' => 'EUR'],
            ['label' => 'City tax', 'amount' => 20, 'currency' => 'EUR'],
            ['label' => 'Promotion', 'amount' => -10, 'currency' => 'EUR'],
        ]);
        $checkoutUrl = route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token]);
        $firstNotification = null;
        foreach ([['pending_payment', 0, 'pending', '250.00'], ['confirmed', 250, 'confirmed', '0.00'], ['pending_payment', 100, 'partial', '150.00'], ['pending_validation', 100, 'pending_validation', '150.00']] as [$status, $paid, $paymentStatus, $due]) {
            $reservation->paymentAttempts()->delete();
            $reservation->update(['status' => $status]);
            if ($paid) {
                $reservation->paymentAttempts()->create(['provider' => 'wise', 'provider_reference' => 'state-' . $paymentStatus, 'idempotency_key' => 'state-' . $paymentStatus, 'status' => 'paid', 'currency' => 'EUR', 'amount' => $paid]);
            }
            if ($status === 'pending_validation') {
                $reservation->paymentAttempts()->create(['provider' => 'wise', 'provider_reference' => 'proof-only', 'idempotency_key' => 'proof-only', 'status' => 'pending_validation', 'currency' => 'EUR', 'amount' => 150]);
            }
            app(\App\Services\ReservationEmailService::class)->queueForReservation($reservation, 'reservation_received');
            $notification = \App\Models\EmailOutbox::latest('id')->firstOrFail();
            $firstNotification ??= $notification;
            $summary = $notification->payload['reservation_summary'];
            $this->assertSame(number_format($paid, 2, '.', ''), $summary['paid']);
            $this->assertSame($due, $summary['due']);
            $this->assertSame($paymentStatus, $summary['payment_status']);
            $this->assertSame('Email Residence', $summary['establishment']['name']);
            $this->assertSame('Email Suite', $summary['property']['name']);
            $this->assertSame(asset('uploads/establishments/email-cover.jpg'), $summary['establishment']['image']);
            $this->assertSame(asset('uploads/properties/email-suite.jpg'), $summary['property']['image']);
            app()->setLocale('en');
            foreach (['emails.reservation-received', 'emails.reservation-created', 'emails.reservation-status-updated'] as $template) {
                $html = view($template, $notification->payload)->render();
                $this->assertStringContainsString('2 night(s) × 100 EUR / night', $html);
                $this->assertStringContainsString('33.56 EUR', $html);
                $this->assertStringContainsString('Promotion', $html);
                $this->assertStringContainsString('data-email-payment-status="' . $paymentStatus . '"', $html);
                $this->assertStringContainsString(htmlspecialchars($notification->payload['checkout_url'], ENT_QUOTES), $html);
                $this->assertStringNotContainsString('99,999', $html);
                $this->assertSame($paymentStatus === 'pending_validation', str_contains($html, 'data-email-validating'));
                $this->assertSame($paymentStatus !== 'pending_validation', str_contains($html, 'data-email-due'));
                if ($paymentStatus === 'pending_validation') {
                    $this->assertStringContainsString(__('messages.transactional.validation_detail', ['amount' => '150 EUR']), $html);
                }
            }
            $checkout = $this->actingAs($customer)->get($checkoutUrl)->assertOk();
            $document = new \DOMDocument();
            @$document->loadHTML($checkout->getContent());
            $xpath = new \DOMXPath($document);
            $this->assertSame(\App\Support\ReservationSummary::money($summary['paid'], 'EUR', 'en'), trim($xpath->query('//*[@data-paid-amount]')->item(0)->textContent));
            $this->assertSame(\App\Support\ReservationSummary::money($paymentStatus === 'pending_validation' ? '0.00' : $due, 'EUR', 'en'), trim($xpath->query('//*[@data-remaining-amount]')->item(0)->textContent));
            $validatingNode = $xpath->query('//*[@data-validating-amount]')->item(0);
            $this->assertSame($paymentStatus === 'pending_validation' ? '150 EUR' : null, $validatingNode ? trim($validatingNode->textContent) : null);
        }
        $reservation->paymentAttempts()->delete();
        $reservation->paymentAttempts()->create(['provider' => 'wise', 'provider_reference' => 'late-paid', 'idempotency_key' => 'late-paid', 'status' => 'paid', 'currency' => 'EUR', 'amount' => 250]);
        $reservation->update(['status' => 'confirmed']);
        $sentPayload = null;
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->once()->andReturnUsing(function ($template, $payload) use (&$sentPayload): void {
            $sentPayload = $payload;
            view($template, $payload)->render();
        });
        app()->setLocale('fr');
        $this->artisan('messages:send-email-notifications', ['--limit' => 1])->assertExitCode(0);
        $this->assertSame('0.00', $sentPayload['reservation_summary']['due']);
        $this->assertSame('confirmed', $sentPayload['reservation_summary']['payment_status']);
        $this->assertSame('sent', $firstNotification->fresh()->status);
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_legacy_reservation_email_payload_and_existing_review_links_remain_supported(): void
    {
        $payload = [
            'reservation_ref' => 'LEGACY-EMAIL', 'property_name' => 'Legacy home', 'status' => 'completed',
            'total_amount' => '250.00', 'currency' => 'EUR',
            'price_lines' => [['label' => 'Saved accommodation', 'amount' => '250.00', 'currency' => 'EUR']],
            'checkout_url' => 'https://example.com/reservations/1/checkout?token=legacy',
            'review_url' => 'https://example.com/review/1?signature=legacy',
            'google_review_url' => 'https://example.com/google-review', 'review_channel' => 'both',
        ];
        foreach (['emails.reservation-created', 'emails.reservation-received', 'emails.reservation-status-updated'] as $template) {
            $html = view($template, $payload)->render();
            $this->assertStringContainsString('LEGACY-EMAIL', $html);
            $this->assertStringContainsString('Legacy home', $html);
            $this->assertStringContainsString(htmlspecialchars($payload['checkout_url'], ENT_QUOTES), $html);
            $this->assertStringContainsString(__('messages.transactional.greeting_generic'), $html);
            $this->assertStringContainsString(route('contact'), $html);
            $this->assertStringContainsString(route('privacy'), $html);
            $this->assertStringContainsString(\App\Support\PlatformBrand::name(), $html);
        }
        $html = view('emails.reservation-status-updated', $payload)->render();
        $this->assertStringContainsString(htmlspecialchars($payload['review_url'], ENT_QUOTES), $html);
        $this->assertStringContainsString($payload['google_review_url'], $html);
    }

    public function test_legacy_transactional_payloads_render_without_inventing_verified_payment(): void
    {
        app()->setLocale('en');
        $payload = [
            'reservation_ref' => 'LEGACY-TRANSACTION', 'property_name' => 'Legacy suite',
            'price_lines' => [['label' => 'Saved accommodation', 'amount' => '250.50', 'currency' => 'EUR']],
            'total_amount' => '250.50', 'amount' => '250.50', 'currency' => 'EUR',
            'receipt_number' => 'RCT-LEGACY', 'receipt_url' => 'https://example.com/receipt',
            'checkout_url' => 'https://example.com/checkout', 'register_url' => 'https://example.com/register',
            'reservation_url' => 'https://example.com/reservation', 'thread_url' => 'https://example.com/thread',
            'sender_name' => 'Legacy host', 'establishment_name' => 'Legacy residence',
            'message' => '<script>private message</script>', 'provider' => 'wise',
        ];
        foreach (['emails.reservation-created', 'emails.reservation-received', 'emails.payment-link', 'emails.receipt-issued', 'emails.payment-proof-submitted', 'emails.message-received'] as $template) {
            $html = view($template, $payload)->render();
            $this->assertStringContainsString('Hello,', $html);
            $this->assertStringContainsString(route('contact'), $html);
            $this->assertStringNotContainsString('messages.transactional.', $html);
            $this->assertStringNotContainsString(__('messages.transactional.paid_confirmed'), $html);
            if ($template !== 'emails.message-received') {
                $this->assertStringContainsString('250.50 EUR', $html);
            }
        }
        $receiptHtml = view('emails.receipt-issued', $payload)->render();
        $this->assertStringNotContainsString('has been confirmed', $receiptHtml);
        $this->assertStringNotContainsString('Amount paid:', $receiptHtml);
        $this->assertStringContainsString($payload['receipt_url'], $receiptHtml);
        $this->assertStringContainsString('&lt;script&gt;', view('emails.message-received', $payload)->render());
    }

    public function test_receipt_emails_verify_the_linked_transaction_and_refresh_before_sending(): void
    {
        $tenant = \App\Models\Tenant::firstOrCreate(['slug' => 'default'], ['name' => 'Default', 'status' => 'active']);
        $establishment = \App\Models\Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Receipt residence', 'slug' => 'receipt-residence']);
        $property = $establishment->properties()->create(['name' => 'Receipt suite', 'slug' => 'receipt-suite']);
        $customer = User::factory()->create(['name' => 'Amina Customer', 'locale' => 'en']);
        $reservation = Reservation::create([
            'user_id' => $customer->id, 'property_id' => $property->id, 'reservation_ref' => 'RECEIPT-EMAIL',
            'email' => $customer->email, 'locale' => 'en', 'status' => 'confirmed', 'currency' => 'EUR',
            'check_in' => now()->addDays(3), 'check_out' => now()->addDays(5), 'adults' => 2,
            'subtotal' => 250, 'total_amount' => 250,
        ]);
        $attempt = $reservation->paymentAttempts()->create([
            'provider' => 'wise', 'provider_reference' => 'WISE-REAL-REFERENCE', 'idempotency_key' => 'receipt-email',
            'amount' => 100, 'currency' => 'EUR', 'status' => 'paid',
        ]);
        $receipt = $reservation->receipts()->create([
            'payment_attempt_id' => $attempt->id, 'receipt_number' => 'RCT-EMAIL',
            'amount' => 100, 'currency' => 'EUR', 'issued_at' => now(),
        ]);
        $service = app(\App\Services\ReservationEmailService::class);
        app()->setLocale('en');
        foreach ([
            ['paid', false, 100, 100, 'EUR', true, '150.00'],
            ['completed', false, 250, 250, 'EUR', true, '0.00'],
            ['pending_validation', false, 100, 100, 'EUR', false, '250.00'],
            ['authorized', false, 100, 100, 'EUR', false, '250.00'],
            ['failed', false, 100, 100, 'EUR', false, '250.00'],
            ['paid', true, 250, 250, 'EUR', false, '250.00'],
            ['paid', false, 100, 100, 'USD', true, null],
            ['paid', false, 100, 250, 'EUR', false, '150.00'],
        ] as [$status, $guarantee, $paid, $receiptAmount, $currency, $verified, $due]) {
            $attempt->update(['status' => $status, 'amount' => $paid, 'currency' => $currency, 'payload' => ['is_guarantee' => $guarantee]]);
            $receipt->update(['amount' => $receiptAmount, 'currency' => $currency]);
            $service->queueReceipt($receipt->fresh());
            $payload = \App\Models\EmailOutbox::latest('id')->firstOrFail()->payload;
            $this->assertSame($verified, $payload['transaction']['verified']);
            $this->assertSame($due, $payload['reservation_summary']['due']);
            $html = view('emails.receipt-issued', $payload)->render();
            $this->assertStringContainsString('Hello Amina,', $html);
            $this->assertStringContainsString('WISE-REAL-REFERENCE', $html);
            $this->assertStringContainsString('Receipt issued on', $html);
            $this->assertStringContainsString(htmlspecialchars($payload['receipt_url'], ENT_QUOTES), $html);
            $this->assertStringNotContainsString('messages.transactional.', $html);
            $this->assertSame($due === '0.00', str_contains($html, __('messages.transactional.status_paid')));
            if ($verified) {
                $this->assertStringContainsString(__('messages.transactional.paid_confirmed'), $html);
                $this->assertStringContainsString(__('messages.transactional.transaction_number'), $html);
            } else {
                $this->assertStringNotContainsString(__('messages.transactional.paid_confirmed'), $html);
                $this->assertStringNotContainsString(__('messages.receipts.email_intro', ['reference' => $reservation->reservation_ref, 'property' => $payload['property_name']]), $html);
            }
        }
        \App\Models\EmailOutbox::query()->delete();
        $attempt->update(['status' => 'paid', 'amount' => 100, 'currency' => 'EUR', 'payload' => []]);
        $receipt->update(['amount' => 100, 'currency' => 'EUR']);
        $service->queueReceipt($receipt->fresh());
        $attempt->update(['status' => 'failed']);
        $sentPayload = null;
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->once()->andReturnUsing(function ($template, $payload) use (&$sentPayload): void {
            $sentPayload = $payload;
            view($template, $payload)->render();
        });
        app()->setLocale('fr');
        $this->artisan('messages:send-email-notifications')->assertExitCode(0);
        $this->assertFalse($sentPayload['transaction']['verified']);
        $this->assertSame('250.00', $sentPayload['reservation_summary']['due']);
        $this->assertSame('fr', app()->getLocale());
        $this->assertSame('failed', $attempt->fresh()->status);
        $this->assertSame('250.00', $reservation->fresh()->total_amount);
    }

    public function test_admin_can_set_guest_preferred_locale_and_email_is_queued_in_that_language(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $property = Property::where('slug', 'appartement-401')->firstOrFail();

        // Admin's own session locale is French; the guest's chosen locale should still win.
        $this->actingAs($admin)->post(route('admin.reservations.store'), [
            'property_id' => $property->id,
            'full_name' => 'English Guest',
            'email' => 'english-guest@example.com',
            'phone' => '+22900000000',
            'country' => 'CA',
            'locale' => 'en',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(7)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
        ])->assertRedirect();

        $reservation = Reservation::query()->where('email', 'english-guest@example.com')->firstOrFail();
        $this->assertSame('en', $reservation->locale);

        $notification = \App\Models\EmailOutbox::query()->where('template', 'reservation_created')->latest()->firstOrFail();
        $this->assertSame('en', $notification->payload['locale']);
        $this->assertSame('Your reservation confirmation: ' . $reservation->reservation_ref, $notification->payload['subject']);
        $this->assertStringContainsString('lang=en', $notification->payload['checkout_url']);
    }

    public function test_customer_remark_is_stored_and_displayed_on_reservation_page(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $guest = ReservationGuest::query()->create(['full_name' => 'Remark Customer', 'email' => 'remark@example.com']);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-REMARK-001',
            'status' => 'pending',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => 'remark@example.com',
            'subtotal' => 1000,
            'fees' => 0,
            'taxes' => 0,
            'total_amount' => 1000,
            'source' => 'website',
            'customer_note' => 'Je souhaite un lit double et une arrivée tardive.',
            'notes' => 'Note interne de validation.',
        ]);

        $this->actingAs($admin)
            ->get('/admin/reservations/' . $reservation->id)
            ->assertOk()
            ->assertSee('Remarque client')
            ->assertSee('Je souhaite un lit double et une arrivée tardive.')
            ->assertSee('Note interne')
            ->assertSee('Message au client');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'customer_note' => 'Je souhaite un lit double et une arrivée tardive.']);
    }

    public function test_only_admin_can_delete_reservation_and_associated_payment_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::query()->create(['full_name' => 'Delete Me', 'email' => 'delete-me@example.com']);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id, 'guest_id' => $guest->id, 'reservation_ref' => 'AFK-DELETE-001',
            'status' => 'pending', 'check_in' => now()->addDay()->toDateString(), 'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1, 'children' => 0, 'infants' => 0, 'currency' => 'XOF', 'email' => $guest->email,
            'subtotal' => 1000, 'fees' => 0, 'taxes' => 0, 'total_amount' => 1000, 'source' => 'website',
        ]);
        $attempt = PaymentAttempt::query()->create([
            'reservation_id' => $reservation->id, 'provider' => 'offline', 'provider_reference' => 'delete-ref',
            'currency' => 'XOF', 'amount' => 1000, 'status' => 'created', 'idempotency_key' => 'delete-key', 'payload' => [],
        ]);
        $receipt = Receipt::query()->create([
            'reservation_id' => $reservation->id, 'payment_attempt_id' => $attempt->id, 'receipt_number' => 'RC-DELETE-001',
            'amount' => 1000, 'currency' => 'XOF', 'issued_at' => now(),
        ]);
        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->delete(route('admin.reservations.destroy', $reservation))->assertForbidden();
        $this->actingAs($admin)->delete(route('admin.reservations.destroy', $reservation))->assertRedirect(route('admin.reservations.index'));

        $this->assertDatabaseMissing('reservations', ['id' => $reservation->id]);
        $this->assertDatabaseMissing('payment_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('receipts', ['id' => $receipt->id]);
        $this->assertDatabaseMissing('reservation_guests', ['id' => $guest->id]);
    }

    public function test_admin_can_review_reservations_and_queue_status_updates(): void
    {
        $this->seed(DatabaseSeeder::class);

        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::query()->create([
            'full_name' => 'Alice Doe',
            'email' => 'alice@example.com',
            'phone' => '+229 99 99 99 99',
            'country' => 'Bénin',
            'metadata' => ['source' => 'test'],
        ]);

        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-TEST-001',
            'status' => 'pending',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'adults' => 2,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => 'alice@example.com',
            'subtotal' => 120000,
            'fees' => 12000,
            'taxes' => 6000,
            'total_amount' => 138000,
            'source' => 'website',
            'notes' => 'Awaiting review.',
        ]);

        $admin = User::where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin/reservations')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/bookings')
            ->assertOk()
            ->assertSee('Alice Doe')
            ->assertSee('138,000.00 XOF')
            ->assertSee('En attente');

        $this->actingAs($admin)
            ->get('/admin/bookings?view=calendar&month=' . now()->format('Y-m'))
            ->assertOk()
            ->assertSee('data-reservation-view="calendar"', false)
            ->assertSee('Alice Doe');

        $this->actingAs($admin)
            ->get('/admin/reservations/' . $reservation->id)
            ->assertOk();

        $this->actingAs($admin)
            ->put('/admin/reservations/' . $reservation->id, [
                'property_id' => $property->id,
                'full_name' => 'Alice Updated',
                'email' => 'alice@example.com',
                'phone' => '+229 99 99 99 99',
                'country' => 'Bénin',
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(14)->toDateString(),
                'adults' => 2,
                'children' => 0,
                'infants' => 0,
                'notes' => 'Dates updated.',
            ])
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'subtotal' => 100000,
            'fees' => 10000,
            'taxes' => 5000,
            'total_amount' => 115000,
        ]);
        $this->assertDatabaseHas('reservation_price_lines', ['reservation_id' => $reservation->id, 'label' => 'Nuit(s) x 4', 'amount' => 100000]);

        $this->actingAs($admin)
            ->put('/admin/reservations/' . $reservation->id, [
                'property_id' => $property->id,
                'full_name' => 'Alice Updated',
                'email' => 'alice@example.com',
                'check_in' => now()->addDays(10)->toDateString(),
                'check_out' => now()->addDays(15)->toDateString(),
                'adults' => 2,
            ])
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'total_amount' => 143750]);

        $this->actingAs($admin)
            ->post('/admin/reservations/' . $reservation->id . '/payment-link')
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('email_outbox', [
            'recipient_email' => 'alice@example.com',
            'template' => 'payment_link',
            'status' => 'queued',
        ]);
        $paymentLink = \App\Models\EmailOutbox::query()->where('template', 'payment_link')->latest()->firstOrFail();
        $this->assertSame(route('checkout.show', ['reservation' => $reservation, 'token' => $reservation->checkout_token, 'lang' => 'fr']), $paymentLink->payload['checkout_url']);

        $this->actingAs($admin)
            ->patch('/admin/reservations/' . $reservation->id . '/status', [
                'status' => 'confirmed',
                'notes' => 'Client confirmed and deposit requested.',
            ])
            ->assertRedirect('/admin/reservations/' . $reservation->id);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => 'alice@example.com', 'template' => 'reservation_status_updated', 'status' => 'queued']);
        $this->actingAs($admin)
            ->post('/admin/reservations/' . $reservation->id . '/payment-link')
            ->assertForbidden();

        $reservation->update(['status' => 'pending']);
        $reservation->paymentAttempts()->create([
            'provider' => 'pay_later',
            'provider_reference' => 'verified-payment',
            'currency' => 'XOF',
            'amount' => 143750,
            'status' => 'verified',
            'idempotency_key' => 'verified-payment-link-test',
        ]);

        $this->actingAs($admin)
            ->post('/admin/reservations/' . $reservation->id . '/payment-link')
            ->assertForbidden();
    }

    public function test_admin_can_edit_notes_update_status_with_proof_and_confirm_payment_with_reference(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed(DatabaseSeeder::class);
        $property = Property::query()->where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::query()->create(['full_name' => 'Forms Guest', 'email' => 'forms-guest@example.com']);
        $reservation = Reservation::query()->create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-FORMS-001',
            'status' => 'pending_payment',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'adults' => 1,
            'currency' => 'XOF',
            'email' => $guest->email,
            'total_amount' => 10000,
        ]);
        $admin = User::query()->where('email', 'admin@afrikappart.test')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee(route('admin.reservations.notes.update', $reservation), false)
            ->assertSee('name="payment_proof"', false)
            ->assertSee('data-confirm-message', false);

        $this->actingAs($admin)
            ->patch(route('admin.reservations.notes.update', $reservation), ['notes' => 'Guest requested a late arrival.'])
            ->assertRedirect(route('admin.reservations.show', $reservation));
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'notes' => 'Guest requested a late arrival.']);

        $this->actingAs($admin)
            ->patch(route('admin.reservations.update-status', $reservation), [
                'status' => 'confirmed',
                'payment_proof' => \Illuminate\Http\UploadedFile::fake()->create('status-proof.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.reservations.show', $reservation));
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertNotEmpty(data_get($reservation->paymentAttempts()->latest()->firstOrFail()->payload, 'payment_proof.path'));

        $this->actingAs($admin)
            ->post(route('admin.reservations.confirm-offline-payment', $reservation), [
                'provider_reference' => 'manual-confirm-123',
                'payment_proof' => \Illuminate\Http\UploadedFile::fake()->create('confirmed-proof.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('payment_attempts', [
            'reservation_id' => $reservation->id,
            'provider' => 'offline',
            'provider_reference' => 'manual-confirm-123',
            'status' => 'paid',
        ]);
    }

    public function test_confirmed_payment_automatically_creates_and_emails_receipt(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => 'Receipt Guest', 'email' => 'receipt@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-RECEIPT-001',
            'status' => 'pending_payment',
            'check_in' => now()->addDays(5)->toDateString(),
            'check_out' => now()->addDays(8)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $guest->email,
            'subtotal' => 75000,
            'fees' => 7500,
            'taxes' => 3750,
            'total_amount' => 86250,
            'source' => 'website',
        ]);
        $attempt = $reservation->paymentAttempts()->create([
            'provider' => 'pay_later',
            'provider_reference' => 'receipt-payment-001',
            'currency' => 'XOF',
            'amount' => 86250,
            'status' => 'paid',
            'idempotency_key' => 'receipt-payment-001',
        ]);

        app(\App\Services\ReservationEmailService::class)->issueReceiptAndQueueEmail($reservation, $attempt);
        app(\App\Services\ReservationEmailService::class)->issueReceiptAndQueueEmail($reservation, $attempt);

        $this->assertDatabaseHas('receipts', ['reservation_id' => $reservation->id, 'payment_attempt_id' => $attempt->id, 'amount' => '86250.00']);
        $this->assertSame(1, $reservation->receipts()->count());
        $this->assertSame(1, \App\Models\EmailOutbox::query()->where('template', 'receipt_issued')->where('recipient_email', $guest->email)->count());
    }

    public function test_past_confirmed_reservations_are_completed_by_job_and_email_status_update(): void
    {
        $this->seed(DatabaseSeeder::class);
        $property = Property::where('slug', 'appartement-401')->firstOrFail();
        $guest = ReservationGuest::create(['full_name' => 'Past Stay Guest', 'email' => 'past-stay@example.com']);
        $reservation = Reservation::create([
            'property_id' => $property->id,
            'guest_id' => $guest->id,
            'reservation_ref' => 'AFK-COMPLETE-001',
            'status' => 'confirmed',
            'check_in' => now()->subDays(4)->toDateString(),
            'check_out' => now()->subDays(2)->toDateString(),
            'adults' => 1,
            'children' => 0,
            'infants' => 0,
            'currency' => 'XOF',
            'email' => $guest->email,
            'subtotal' => 50000,
            'fees' => 5000,
            'taxes' => 2500,
            'total_amount' => 57500,
            'source' => 'website',
        ]);

        dispatch_sync(new CompletePastReservations());

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'completed']);
        $this->assertDatabaseHas('email_outbox', ['recipient_email' => $guest->email, 'template' => 'reservation_status_updated', 'status' => 'queued']);
    }
}
