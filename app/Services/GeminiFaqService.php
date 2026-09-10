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

TRIPS AND BOOKINGS
- Passengers use Find a Ride, submit a booking request, and wait for driver acceptance before a ride is confirmed.
- A passenger may cancel only a Pending booking request. Accepted, rejected, cancelled, or completed bookings cannot be cancelled by the passenger.
- Drivers need a verified, valid driving licence and an active vehicle to publish a trip. Drivers can create, edit, start, complete, or cancel eligible trips.
- A driver can start a trip only within the permitted start window. During an active trip, the driver marks each boarded passenger as Picked Up, then completes the trip.

PAYMENTS AND RATINGS
- Payment becomes available after the driver completes a trip with boarded passengers.
- GreenPool currently offers Pay by Card through Stripe Checkout or Pay Cash, which the driver confirms after receiving it.
- TNG eWallet, Touch 'n Go eWallet, FPX, bank transfer, and other e-wallet methods are not currently GreenPool checkout options. Never say they are supported.
- Passengers rate drivers after payment is completed; drivers can rate passengers after an eligible completed trip. Ratings are available for seven days after completion.

VEHICLES, LICENCES, AND PREFERENCES
- Drivers upload a driving licence and vehicle documents/photos for validation. A licence identity number cannot be shared by two drivers.
- Smoking, pets, and conversation preferences are saved with each published trip. Changing profile preferences affects future trips, not already published trips.

MESSAGES, LIVE JOURNEY, AND SAFETY
- Messages are available only for the relevant pending or accepted booking and close after cancellation or completion.
- During an active trip, participants can send non-emergency quick updates, see live location when permission and connection allow, and report a genuine emergency.
- In immediate danger, use GreenPool's Emergency button and call 999. Do not claim GreenPool contacts emergency services automatically.

ACCOUNT, NOTIFICATIONS, AND ATTRACTIONS
- Users can manage profile details, email/phone verification, Telegram link for OTP delivery, notifications, and account security.
- Tourist Attractions provides saved Malaysian places, search, filters, Google-powered details when available, and favourites.
TEXT;

    /**
     * Answer general questions safely while grounding GreenPool-specific
     * questions in the approved FAQ entries supplied by the application.
     *
     * @param  Collection<int, Faq>  $faqs
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

        if ($knownAnswer = $this->knownAnswer($question)) {
            return $knownAnswer;
        }

        $knowledge = $this->relevantFaqs($faqs, $question)
            ->map(fn (Faq $faq) => "[{$faq->category}] {$faq->question}\n{$faq->answer}")
            ->implode("\n\n");
        $capabilityMap = self::WEBSITE_CAPABILITY_MAP;

        $prompt = <<<TEXT
You are GreenPool Help, a precise and friendly support assistant for a Malaysian carpooling website.
The user is a {$user->role}. Answer only about GreenPool's current user-facing features.
First identify the user's specific intent, module, and named entity. A question about a payment method is not the same as a question about when payment is due; a question about cancelling a booking is not the same as cancelling a trip.
Use APPROVED FAQ CONTENT as the source of truth for precise rules, policies, payment conditions, and eligibility. When it does not directly answer a website-operation question, use only the WEBSITE CAPABILITY MAP to explain the relevant menu and normal workflow.
Answer the user's actual question first, then give the shortest useful next step. Mirror the user's language when possible. Give two to five short sentences, unless a concise answer is enough.
Do not invent app features, policies, prices, account information, payment methods, or steps that are not in those two sources. If a requested feature or payment method is not supported, say clearly that it is not currently available in GreenPool and name the supported alternative when known.
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
            'payment', 'pay', 'fare', 'receipt', 'refund', 'tng', 'touch n go', 'touchngo', 'ewallet', 'e wallet', 'fpx', 'stripe', 'cash', 'message', 'notification',
            'vehicle', 'licence', 'license', 'profile', 'account', 'rating', 'emergency',
            'attraction', 'favourite', 'favorite', 'settings', 'setting', 'help',
            'find a ride', 'my trips', 'app',
            '付款', '支付', '预订', '订单', '行程', '司机', '乘客', '车辆', '驾照', '执照', '评分', '消息', '通知', '紧急', '景点', '账号', '设置', '帮助', '电话', '电报',
            'pembayaran', 'tempahan', 'perjalanan', 'pemandu', 'penumpang', 'kenderaan', 'kecemasan',
        ] as $term) {
            if (str_contains($question, $term)) {
                return true;
            }
        }

        return false;
    }

    private function knownAnswer(string $question): ?string
    {
        $question = mb_strtolower($question);

        if (str_contains($question, 'tng') || str_contains($question, 'touch n go') || str_contains($question, 'touchngo') || str_contains($question, 'e-wallet') || str_contains($question, 'ewallet') || str_contains($question, 'fpx')) {
            if (preg_match('/\\p{Han}/u', $question)) {
                return 'GreenPool 目前不支持 TNG eWallet、FPX、银行转账或其他电子钱包。完成行程后，请到 Payments 或 My Bookings，选择 Stripe 的信用卡/借记卡付款，或选择 Cash 并把现金交给司机确认。';
            }

            return 'TNG eWallet and other e-wallet or bank-transfer methods are not currently available in GreenPool. After a completed trip, open Payments or My Bookings and choose Pay by Card through Stripe Checkout, or Pay Cash and hand the amount to your driver for confirmation.';
        }

        return null;
    }

    /** @param Collection<int, Faq> $faqs */
    private function relevantFaqs(Collection $faqs, string $question): Collection
    {
        $terms = collect(preg_split('/[^\\pL\\pN]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn (string $term) => mb_strlen($term) > 1)
            ->unique();

        return $faqs
            ->map(function (Faq $faq) use ($terms): array {
                $content = mb_strtolower(implode(' ', [
                    $faq->category,
                    $faq->question,
                    $faq->answer,
                    implode(' ', $faq->keywords ?? []),
                ]));
                $score = $terms->sum(fn (string $term) => str_contains($content, $term) ? 1 : 0);

                return compact('faq', 'score');
            })
            ->sortByDesc('score')
            ->take(6)
            ->pluck('faq')
            ->values();
    }

    private function plainText(string $answer): string
    {
        $answer = preg_replace('/^\s{0,3}#{1,6}\s*/m', '', $answer);
        $answer = str_replace(['**', '__', '`'], '', $answer);
        $answer = preg_replace('/^\s*[-*+]\s+/m', '', $answer);

        return mb_substr(trim((string) $answer), 0, 1500);
    }
}
