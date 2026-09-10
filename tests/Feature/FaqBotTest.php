<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use Database\Seeders\FaqSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FaqBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // General FAQ tests must stay deterministic and must never consume a
        // developer's real Gemini quota from the local .env file.
        config()->set('services.gemini.enabled', false);
        config()->set('services.gemini.key', null);
    }

    public function test_authenticated_user_can_get_featured_faqs_and_a_keyword_matched_answer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Faq::create([
            'question' => 'How do I pay for a ride?',
            'answer' => 'Open Payments after your completed trip.',
            'keywords' => ['payment', 'pay ride', 'checkout'],
            'category' => 'Payments',
            'is_featured' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($user)
            ->getJson(route('faq-bot.featured'))
            ->assertOk()
            ->assertJsonPath('data.0.question', 'How do I pay for a ride?');

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'Where can I make a payment?'])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('faq.answer', 'Open Payments after your completed trip.');
    }

    public function test_bot_returns_safe_fallback_and_suggestions_when_no_faq_matches(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Faq::create([
            'question' => 'How do I create a trip?',
            'answer' => 'Use My Trips.',
            'keywords' => ['create trip', 'driver'],
            'category' => 'Trips',
            'is_featured' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'What colour is the moon?'])
            ->assertOk()
            ->assertJsonPath('matched', false)
            ->assertJsonCount(1, 'suggestions');
    }

    public function test_bot_understands_greetings_and_common_booking_synonyms(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Faq::create([
            'question' => 'How do I find and book a ride?',
            'answer' => 'Open Find a Ride.',
            'keywords' => ['booking', 'find ride'],
            'category' => 'Bookings',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'Hi'])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('faq.id', 'greeting');

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'Can I reserve a ride?'])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('faq.answer', 'Open Find a Ride.');
    }

    public function test_bot_prefers_an_exact_approved_faq_when_it_is_enabled(): void
    {
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'gemini-test');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'You can manage notification preferences from Settings.']]]],
                ],
            ]),
        ]);
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'passenger']);
        Faq::create([
            'question' => 'How do I manage notifications?',
            'answer' => 'Open Settings.',
            'keywords' => ['notification preferences'],
            'category' => 'Settings',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'How do I manage notifications?'])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('faq.answer', 'Open Settings.');

        Http::assertNothingSent();
    }

    public function test_bot_refuses_a_non_greenpool_question_without_calling_gemini(): void
    {
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'gemini-test');
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'passenger']);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'What is the weather in Malaysia?'])
            ->assertOk()
            ->assertJsonPath('ai', true)
            ->assertJsonPath('answer', 'I can help only with GreenPool features, such as trips, bookings, payments, messages, ratings, vehicles, notifications, accounts, and tourist attractions.');

        Http::assertNothingSent();
    }

    public function test_gemini_output_is_returned_as_plain_text(): void
    {
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'gemini-test');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'Use **Find a Ride** to browse available trips.']]]],
                ],
            ]),
        ]);
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'passenger']);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'How do I use the booking feature?'])
            ->assertOk()
            ->assertJsonPath('ai', true)
            ->assertJsonPath('answer', 'Use Find a Ride to browse available trips.');
    }

    public function test_direct_create_trip_question_uses_the_seeded_approved_faq(): void
    {
        $this->seed(FaqSeeder::class);
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.key', 'test-key');
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'driver']);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'How do I create a trip?'])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('faq.question', 'How do I create a trip as a driver?');

        Http::assertNothingSent();
    }

    public function test_gemini_can_explain_a_greenpool_feature_when_the_faq_database_is_empty(): void
    {
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'gemini-test');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'As a driver, open My Trips, choose Create Trip, then select your verified vehicle and enter the trip details.']]]],
                ],
            ]),
        ]);
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'driver']);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'How do I create a trip as a driver?'])
            ->assertOk()
            ->assertJsonPath('ai', true)
            ->assertJsonPath('answer', 'As a driver, open My Trips, choose Create Trip, then select your verified vehicle and enter the trip details.');

        Http::assertSent(function ($request): bool {
            $prompt = data_get($request->data(), 'contents.0.parts.0.text', '');

            return str_contains($prompt, 'WEBSITE CAPABILITY MAP:')
                && str_contains($prompt, 'Drivers need a verified, valid driving licence');
        });
    }

    public function test_fuzzy_similarity_does_not_send_an_alerts_question_to_an_unrelated_payment_faq(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Faq::create([
            'question' => 'Where can I find my payment receipt?',
            'answer' => 'Open Payments and select the relevant payment.',
            'keywords' => ['payment receipt', 'receipt'],
            'category' => 'Payments',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'I keep getting too many app pop-ups. Can I choose which kinds of alerts reach me?'])
            ->assertOk()
            ->assertJsonPath('matched', false)
            ->assertJsonPath('title', 'I could not find an exact answer yet.');
    }

    public function test_full_approved_faq_catalog_does_not_misclassify_an_alert_preferences_question_as_payment(): void
    {
        $this->seed(FaqSeeder::class);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'I keep getting too many app pop-ups. Can I choose which kinds of alerts reach me?'])
            ->assertOk()
            ->assertJsonPath('matched', true)
            ->assertJsonPath('faq.question', 'How do notification settings work?');
    }

    public function test_bot_answers_tng_question_with_the_actual_supported_payment_methods(): void
    {
        $this->seed(FaqSeeder::class);
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.key', 'test-key');
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'passenger']);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'Can I pay with TNG?'])
            ->assertOk()
            ->assertJsonPath('ai', true)
            ->assertJsonPath('answer', 'TNG eWallet and other e-wallet or bank-transfer methods are not currently available in GreenPool. After a completed trip, open Payments or My Bookings and choose Pay by Card through Stripe Checkout, or Pay Cash and hand the amount to your driver for confirmation.');

        Http::assertNothingSent();
    }

    public function test_gemini_is_given_relevant_cross_module_context_for_a_natural_language_question(): void
    {
        $this->seed(FaqSeeder::class);
        config()->set('services.gemini.enabled', true);
        config()->set('services.gemini.key', 'test-key');
        config()->set('services.gemini.model', 'gemini-test');
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'During an active trip, use the Emergency button and call 999 if anyone is in immediate danger.']]]],
                ],
            ]),
        ]);
        $user = User::factory()->create(['email_verified_at' => now(), 'role' => 'passenger']);

        $this->actingAs($user)
            ->postJson(route('faq-bot.answer'), ['question' => 'What should I do if there is an emergency during my ride?'])
            ->assertOk()
            ->assertJsonPath('ai', true)
            ->assertJsonPath('answer', 'During an active trip, use the Emergency button and call 999 if anyone is in immediate danger.');

        Http::assertSent(function ($request): bool {
            $prompt = data_get($request->data(), 'contents.0.parts.0.text', '');

            return str_contains($prompt, 'First identify the user\'s specific intent')
                && str_contains($prompt, 'What should I do in an emergency?')
                && str_contains($prompt, 'call 999');
        });
    }
}
