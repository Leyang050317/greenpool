<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const METHODS = [
        'stripe' => 'Stripe Checkout',
        'fpx' => 'Online Banking (FPX)',
        'card' => 'Credit / Debit Card',
        'e_wallet' => 'E-Wallet',
        'cash' => 'Cash',
    ];

    protected $fillable = [
        'booking_id', 'payer_id', 'payee_id', 'amount', 'payment_method',
        'payment_status', 'transaction_reference', 'stripe_checkout_session_id',
        'stripe_payment_intent_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payee_id');
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'Paid';
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->payment_method] ?? 'Not selected';
    }
}
