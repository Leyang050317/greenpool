<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const METHODS = [
        'stripe' => 'Credit / Debit Card',
        'fpx' => 'Online Banking (FPX)',
        'card' => 'Credit / Debit Card',
        'e_wallet' => 'E-Wallet',
        'cash' => 'Cash',
    ];

    protected $fillable = [
        'booking_id', 'payer_id', 'payee_id', 'amount', 'payment_method',
        'payment_status', 'transaction_reference', 'stripe_checkout_session_id',
        'stripe_payment_intent_id', 'paid_at', 'issue_reason', 'issue_details',
        'issue_reported_at', 'issue_resolution', 'issue_resolution_details',
        'issue_resolved_at', 'issue_resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'issue_reported_at' => 'datetime',
            'issue_resolved_at' => 'datetime',
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

    public function isUnderReview(): bool
    {
        return $this->payment_status === 'Under Review';
    }

    public function isWaived(): bool
    {
        return $this->payment_status === 'Waived';
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->payment_method] ?? 'Not selected';
    }
}
