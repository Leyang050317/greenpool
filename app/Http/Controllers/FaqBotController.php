<?php

namespace App\Http\Controllers;

use App\Models\Faq;
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

    public function answer(Request $request): JsonResponse
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

        $match = $faqs
            ->map(function (Faq $faq) use ($question, $terms): array {
                $keywords = collect($faq->keywords ?? [])->map(fn ($keyword) => $this->normalise($keyword));
                $haystack = $keywords->push($this->normalise($faq->question), $this->normalise($faq->answer))->implode(' ');
                $score = $keywords->sum(fn ($keyword) => $keyword !== '' && str_contains($question, $keyword) ? 8 : 0);
                $score += collect($terms)->sum(function (string $term) use ($haystack): int {
                    if (str_contains($haystack, $term)) {
                        return 2;
                    }

                    return collect(explode(' ', $haystack))
                        ->contains(fn (string $candidate) => strlen($candidate) >= 4 && similar_text($term, $candidate) / max(strlen($term), strlen($candidate)) >= 0.82) ? 1 : 0;
                });

                return ['faq' => $faq, 'score' => $score];
            })
            ->filter(fn (array $result) => $result['score'] >= 2)
            ->sortByDesc('score')
            ->first();

        if (! $match) {
            return response()->json([
                'matched' => false,
                'title' => 'I could not find an exact answer yet.',
                'answer' => 'Try keywords such as booking, trip, payment, attractions, messages, notifications, or vehicle. You can also use the relevant menu page for more help.',
                'suggestions' => $faqs->where('is_featured', true)->take(4)->map(fn (Faq $faq) => ['id' => $faq->id, 'question' => $faq->question])->values(),
            ]);
        }

        /** @var Faq $faq */
        $faq = $match['faq'];

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
