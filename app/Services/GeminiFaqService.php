<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiFaqService
{
    /**
     * Answer only from the FAQ entries supplied by the application. This keeps
     * the public bot helpful without exposing account, payment, or trip data.
     *
     * @param Collection<int, Faq> $faqs
     */
    public function answer(string $question, Collection $faqs, User $user): ?string
    {
        $key = config('services.gemini.key');

        if (! filter_var(config('services.gemini.enabled'), FILTER_VALIDATE_BOOLEAN) || blank($key)) {
            return null;
        }

        $knowledge = $faqs->map(fn (Faq $faq) => "[{$faq->category}] {$faq->question}\n{$faq->answer}")
            ->implode("\n\n");

        $prompt = <<<TEXT
You are GreenPool Help, a concise support assistant for a Malaysian carpooling project.
The user is a {$user->role}. Answer using only the approved FAQ content below.
Do not invent app features, policies, prices, or account information. Never ask for passwords, card numbers, OTPs, or identity documents.
For an immediate safety emergency, tell the user to use GreenPool's Emergency button and call 999. If the FAQ does not answer the question, say so clearly and suggest a relevant GreenPool menu.

APPROVED FAQ CONTENT:
{$knowledge}

USER QUESTION:
{$question}
TEXT;

        try {
            $response = Http::acceptJson()
                // Keep credentials out of query strings, browser history, and
                // HTTP exception messages. This is also Google's recommended
                // authentication format for the Gemini REST API.
                ->withHeaders(['x-goog-api-key' => $key])
                ->timeout(12)
                ->retry(1, 250, throw: false)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'.config('services.gemini.model').':generateContent', [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'maxOutputTokens' => 300,
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Gemini FAQ request failed.', [
                    'status' => $response->status(),
                    'reason' => mb_substr((string) data_get($response->json(), 'error.message', 'Unknown Gemini API error'), 0, 500),
                ]);

                return null;
            }

            $answer = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));

            return $answer === '' ? null : mb_substr($answer, 0, 1500);
        } catch (\Throwable $exception) {
            // HTTP client exceptions may include the full request URL, which
            // contains the API key. Keep diagnostic logging useful without
            // ever persisting a credential in Laravel's log file.
            Log::warning('Gemini FAQ request could not be completed.', [
                'exception_type' => $exception::class,
            ]);

            return null;
        }
    }
}
