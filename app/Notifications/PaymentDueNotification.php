<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentDueNotification extends Notification
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
            'booking_id' => $this->payment->booking_id,
            'type' => 'payment_due',
            'title' => 'Payment is due',
            'icon' => 'credit-card',
            'message' => 'Your trip is complete. Please pay RM '.number_format((float) $this->payment->amount, 2).'.',
            'url' => route('payments.checkout', $this->payment->booking_id),
        ];
    }
}
