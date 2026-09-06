<?php

namespace App\Services;

use App\Contracts\ChatProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiChatProvider implements ChatProvider
{
    public function reply(array $messages): string
    {
        $apiKey = config('services.gemini.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

        $contents = array_map(fn (array $message): array => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $message['content']]],
        ], $messages);

        $response = Http::connectTimeout(5)
            ->timeout(45)
            ->retry([200, 500], 0)
            ->post(sprintf('%s/%s:generateContent?key=%s', config('services.gemini.endpoint'), config('services.gemini.model'), urlencode($apiKey)), ['contents' => $contents])
            ->throw();

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Gemini returned an empty response.');
        }

        return $text;
    }
}
