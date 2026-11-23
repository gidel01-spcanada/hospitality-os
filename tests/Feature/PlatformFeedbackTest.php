<?php

namespace Tests\Feature;

use App\Mail\PlatformFeedbackReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PlatformFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_feedback_is_sent_to_the_configured_sprint_pay_address(): void
    {
        Mail::fake();
        config()->set('services.sprintpay.feedback_email', 'feedback@sprintpay.example');

        $this->get(route('feedback.create'))
            ->assertOk()
            ->assertSee(__('messages.feedback.title'));

        $this->post(route('feedback.store'), [
            'name' => 'Amina Customer',
            'email' => 'amina@example.com',
            'category' => 'suggestion',
            'message' => 'Please add a monthly reservation calendar.',
        ])->assertRedirect(route('feedback.create'))
            ->assertSessionHas('status', __('messages.feedback.sent'));

        $this->assertDatabaseHas('platform_feedback', [
            'email' => 'amina@example.com',
            'category' => 'suggestion',
            'message' => 'Please add a monthly reservation calendar.',
        ]);

        Mail::assertSent(PlatformFeedbackReceived::class, function (PlatformFeedbackReceived $mail): bool {
            $html = $mail->render();
            $this->assertStringContainsString(__('messages.transactional.feedback_context'), $html);
            $this->assertStringContainsString(route('privacy'), $html);
            $this->assertStringContainsString('Please add a monthly reservation calendar.', $html);
            $this->assertSame('amina@example.com', $mail->envelope()->replyTo[0]->address);
            return $mail->hasTo('feedback@sprintpay.example');
        });
    }

    public function test_feedback_form_reports_when_sprint_pay_delivery_is_not_configured(): void
    {
        config()->set('services.sprintpay.feedback_email', null);

        $this->get(route('feedback.create'))
            ->assertOk()
            ->assertSee(__('messages.feedback.unavailable'))
            ->assertDontSee('action="' . route('feedback.store') . '"', false);
    }

    public function test_feedback_delivery_failure_does_not_return_a_500_or_require_a_duplicate_submission(): void
    {
        config()->set('services.sprintpay.feedback_email', 'feedback@sprintpay.example');
        Log::spy();
        $pendingMail = \Mockery::mock(PendingMail::class);
        $pendingMail->shouldReceive('send')
            ->once()
            ->with(\Mockery::type(PlatformFeedbackReceived::class))
            ->andThrow(new TransportException('SMTP unavailable'));
        Mail::shouldReceive('to')
            ->once()
            ->with('feedback@sprintpay.example')
            ->andReturn($pendingMail);

        $this->post(route('feedback.store'), [
            'name' => 'Amina Customer',
            'email' => 'amina@example.com',
            'category' => 'suggestion',
            'message' => 'Please add a monthly reservation calendar.',
        ])->assertRedirect(route('feedback.create'))
            ->assertSessionHas('status', __('messages.feedback.saved_delivery_failed'));

        $this->assertDatabaseCount('platform_feedback', 1);
        Log::shouldHaveReceived('warning')->once();
    }
}