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
        $terms = array_values(array_filter(explode(' ', $question), fn (string $term) => strlen($term) > 1));
        $faqs = Faq::query()->where('is_active', true)->orderBy('sort_order')->get();

        $match = $faqs
            ->map(function (Faq $faq) use ($question, $terms): array {
                $keywords = collect($faq->keywords ?? [])->map(fn ($keyword) => $this->normalise($keyword));
                $haystack = $keywords->push($this->normalise($faq->question))->implode(' ');
                $score = $keywords->sum(fn ($keyword) => $keyword !== '' && str_contains($question, $keyword) ? 6 : 0);
                $score += collect($terms)->sum(fn ($term) => str_contains($haystack, $term) ? 1 : 0);

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
}
