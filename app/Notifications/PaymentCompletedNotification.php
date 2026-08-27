<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'type' => 'payment_completed',
            'title' => 'Payment completed',
            'icon' => 'circle-check',
            'message' => 'Your payment of RM '.number_format((float) $this->payment->amount, 2).' was successful.',
            'url' => route('payments.show', $this->payment),
        ];
    }
}
