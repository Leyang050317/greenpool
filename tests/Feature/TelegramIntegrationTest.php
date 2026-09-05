<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config([
            'services.telegram.bot_token' => 'test-bot-token',
            'services.telegram.bot_username' => 'GreenPoolTestBot',
            'services.telegram.webhook_secret' => 'test_webhook-secret',
        ]);

    }

    public function test_guest_cannot_generate_a_telegram_link(): void
    {
        $this->get(route('telegram.link'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_gets_a_short_lived_opaque_link_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('telegram.link'));
        $location = $response->headers->get('Location');

        $response->assertRedirectContains('https://t.me/GreenPoolTestBot?start=');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);
        $token = $query['start'] ?? '';

        $this->assertSame(48, strlen($token));
        $this->assertNotSame((string) $user->id, $token);
        $this->assertSame($user->id, Cache::get($this->linkCacheKey($token)));
    }

    public function test_webhook_rejects_missing_or_incorrect_secret(): void
    {
        $payload = $this->startPayload('token', '123');

        $this->postJson(route('telegram.webhook'), $payload)->assertForbidden();
        $this->postJson(route('telegram.webhook'), $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'incorrect',
        ])->assertForbidden();
    }

    public function test_valid_one_time_token_links_the_intended_user(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $user = User::factory()->create();
        $token = str_repeat('a', 48);
        Cache::put($this->linkCacheKey($token), $user->id, now()->addMinutes(10));

        $this->postJson(route('telegram.webhook'), $this->startPayload($token, '987654321'), $this->webhookHeaders())
            ->assertOk();

        $this->assertSame('987654321', $user->fresh()->telegram_chat_id);
        $this->assertNull(Cache::get($this->linkCacheKey($token)));
        Http::assertSent(fn (Request $request) => $request['chat_id'] === '987654321'
            && str_contains((string) $request['text'], 'linked to GreenPool successfully'));
    }

    public function test_link_token_cannot_be_reused_for_another_chat(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $user = User::factory()->create();
        $token = str_repeat('b', 48);
        Cache::put($this->linkCacheKey($token), $user->id, now()->addMinutes(10));

        $this->postJson(route('telegram.webhook'), $this->startPayload($token, '111'), $this->webhookHeaders());
        $this->postJson(route('telegram.webhook'), $this->startPayload($token, '222'), $this->webhookHeaders());

        $this->assertSame('111', $user->fresh()->telegram_chat_id);
    }

    public function test_chat_already_owned_by_another_user_is_not_reassigned(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $owner = User::factory()->create(['telegram_chat_id' => '333']);
        $otherUser = User::factory()->create();
        $token = str_repeat('c', 48);
        Cache::put($this->linkCacheKey($token), $otherUser->id, now()->addMinutes(10));

        $this->postJson(route('telegram.webhook'), $this->startPayload($token, '333'), $this->webhookHeaders())
            ->assertOk();

        $this->assertSame('333', $owner->fresh()->telegram_chat_id);
        $this->assertNull($otherUser->fresh()->telegram_chat_id);
    }

    public function test_user_can_unlink_telegram(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => '444']);

        $this->actingAs($user)
            ->delete(route('telegram.unlink'))
            ->assertSessionHas('status', 'telegram-unlinked');

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_phone_otp_is_not_created_without_a_linked_telegram_account(): void
    {
        $user = User::factory()->create([
            'phone_number' => null,
            'telegram_chat_id' => null,
        ]);

        $this->actingAs($user)
            ->post(route('phone-verification.send'), ['phone_number' => '+60 12-345 6789'])
            ->assertSessionHasErrors('telegram');

        $this->assertNull($user->fresh()->phone_number);
        $this->assertNull(Cache::get('phone-verification:'.$user->id));
        Http::assertNothingSent();
    }

    public function test_failed_telegram_delivery_restores_existing_phone_state(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false], 500)]);
        $verifiedAt = now()->subDay()->startOfSecond();
        $user = User::factory()->create([
            'phone_number' => '+60 12-345 6789',
            'phone_verified_at' => $verifiedAt,
            'telegram_chat_id' => '555',
        ]);

        $this->actingAs($user)->post(route('phone-verification.send'), [
            'phone_number' => '+60 19-876 5432',
            'confirm_phone_change' => '1',
        ])->assertSessionHasErrors('telegram');

        $freshUser = $user->fresh();
        $this->assertSame('+60 12-345 6789', $freshUser->phone_number);
        $this->assertTrue($verifiedAt->equalTo($freshUser->phone_verified_at));
        $this->assertNull(Cache::get('phone-verification:'.$user->id));
    }

    public function test_webhook_can_be_registered_without_exposing_credentials(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $exitCode = Artisan::call('telegram:set-webhook', [
            '--url' => 'https://greenpool.example/telegram/webhook',
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringNotContainsString('test-bot-token', Artisan::output());
        $this->assertStringNotContainsString('test_webhook-secret', Artisan::output());
        Http::assertSent(fn (Request $request) => $request['url'] === 'https://greenpool.example/telegram/webhook'
            && $request['secret_token'] === 'test_webhook-secret');
    }

    private function startPayload(string $token, string $chatId): array
    {
        return [
            'message' => [
                'chat' => ['id' => $chatId],
                'text' => '/start '.$token,
            ],
        ];
    }

    private function webhookHeaders(): array
    {
        return ['X-Telegram-Bot-Api-Secret-Token' => 'test_webhook-secret'];
    }

    private function linkCacheKey(string $token): string
    {
        return 'telegram-link:'.hash('sha256', $token);
    }
}
