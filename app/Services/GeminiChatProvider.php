<?php

namespace App\Services;

use App\Contracts\ChatProvider;
use App\Models\Conversation;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiChatProvider implements ChatProvider
{
    public function reply(array $messages): string
    {
        $contents = array_map(fn (array $message): array => [
            'role' => $message['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $message['content']]],
        ], $messages);

        return $this->generate($contents);
    }

    public function title(array $messages): string
    {
        $transcript = collect($messages)
            ->map(fn (array $message): string => sprintf('%s: %s', $message['role'], mb_substr($message['content'], 0, 800)))
            ->implode("\n");

        $prompt = <<<PROMPT
            Write a title for the conversation below, in the language the user is writing in.
            Rules: 3 to 6 words, no quotes, no trailing punctuation, no preamble, title case.

            {$transcript}
            PROMPT;

        $title = $this->generate([['role' => 'user', 'parts' => [['text' => $prompt]]]]);

        return Conversation::titleFromPrompt(trim($title, " \t\n\r\0\x0B\"'.,:;-"));
    }

    /**
     * Send one generateContent request and return the model's text.
     *
     * @param  array<int, array{role: string, parts: array<int, array{text: string}>}>  $contents
     */
    private function generate(array $contents): string
    {
        $apiKey = config('services.gemini.key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('Gemini API key is not configured.');
        }

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
