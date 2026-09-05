<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook updates.
     *
     * Parses the Telegram JSON payload and processes deep-linking
     * commands of the form: /start {user_id}
     */
    public function handle(Request $request): JsonResponse
    {
        $message = $request->input('message');

        if (! $message) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $chatId = data_get($message, 'chat.id');
        $text = trim((string) data_get($message, 'text', ''));

        if (! $chatId || $text === '') {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Deep linking: /start {user_id}
        if (preg_match('/^\/start\s+(\d+)$/', $text, $matches)) {
            $this->handleStartCommand((int) $matches[1], (string) $chatId);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Link the Telegram chat to the given user and send a confirmation message.
     */
    private function handleStartCommand(int $userId, string $chatId): void
    {
        $user = User::find($userId);

        if (! $user) {
            $this->sendMessage($chatId, 'Sorry, no matching GreenPool account was found. Please go back to the website and try again.');
            return;
        }

        $user->update(['telegram_chat_id' => $chatId]);

        $this->sendMessage(
            $chatId,
            'Welcome, ' . e($user->name) . "!\n\nYour Telegram account has been linked to GreenPool successfully. You will receive OTP verification codes in this chat."
        );
    }

    /**
     * Send a plain-text message via the Telegram Bot API.
     */
    private function sendMessage(string $chatId, string $text): void
    {
        $token = config('services.telegram.bot_token');

        if (! $token) {
            Log::error('Telegram bot token is not configured. Set TELEGRAM_BOT_TOKEN in your .env file.');
            return;
        }

        Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);
    }
}
