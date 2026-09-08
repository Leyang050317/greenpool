<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttractionDetail extends Model
{
    protected $primaryKey = 'detail_id';

    protected $fillable = [
        'attraction_id', 'category', 'rating', 'user_rating_count', 'opening_hours', 'entrance_fee', 'contact', 'website',
        'source_name', 'source_place_id', 'google_place_id', 'latitude', 'longitude', 'image_attribution',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'rating' => 'float',
            'user_rating_count' => 'integer',
        ];
    }

    public function attraction(): BelongsTo
    {
        return $this->belongsTo(Attraction::class, 'attraction_id', 'attraction_id');
    }
}
