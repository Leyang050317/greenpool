<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'smoking_allowed',
        'pets_allowed',
        'conversation_preference',
    ];

    protected function casts(): array
    {
        return [
            'smoking_allowed' => 'boolean',
            'pets_allowed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
