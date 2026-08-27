<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqBotTest extends TestCase
{
    use RefreshDatabase;

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
}
