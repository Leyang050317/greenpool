<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Payment $payment)
    {
        $this->payment->loadMissing(['payer', 'payee']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('driver.'.$this->payment->payee_id);
    }

    public function broadcastWith(): array
    {
        $totalEarnings = Payment::query()
            ->where('payee_id', $this->payment->payee_id)
            ->where('payment_status', 'Paid')
            ->sum('amount');

        return [
            'payment_id' => $this->payment->id,
            'amount' => (float) $this->payment->amount,
            'total_earnings' => (float) $totalEarnings,
            'title' => 'Payment received',
            'message' => $this->payment->payer->name.' paid RM '.number_format((float) $this->payment->amount, 2).'.',
            'url' => route('payments.show', $this->payment),
            'created_at' => $this->payment->paid_at?->toIso8601String(),
        ];
    }
}
