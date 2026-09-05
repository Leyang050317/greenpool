<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramOtpService
{
    /**
     * Generate a 6-digit OTP, cache it for 5 minutes,
     * and send it to the user via Telegram.
     *
     * @return bool True if the OTP was sent successfully.
     */
    public function sendTelegramOtp(User $user): bool
    {
        if (empty($user->telegram_chat_id)) {
            Log::warning("User {$user->id} has no linked Telegram chat ID. Cannot send OTP.");

            return false;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put('otp_'.$user->id, $otp, now()->addMinutes(5));

        $token = config('services.telegram.bot_token');

        if (! $token) {
            Log::error('Telegram bot token is not configured. Set TELEGRAM_BOT_TOKEN in your .env file.');

            return false;
        }

        $message = "Your GreenPool verification code is: <b>{$otp}</b>\n\nThis code expires in 5 minutes. Do not share it with anyone.";

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $user->telegram_chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::warning('Telegram OTP delivery failed.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'telegram_error_code' => data_get($response->json(), 'error_code'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Telegram OTP delivery could not be completed.', [
                'user_id' => $user->id,
                'exception_type' => $exception::class,
            ]);

            return false;
        }
    }

    /**
     * Verify a user-submitted OTP against the cached value.
     *
     * The cached OTP is deleted on successful verification to prevent reuse.
     *
     * @return bool True if the OTP matches.
     */
    public function verifyOtp(User $user, string $inputOtp): bool
    {
        $cached = Cache::get('otp_'.$user->id);

        if ($cached !== null && hash_equals((string) $cached, trim($inputOtp))) {
            Cache::forget('otp_'.$user->id);

            return true;
        }

        return false;
    }

    /**
     * Send a pre-generated OTP to the user's Telegram chat.
     *
     * Unlike sendTelegramOtp(), this method does NOT generate or cache
     * the OTP — it only handles the Telegram message delivery.
     * Use this when another part of the app (e.g. PhoneVerificationController)
     * manages its own OTP lifecycle.
     *
     * @return bool True if the message was sent successfully.
     */
    public function sendOtpMessage(User $user, string $otp): bool
    {
        if (empty($user->telegram_chat_id)) {
            Log::warning("User {$user->id} has no linked Telegram chat ID. Cannot send OTP message.");

            return false;
        }

        $token = config('services.telegram.bot_token');

        if (! $token) {
            Log::error('Telegram bot token is not configured. Set TELEGRAM_BOT_TOKEN in your .env file.');

            return false;
        }

        $message = "Your GreenPool phone verification code is: <b>{$otp}</b>\n\nThis code expires in 5 minutes. Do not share it with anyone.";

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $user->telegram_chat_id,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::warning('Telegram phone OTP delivery failed.', [
                    'user_id' => $user->id,
                    'status' => $response->status(),
                    'telegram_error_code' => data_get($response->json(), 'error_code'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Telegram phone OTP delivery could not be completed.', [
                'user_id' => $user->id,
                'exception_type' => $exception::class,
            ]);

            return false;
        }
    }
}
