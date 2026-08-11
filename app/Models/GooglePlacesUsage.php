<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GooglePlacesUsage extends Model
{
    protected $table = 'google_places_usage';

    protected $fillable = ['usage_date', 'request_count'];

    protected function casts(): array
    {
        return ['usage_date' => 'date'];
    }
}
