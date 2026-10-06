<?php

namespace Tests\Feature;

use App\Mail\PlatformFeedbackReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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
}