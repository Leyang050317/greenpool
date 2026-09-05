<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramWebhookController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $username = ltrim((string) config('services.telegram.bot_username'), '@');

        if ($username === '' || preg_match('/^[A-Za-z0-9_]{5,32}$/', $username) !== 1) {
            return back()->withErrors([
                'telegram' => 'Telegram linking is not configured. Please contact support.',
            ]);
        }

        // Telegram deep-link payloads are limited to 64 URL-safe characters.
        // Store only a hash so a cache leak cannot reveal usable link tokens.
        $token = Str::random(48);
        Cache::put($this->linkCacheKey($token), $request->user()->id, now()->addMinutes(10));

        return redirect()->away("https://t.me/{$username}?start={$token}");
    }

    public function unlink(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['telegram_chat_id' => null])->save();

        return back()->with('status', 'telegram-unlinked');
    }

    /**
     * Handle incoming Telegram webhook updates.
     *
     * Parses the Telegram JSON payload and processes secure deep-link commands.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = (string) config('services.telegram.webhook_secret');
        $providedSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if ($secret === '') {
            Log::error('Telegram webhook secret is not configured.');

            return response()->json(['status' => 'unavailable'], 503);
        }

        if ($providedSecret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $chatId = data_get($message, 'chat.id');
        $text = trim((string) data_get($message, 'text', ''));

        if (! $chatId || $text === '') {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Deep linking uses a short-lived, one-time token rather than a
        // predictable database user ID.
        if (preg_match('/^\/start\s+([A-Za-z0-9_-]{32,64})$/', $text, $matches)) {
            $this->handleStartCommand($matches[1], (string) $chatId);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Link the Telegram chat to the given user and send a confirmation message.
     */
    private function handleStartCommand(string $token, string $chatId): void
    {
        $userId = Cache::pull($this->linkCacheKey($token));
        $user = is_numeric($userId) ? User::find((int) $userId) : null;

        if (! $user || ! $user->isActive()) {
            $this->sendMessage($chatId, 'This GreenPool link is invalid or has expired. Please generate a new link from your GreenPool profile.');

            return;
        }

        $existingOwner = User::query()
            ->where('telegram_chat_id', $chatId)
            ->whereKeyNot($user->id)
            ->exists();

        if ($existingOwner) {
            $this->sendMessage($chatId, 'This Telegram account is already linked to another GreenPool account. Unlink it there before trying again.');

            return;
        }

        try {
            $user->update(['telegram_chat_id' => $chatId]);
        } catch (QueryException $exception) {
            Log::warning('Telegram account link could not be saved.', [
                'user_id' => $user->id,
                'exception_type' => $exception::class,
            ]);
            $this->sendMessage($chatId, 'Telegram could not be linked. Please return to GreenPool and try again.');

            return;
        }

        $this->sendMessage(
            $chatId,
            'Welcome, '.e($user->name)."!\n\nYour Telegram account has been linked to GreenPool successfully. You will receive OTP verification codes in this chat."
        );
    }

    /**
     * Send a plain-text message via the Telegram Bot API.
     */
    private function sendMessage(string $chatId, string $text): bool
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            Log::error('Telegram bot token is not configured. Set TELEGRAM_BOT_TOKEN in your .env file.');

            return false;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::warning('Telegram message delivery failed.', [
                    'status' => $response->status(),
                    'telegram_error_code' => data_get($response->json(), 'error_code'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            // Exception messages may contain the request URL, which embeds the
            // bot token. Log only the exception type.
            Log::warning('Telegram message delivery could not be completed.', [
                'exception_type' => $exception::class,
            ]);

            return false;
        }
    }

    private function linkCacheKey(string $token): string
    {
        return 'telegram-link:'.hash('sha256', $token);
    }
}
