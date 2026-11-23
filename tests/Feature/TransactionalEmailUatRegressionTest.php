<?php

namespace Tests\Feature;

use App\Models\EmailOutbox;
use App\Models\Establishment;
use App\Models\MessageThread;
use App\Models\Reservation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MessageEmailService;
use App\Services\ReservationEmailService;
use App\Support\BrandSettings;
use App\Support\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TransactionalEmailUatRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_notifications_deliver_new_and_legacy_bodies_without_the_reserved_variable_collision(): void
    {
        $tenant = Tenant::create(['slug' => 'messages', 'name' => 'Messages', 'status' => 'active']);
        $establishment = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Residence', 'slug' => 'messages-residence']);
        $customer = User::factory()->create(['role' => 'customer', 'email_message_updates' => true]);
        $staff = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenant->id, 'email_message_updates' => true]);
        $thread = MessageThread::create(['customer_id' => $customer->id, 'establishment_id' => $establishment->id]);

        app(MessageEmailService::class)->queueForMessage($thread, $staff, '<b>New reply</b>');
        $new = EmailOutbox::where('recipient_email', $customer->email)->firstOrFail();
        $this->assertSame('<b>New reply</b>', $new->payload['message_body']);
        $this->assertArrayNotHasKey('message', $new->payload);
        $legacy = EmailOutbox::create([
            'template' => 'message_received', 'recipient_email' => $staff->email, 'status' => 'queued',
            'payload' => ['locale' => 'en', 'subject' => 'Legacy', 'sender_name' => 'Guest', 'establishment_name' => 'Residence', 'message' => '<b>Old reply</b>'],
        ]);

        $this->artisan('messages:send-email-notifications')->assertExitCode(0);

        $this->assertSame('sent', $new->fresh()->status);
        $this->assertSame('sent', $legacy->fresh()->status);
        $messages = Mail::mailer()->getSymfonyTransport()->messages()->values();
        $this->assertCount(2, $messages);
        $customerMail = $messages->first(fn ($mail) => $mail->getOriginalMessage()->getTo()[0]->getAddress() === $customer->email);
        $staffMail = $messages->first(fn ($mail) => $mail->getOriginalMessage()->getTo()[0]->getAddress() === $staff->email);
        $this->assertStringContainsString('&lt;b&gt;New reply&lt;/b&gt;', $customerMail->getOriginalMessage()->getHtmlBody());
        $this->assertStringContainsString('&lt;b&gt;Old reply&lt;/b&gt;', $staffMail->getOriginalMessage()->getHtmlBody());
    }

    public function test_confirmation_email_delivers_stay_dates_in_each_guest_locale_and_supports_legacy_payloads(): void
    {
        $reservation = $this->reservation('dates', 'EUR');
        foreach (['en', 'fr'] as $locale) {
            $reservation->update(['locale' => $locale]);
            app(ReservationEmailService::class)->queueForReservation($reservation, 'reservation_created');
        }

        $this->artisan('messages:send-email-notifications')->assertExitCode(0);
        $messages = Mail::mailer()->getSymfonyTransport()->messages()->values();
        $this->assertCount(2, $messages);
        foreach (['en', 'fr'] as $locale) {
            $mail = $messages->first(fn ($message) => str_contains($message->getOriginalMessage()->getHtmlBody(), '<html lang="' . $locale . '">'));
            $this->assertNotNull($mail);
            $html = $mail->getOriginalMessage()->getHtmlBody();
            $this->assertStringContainsString(Carbon::parse('2031-02-11')->locale($locale)->translatedFormat('D d M Y'), $html);
            $this->assertStringContainsString(Carbon::parse('2031-02-14')->locale($locale)->translatedFormat('D d M Y'), $html);
        }
        $this->assertSame(2, EmailOutbox::where('status', 'sent')->count());

        app()->setLocale('en');
        $html = view('emails.reservation-created', [
            'reservation_ref' => 'LEGACY', 'property_name' => 'Suite',
            'check_in' => '2031-02-11', 'check_out' => '2031-02-14',
        ])->render();
        $this->assertStringContainsString('Tue 11 Feb 2031', $html);
        $this->assertStringContainsString('Fri 14 Feb 2031', $html);
    }

    public function test_fractional_amounts_are_preserved_in_delivered_payment_and_receipt_emails(): void
    {
        $reservation = $this->reservation('amounts', 'EUR');
        $reservation->update(['locale' => 'en', 'subtotal' => '199.50', 'total_amount' => '229.43']);
        $reservation->priceLines()->create(['label' => 'Accommodation', 'amount' => '199.50', 'currency' => 'EUR']);
        $attempt = $reservation->paymentAttempts()->create([
            'provider' => 'wise', 'provider_reference' => 'payment-amounts', 'idempotency_key' => 'payment-amounts',
            'status' => 'paid', 'currency' => 'EUR', 'amount' => '229.43',
        ]);
        $tenantId = $reservation->property->establishment->tenant_id;
        app(CurrentTenant::class)->set($tenantId);
        BrandSettings::set(['email_sender_email' => 'payments@example.com', 'email_sender_name' => 'Payments']);
        app(CurrentTenant::class)->clear();
        $staff = User::factory()->create(['role' => 'admin', 'tenant_id' => $tenantId, 'locale' => 'en']);
        $service = app(ReservationEmailService::class);
        $service->queuePaymentLink($reservation);
        $service->issueReceiptAndQueueEmail($reservation, $attempt);
        $service->queueOfflineProofNotification($reservation, $attempt);
        $this->artisan('messages:send-email-notifications')->assertExitCode(0);

        $messages = Mail::mailer()->getSymfonyTransport()->messages()->values();
        $this->assertCount(3, $messages);
        foreach ($messages as $message) {
            $mail = $message->getOriginalMessage();
            $html = $mail->getHtmlBody();
            $this->assertStringContainsString('229.43 EUR', $html);
            if ($mail->getTo()[0]->getAddress() !== $staff->email) {
                $this->assertStringContainsString('199.50 EUR', $html);
            }
            $this->assertSame('payments@example.com', $mail->getFrom()[0]->getAddress());
            $this->assertSame('Payments', $mail->getFrom()[0]->getName());
        }
        $this->assertSame(3, EmailOutbox::where('status', 'sent')->count());
        $this->assertSame('199,50 EUR', \App\Support\ReservationSummary::money('199.50', 'EUR', 'fr'));
        $this->assertSame('92 000 XOF', \App\Support\ReservationSummary::money('92000', 'XOF', 'fr'));
    }

    public function test_confirmation_subject_and_sender_are_tenant_scoped_and_other_subjects_remain_unchanged(): void
    {
        config(['platform.mode' => 'cloud', 'mail.from.address' => 'default@example.com', 'mail.from.name' => 'Default Sender']);
        $first = $this->reservation('tenant-one', 'EUR');
        $second = $this->reservation('tenant-two', 'EUR');
        $third = $this->reservation('tenant-three', 'EUR');
        $fourth = $this->reservation('tenant-four', 'EUR');
        $firstTenant = $first->property->establishment->tenant_id;
        $secondTenant = $second->property->establishment->tenant_id;
        $fourthTenant = $fourth->property->establishment->tenant_id;
        app(CurrentTenant::class)->set($firstTenant);
        BrandSettings::set(['customer_confirmation_subject' => 'Custom confirmation', 'email_sender_email' => 'one@example.com', 'email_sender_name' => 'First Sender']);
        app(CurrentTenant::class)->set($secondTenant);
        BrandSettings::set(['email_sender_email' => 'two@example.com', 'email_sender_name' => 'Second Sender']);
        app(CurrentTenant::class)->set($fourthTenant);
        BrandSettings::set(['email_sender_name' => 'Name Only']);
        app(CurrentTenant::class)->clear();

        $service = app(ReservationEmailService::class);
        $service->queueForReservation($first, 'reservation_created');
        $service->queueForReservation($first, 'reservation_received');
        $service->queueForReservation($first, 'reservation_status_updated');
        $service->queueForReservation($second, 'reservation_created');
        $service->queueForReservation($first, 'reservation_created', 'Explicit subject');
        $service->queueForReservation($third, 'reservation_created');
        $service->queueForReservation($fourth, 'reservation_created');
        $this->artisan('messages:send-email-notifications')->assertExitCode(0);

        $messages = Mail::mailer()->getSymfonyTransport()->messages()->map(fn ($sent) => $sent->getOriginalMessage());
        $this->assertCount(7, $messages);
        $firstMessages = $messages->filter(fn ($mail) => $mail->getTo()[0]->getAddress() === $first->email);
        $this->assertCount(4, $firstMessages);
        $subjects = $firstMessages->map(fn ($mail) => $mail->getSubject())->all();
        $this->assertSame(2, count(array_filter($subjects, fn ($subject) => $subject === 'Custom confirmation')));
        $this->assertContains(__('messages.reservation_status.email_subject', ['reference' => $first->reservation_ref], 'en'), $subjects);
        $this->assertContains('Explicit subject', $subjects);
        foreach ($firstMessages as $message) {
            $from = $message->getFrom()[0];
            $this->assertSame('one@example.com', $from->getAddress());
            $this->assertSame('First Sender', $from->getName());
        }
        $secondMessage = $messages->first(fn ($mail) => $mail->getTo()[0]->getAddress() === $second->email);
        $this->assertSame(__('messages.reservation_created.email_subject', ['reference' => $second->reservation_ref], 'en'), $secondMessage->getSubject());
        $from = $secondMessage->getFrom()[0];
        $this->assertSame('two@example.com', $from->getAddress());
        $this->assertSame('Second Sender', $from->getName());
        $from = $messages->first(fn ($mail) => $mail->getTo()[0]->getAddress() === $third->email)->getFrom()[0];
        $this->assertSame('default@example.com', $from->getAddress());
        $this->assertSame('Default Sender', $from->getName());
        $from = $messages->first(fn ($mail) => $mail->getTo()[0]->getAddress() === $fourth->email)->getFrom()[0];
        $this->assertSame('default@example.com', $from->getAddress());
        $this->assertSame('Name Only', $from->getName());
        $this->assertSame(7, EmailOutbox::where('status', 'sent')->count());
    }

    private function reservation(string $slug, string $currency): Reservation
    {
        $tenant = Tenant::create(['slug' => $slug, 'name' => $slug, 'status' => 'active']);
        $establishment = Establishment::create(['tenant_id' => $tenant->id, 'name' => 'Residence', 'slug' => $slug . '-residence', 'currency' => $currency]);
        $property = $establishment->properties()->create(['name' => 'Suite', 'slug' => $slug . '-suite', 'currency' => $currency]);

        return Reservation::create([
            'property_id' => $property->id, 'reservation_ref' => strtoupper($slug), 'status' => 'pending_payment',
            'email' => $slug . '@example.com', 'locale' => 'en', 'check_in' => '2031-02-11', 'check_out' => '2031-02-14',
            'adults' => 1, 'currency' => $currency, 'subtotal' => 100, 'total_amount' => 100,
        ]);
    }
}
