<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favourite extends Model
{
    protected $primaryKey = 'favourite_id';

    protected $fillable = [
        'attraction_id',
        'user_id',
    ];

    public function attraction(): BelongsTo
    {
        return $this->belongsTo(Attraction::class, 'attraction_id', 'attraction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
