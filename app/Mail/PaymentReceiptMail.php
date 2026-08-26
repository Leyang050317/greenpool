<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment, public ?array $fareBreakdown) {}

    public function build(): self
    {
        return $this->subject('GreenPool E-Receipt · '.$this->payment->transaction_reference)
            ->view('emails.payment-receipt');
    }
}
