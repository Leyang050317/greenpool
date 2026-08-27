<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'trip_updates', 'booking_updates', 'payment_updates', 'message_alerts', 'rating_reminders', 'attraction_updates'];

    protected function casts(): array
    {
        return ['trip_updates' => 'boolean', 'booking_updates' => 'boolean', 'payment_updates' => 'boolean', 'message_alerts' => 'boolean', 'rating_reminders' => 'boolean', 'attraction_updates' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
