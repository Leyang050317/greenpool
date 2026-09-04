<?php

namespace Tests\Feature\Mail;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BrevoTransportTest extends TestCase
{
    public function test_it_sends_laravel_mail_through_brevo_https_api(): void
    {
        config([
            'mail.default' => 'brevo',
            'mail.from.address' => 'greenpool@example.test',
            'mail.from.name' => 'GreenPool',
            'mail.mailers.brevo' => [
                'transport' => 'brevo',
                'key' => 'xkeysib-test-key',
            ],
        ]);

        Mail::forgetMailers();
        Http::fake([
            'https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'brevo-message-id'], 201),
        ]);

        Mail::raw('Test message', function ($message): void {
            $message->to('passenger@example.test', 'Passenger')
                ->subject('GreenPool test email');
        });

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.brevo.com/v3/smtp/email'
                && $request->header('api-key')[0] === 'xkeysib-test-key'
                && $request['sender'] === ['email' => 'greenpool@example.test', 'name' => 'GreenPool']
                && $request['to'] === [['email' => 'passenger@example.test', 'name' => 'Passenger']]
                && $request['subject'] === 'GreenPool test email'
                && $request['textContent'] === 'Test message';
        });
    }
}
