<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--url= : Public HTTPS webhook URL}';

    protected $description = 'Register the GreenPool Telegram webhook without printing credentials';

    public function handle(): int
    {
        $token = (string) config('services.telegram.bot_token');
        $secret = (string) config('services.telegram.webhook_secret');
        $url = (string) ($this->option('url') ?: rtrim((string) config('app.url'), '/').'/telegram/webhook');

        if ($token === '' || $secret === '') {
            $this->error('TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET must be configured.');

            return self::FAILURE;
        }

        if (preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret) !== 1) {
            $this->error('TELEGRAM_WEBHOOK_SECRET may contain only letters, numbers, underscores, and hyphens.');

            return self::FAILURE;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false || ! str_starts_with($url, 'https://')) {
            $this->error('The Telegram webhook must be a valid public HTTPS URL.');

            return self::FAILURE;
        }

        try {
            $response = Http::asForm()->timeout(15)->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $url,
                'secret_token' => $secret,
                'allowed_updates' => json_encode(['message'], JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable $exception) {
            $this->error('Telegram could not be reached. Try again after checking the service connection.');

            return self::FAILURE;
        }

        if ($response->failed() || ! $response->json('ok')) {
            $this->error('Telegram rejected the webhook configuration. Check the bot credentials and public URL.');

            return self::FAILURE;
        }

        $this->info("Telegram webhook registered for {$url}");

        return self::SUCCESS;
    }
}
