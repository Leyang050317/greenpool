<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiFaqService
{
    private const WEBSITE_CAPABILITY_MAP = <<<'TEXT'
GreenPool is a Malaysian carpooling website with Passenger and Driver accounts.
Passengers can manage their profile, find a ride, submit and cancel booking requests, review booking history, message the relevant driver, receive notifications, make required trip payments, rate eligible completed trips, and browse or save tourist attractions.
Drivers can manage their profile and driving licence, add and manage vehicles, create, edit, start, complete, or cancel trips, review and accept or reject booking requests when seats and licence requirements allow, message relevant passengers, receive notifications, and rate eligible completed trips.
Both roles can use Messages, Notifications, Payments where applicable, Ratings, Settings, Help, emergency reporting for a relevant trip, and Tourist Attractions.
Trip creation requires a verified valid driving licence and an active vehicle. Bookings require driver acceptance before confirmation. Payment becomes available after a completed trip. Messaging is limited to the relevant active booking.
TEXT;

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

        if (! $this->isGreenPoolQuestion($question)) {
            return 'I can help only with GreenPool features, such as trips, bookings, payments, messages, ratings, vehicles, notifications, accounts, and tourist attractions.';
        }

        $knowledge = $faqs->map(fn (Faq $faq) => "[{$faq->category}] {$faq->question}\n{$faq->answer}")
            ->implode("\n\n");
        $capabilityMap = self::WEBSITE_CAPABILITY_MAP;

        $prompt = <<<TEXT
You are GreenPool Help, a concise support assistant for a Malaysian carpooling website.
The user is a {$user->role}. Answer only about GreenPool's current user-facing features.
Use APPROVED FAQ CONTENT as the source of truth for precise rules, policies, payment conditions, and eligibility. When an FAQ does not directly answer a website-operation question, use only the WEBSITE CAPABILITY MAP to explain the relevant menu and normal workflow.
Do not invent app features, policies, prices, account information, or steps that are not in those two sources. If neither source supports the requested feature, say that GreenPool does not currently document that feature and suggest a relevant available menu.
Never ask for passwords, card numbers, OTPs, or identity documents.
For an immediate safety emergency, tell the user to use GreenPool's Emergency button and call 999.
Return plain text only. Do not use Markdown, headings, bullets, or asterisks.

WEBSITE CAPABILITY MAP:
{$capabilityMap}

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
            'attraction', 'favourite', 'favorite', 'settings', 'setting', 'help',
            'find a ride', 'my trips', 'app',
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
