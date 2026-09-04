<?php

namespace App\Mail\Transport;

use Illuminate\Http\Client\Factory as HttpFactory;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class BrevoTransport extends AbstractTransport
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $apiKey,
    ) {
        parent::__construct();
    }

    /**
     * Send a Laravel mail message through Brevo's HTTPS transactional API.
     */
    protected function doSend(SentMessage $message): void
    {
        if ($this->apiKey === '') {
            throw new TransportException('Brevo API key is not configured. Set BREVO_API_KEY before using the brevo mailer.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();
        $from = $email->getFrom()[0] ?? $envelope->getSender();

        $payload = array_filter([
            'sender' => $this->address($from),
            'to' => $this->addresses($this->recipients($email, $envelope)),
            'cc' => $this->addresses($email->getCc()),
            'bcc' => $this->addresses($email->getBcc()),
            'replyTo' => $email->getReplyTo() === [] ? null : $this->address($email->getReplyTo()[0]),
            'subject' => $email->getSubject(),
            'htmlContent' => $email->getHtmlBody(),
            'textContent' => $email->getTextBody(),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        $response = $this->http
            ->acceptJson()
            ->asJson()
            ->withHeaders(['api-key' => $this->apiKey])
            ->post('https://api.brevo.com/v3/smtp/email', $payload);

        if (! $response->successful()) {
            $reason = trim($response->body()) ?: 'No response body returned.';

            throw new TransportException(sprintf(
                'Brevo API request failed with HTTP %d: %s',
                $response->status(),
                $reason,
            ));
        }

        if (is_string($messageId = $response->json('messageId'))) {
            $message->setMessageId($messageId);
        }
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName() ?: null,
        ]);
    }

    /**
     * @param  Address[]  $addresses
     * @return array<int, array{email: string, name?: string}>
     */
    private function addresses(array $addresses): array
    {
        return array_map($this->address(...), $addresses);
    }

    /**
     * @return Address[]
     */
    private function recipients(Email $email, Envelope $envelope): array
    {
        return array_values(array_filter(
            $envelope->getRecipients(),
            static fn (Address $address): bool => ! in_array($address, [...$email->getCc(), ...$email->getBcc()], true),
        ));
    }

    public function __toString(): string
    {
        return 'brevo';
    }
}
