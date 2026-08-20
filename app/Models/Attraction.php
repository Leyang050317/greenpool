<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Attraction extends Model
{
    protected $table = 'tourist_attractions';

    protected $primaryKey = 'attraction_id';

    protected $fillable = [
        'attraction_name',
        'state',
        'description',
        'location',
        'image_url',
    ];

    public function favourites(): HasMany
    {
        return $this->hasMany(Favourite::class, 'attraction_id', 'attraction_id');
    }

    public function displayImageUrl(): string
    {
        if (filled($this->image_url)) {
            return $this->image_url;
        }

        return asset('images/attractions/placeholder.svg');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(AttractionDetail::class, 'attraction_id', 'attraction_id');
    }

    public function getCategoryAttribute(): string
    {
        return $this->presentationValue('category') ?? 'Attraction';
    }

    public function getOpeningHoursAttribute(): string
    {
        return $this->presentationValue('opening_hours') ?? 'To be confirmed';
    }

    public function getEntranceFeeAttribute(): string
    {
        return $this->presentationValue('entrance_fee') ?? 'To be confirmed';
    }

    public function getContactAttribute(): ?string
    {
        return $this->presentationValue('contact');
    }

    public function getWebsiteAttribute(): ?string
    {
        return $this->presentationValue('website');
    }

    private function presentationValue(string $key): ?string
    {
        if ($this->relationLoaded('detail') && $this->detail) {
            return $this->detail->{$key};
        }

        return null;
    }
}
