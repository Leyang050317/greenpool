<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    public const EDIT_WINDOW_DAYS = 7;

    protected $fillable = ['booking_id', 'reviewer_id', 'reviewee_id', 'score', 'comment'];

    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    public function booking(): BelongsTo { return $this->belongsTo(Booking::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
    public function reviewee(): BelongsTo { return $this->belongsTo(User::class, 'reviewee_id'); }

    public function isEditable(): bool
    {
        return $this->created_at !== null
            && now()->lessThanOrEqualTo($this->created_at->copy()->addDays(self::EDIT_WINDOW_DAYS));
    }

    public function editableUntil(): ?\Illuminate\Support\Carbon
    {
        return $this->created_at?->copy()->addDays(self::EDIT_WINDOW_DAYS);
    }
}
