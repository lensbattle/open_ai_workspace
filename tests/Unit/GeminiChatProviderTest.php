<?php

namespace Tests\Unit;

use App\Services\GeminiChatProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiChatProviderTest extends TestCase
{
    public function test_it_translates_gemini_response_to_text(): void
    {
        config([
            'services.gemini.key' => 'test-key',
            'services.gemini.endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models',
            'services.gemini.model' => 'gemini-test',
        ]);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'A useful answer']]]]],
            ]),
        ]);

        $reply = (new GeminiChatProvider)->reply([
            ['role' => 'user', 'content' => 'Say hello'],
            ['role' => 'assistant', 'content' => 'Hello'],
        ]);

        $this->assertSame('A useful answer', $reply);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent?key=test-key'
            && $request['contents'][1]['role'] === 'model');
    }
}
