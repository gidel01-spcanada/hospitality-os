<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HelpChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_help_chat_answers_from_public_faq_context(): void
    {
        Http::fake(fn () => Http::response([
            'choices' => [['message' => ['content' => 'Choose a property and enter your dates.']]],
        ]));
        config()->set('services.help_chat.api_key', 'test-key');
        config()->set('services.help_chat.base_url', 'https://api.groq.test/v1');
        config()->set('services.help_chat.model', 'test-model');

        $response = $this->get('/')
            ->assertOk()
            ->assertSee('data-help-chat', false)
            ->assertSee('data-help-chat-minimize', false);

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $launcher = $xpath->query('//summary[@class="help-chat-launcher"]')->item(0);
        $icon = $xpath->query('.//svg[@class="help-chat-launcher-compact"]', $launcher)->item(0);
        $this->assertNotNull($icon);
        $this->assertSame('true', $icon->getAttribute('aria-hidden'));
        $this->assertSame(__('messages.help_chat.open'), $launcher->getAttribute('title'));
        $this->assertSame(__('messages.help_chat.open'), $launcher->getAttribute('aria-label'));

        $this->postJson(route('help-chat.ask'), [
            'message' => 'How do I book a stay?',
            'history' => [],
        ])->assertOk()
            ->assertJsonPath('answer', 'Choose a property and enter your dates.');

        Http::assertSent(function (ClientRequest $request): bool {
            $messages = $request->data()['messages'];

            return $request->url() === 'https://api.groq.test/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer test-key')
                && str_contains($messages[0]['content'], __('messages.faq.booking_answer'))
                && $messages[1]['content'] === 'How do I book a stay?';
        });
    }

    public function test_help_chat_reports_unavailable_without_an_api_key(): void
    {
        config()->set('services.help_chat.api_key', null);

        $this->postJson(route('help-chat.ask'), ['message' => 'How do I book?'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', __('messages.help_chat.unavailable'));
    }
}