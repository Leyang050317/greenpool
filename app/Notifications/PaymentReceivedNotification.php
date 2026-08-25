<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->payment->loadMissing('payer');

        return [
            'payment_id' => $this->payment->id,
            'type' => 'payment_received',
            'title' => 'Payment received',
            'icon' => 'circle-check',
            'message' => $this->payment->payer->name.' paid RM '.number_format((float) $this->payment->amount, 2).'.',
            'url' => route('payments.show', $this->payment),
        ];
    }
}
