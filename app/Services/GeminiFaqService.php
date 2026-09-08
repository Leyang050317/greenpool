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
     * Answer general questions safely while grounding GreenPool-specific
     * questions in the approved FAQ entries supplied by the application.
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

        $greenPoolQuestion = $this->isGreenPoolQuestion($question);
        $scope = $greenPoolQuestion
            ? 'This is a GreenPool-specific question. Answer using only the approved FAQ content. If the FAQ does not answer it, say so clearly and direct the user to the relevant GreenPool menu.'
            : 'This is a general-knowledge question. You may answer concisely using general knowledge, but do not present general information as a GreenPool feature, rule, price, or policy. For live weather, current traffic, prices, laws, or other changing facts, explain that you cannot see live data and give only stable, general guidance.';

        $prompt = <<<TEXT
You are GreenPool Help, a concise support assistant for a Malaysian carpooling project.
The user is a {$user->role}. {$scope}
Do not invent app features, policies, prices, or account information. Never ask for passwords, card numbers, OTPs, or identity documents.
For an immediate safety emergency, tell the user to use GreenPool's Emergency button and call 999.
Return plain text only. Do not use Markdown, headings, bullets, or asterisks.

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

            return $answer === '' ? null : $this->plainText($answer);
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

    private function isGreenPoolQuestion(string $question): bool
    {
        $question = mb_strtolower($question);

        foreach ([
            'greenpool', 'booking', 'book a ride', 'ride', 'trip', 'driver', 'passenger',
            'payment', 'pay', 'fare', 'receipt', 'refund', 'message', 'notification',
            'vehicle', 'licence', 'license', 'profile', 'account', 'rating', 'emergency',
            'attraction', 'favourite', 'favorite', 'app',
        ] as $term) {
            if (str_contains($question, $term)) {
                return true;
            }
        }

        return false;
    }

    private function plainText(string $answer): string
    {
        $answer = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $answer);
        $answer = str_replace(['**', '__', '`'], '', $answer);
        $answer = preg_replace('/^\s*[-*+]\s+/m', '', $answer);

        return mb_substr(trim((string) $answer), 0, 1500);
    }
}
