<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Services\GeminiFaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqBotController extends Controller
{
    public function featured(): JsonResponse
    {
        return response()->json([
            'data' => Faq::query()
                ->where('is_active', true)
                ->where('is_featured', true)
                ->orderBy('sort_order')
                ->limit(6)
                ->get(['id', 'question', 'category']),
        ]);
    }

    public function answer(Request $request, GeminiFaqService $gemini): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'min:2', 'max:300'],
        ]);

        $question = $this->normalise($validated['question']);
        $terms = $this->meaningfulTerms($question);
        $faqs = Faq::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($this->isGreeting($question)) {
            return response()->json([
                'matched' => true,
                'faq' => [
                    'id' => 'greeting',
                    'question' => 'Hello!',
                    'answer' => 'I can help with trips, bookings, payments, messages, ratings, notifications, vehicles, account setup, and tourist attractions. What would you like to do?',
                    'category' => 'General',
                ],
            ]);
        }

        $match = $this->findFaqMatch($faqs, $question, $terms);

        // Direct questions have approved, deterministic answers. Return those
        // before Gemini so model wording or a deployment setting cannot hide a
        // core GreenPool guide such as how to create a trip.
        if ($match && ($match['keyword_score'] >= 8 || $match['exact_question'])) {
            return $this->matchedFaqResponse($match['faq']);
        }

        // When Gemini is configured, let it select the relevant approved FAQ
        // before the lightweight keyword fallback runs. This avoids a shared
        // word such as "keep" incorrectly sending an alerts question to the
        // payment-receipt FAQ.
        $aiAnswer = $gemini->answer($validated['question'], $faqs, $request->user());

        if ($aiAnswer !== null) {
            return response()->json([
                'matched' => false,
                'ai' => true,
                'title' => 'GreenPool AI Help',
                'answer' => $aiAnswer,
                'suggestions' => $faqs->where('is_featured', true)->take(4)->map(fn (Faq $faq) => ['id' => $faq->id, 'question' => $faq->question])->values(),
            ]);
        }

        if (! $match) {
            return response()->json([
                'matched' => false,
                'title' => 'I could not find an exact answer yet.',
                'answer' => 'Try keywords such as booking, trip, payment, attractions, messages, notifications, or vehicle. You can also use the relevant menu page for more help.',
                'suggestions' => $faqs->where('is_featured', true)->take(4)->map(fn (Faq $faq) => ['id' => $faq->id, 'question' => $faq->question])->values(),
            ]);
        }

        return $this->matchedFaqResponse($match['faq']);
    }

    /** @return array{faq: Faq, score: int, keyword_score: int, exact_question: bool}|null */
    private function findFaqMatch($faqs, string $question, array $terms): ?array
    {
        return $faqs
            ->map(function (Faq $faq) use ($question, $terms): array {
                $faqKeywords = collect($faq->keywords ?? [])->map(fn ($keyword) => $this->normalise($keyword));
                $haystack = $faqKeywords->concat([$this->normalise($faq->question), $this->normalise($faq->answer)])->implode(' ');
                $keywordScore = $faqKeywords->sum(function (string $keyword) use ($question, $terms): int {
                    if ($keyword === '') {
                        return 0;
                    }

                    // Allow natural wording such as "create a trip" to match
                    // the approved keyword "create trip" without treating an
                    // unrelated single word as a direct match.
                    $keywordTerms = array_filter(explode(' ', $keyword));
                    $allKeywordTermsPresent = $keywordTerms !== []
                        && collect($keywordTerms)->every(fn (string $term) => in_array($term, $terms, true));

                    return str_contains($question, $keyword) || $allKeywordTermsPresent ? 8 : 0;
                });
                $keywordTermMatches = collect($terms)->filter(fn (string $term) => $faqKeywords->contains($term))->count();
                $exactTermMatches = collect($terms)->filter(fn (string $term) => str_contains($haystack, $term))->count();
                $score = $keywordScore + ($exactTermMatches * 2);
                $score += collect($terms)->sum(function (string $term) use ($haystack): int {
                    if (str_contains($haystack, $term)) {
                        return 0;
                    }

                    return collect(explode(' ', $haystack))
                        ->contains(fn (string $candidate) => strlen($candidate) >= 4 && similar_text($term, $candidate) / max(strlen($term), strlen($candidate)) >= 0.82) ? 1 : 0;
                });

                // FAQ fallback is deliberately conservative. General words in
                // the question or in an answer are not enough to claim a match.
                return [
                    'faq' => $faq,
                    'score' => $score,
                    'keyword_score' => $keywordScore,
                    'exact_question' => $question === $this->normalise($faq->question),
                    'has_direct_match' => $keywordScore > 0 || $keywordTermMatches > 0,
                ];
            })
            // A fuzzy similarity by itself is not enough; words such as
            // "alerts" and "receipt" must never redirect a user to payment help.
            ->filter(fn (array $result) => ($result['has_direct_match'] || $result['exact_question']) && $result['score'] >= 2)
            ->sortByDesc('score')
            ->first();
    }

    private function matchedFaqResponse(Faq $faq): JsonResponse
    {
        return response()->json([
            'matched' => true,
            'faq' => [
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'category' => $faq->category,
            ],
        ]);
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower($value);

        return trim((string) preg_replace('/[^\\pL\\pN]+/u', ' ', $value));
    }

    /** @return list<string> */
    private function meaningfulTerms(string $question): array
    {
        $aliases = [
            'carpool' => 'trip', 'ride' => 'booking', 'book' => 'booking', 'reserve' => 'booking',
            'chat' => 'message', 'text' => 'message', 'favourite' => 'attraction', 'favorite' => 'attraction',
            'vehicle' => 'car', 'licence' => 'license', 'money' => 'payment', 'paid' => 'payment',
            // Users commonly describe notification preferences as alerts or
            // pop-ups, so direct the offline FAQ fallback to Settings too.
            'alert' => 'settings notification', 'alerts' => 'settings notification',
            'popup' => 'settings notification', 'popups' => 'settings notification',
        ];
        $stopWords = ['a', 'an', 'and', 'are', 'can', 'do', 'for', 'how', 'i', 'in', 'is', 'it', 'my', 'of', 'on', 'the', 'to', 'what', 'where', 'why', 'with', 'you'];

        return collect(explode(' ', $question))
            ->map(fn (string $term) => $aliases[$term] ?? $term)
            ->filter(fn (string $term) => strlen($term) > 1 && ! in_array($term, $stopWords, true))
            ->unique()
            ->values()
            ->all();
    }

    private function isGreeting(string $question): bool
    {
        return in_array($question, ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'thanks', 'thank you'], true);
    }
}
