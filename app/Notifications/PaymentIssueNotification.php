<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentIssueNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Payment $payment, private readonly string $action) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $reported = $this->action === 'reported';

        return [
            'payment_id' => $this->payment->id,
            'booking_id' => $this->payment->booking_id,
            'type' => $reported ? 'payment_issue_reported' : 'payment_issue_resolved',
            'title' => $reported ? 'Payment issue reported' : 'Payment issue resolved',
            'icon' => 'message-square',
            'message' => $reported
                ? 'A payment issue was reported for RM '.number_format((float) $this->payment->amount, 2).'. You can discuss it in the trip chat.'
                : ($this->payment->isWaived() ? 'This payment was waived. No payment is required.' : 'The payment issue was reviewed. The payment is still due.'),
            'url' => route('payments.show', $this->payment),
        ];
    }
}
