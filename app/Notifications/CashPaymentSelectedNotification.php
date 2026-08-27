<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CashPaymentSelectedNotification extends Notification
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
            'booking_id' => $this->payment->booking_id,
            'type' => 'cash_payment_selected',
            'title' => 'Cash payment awaiting confirmation',
            'icon' => 'banknote',
            'message' => $this->payment->payer->name.' selected cash for RM '.number_format((float) $this->payment->amount, 2).'. Confirm after receiving it.',
            'url' => route('payments.show', $this->payment).'#cash-confirmation',
        ];
    }
}
