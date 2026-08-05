<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attraction extends Model
{
    private const PRESENTATION = [
        'Petronas Twin Towers' => [
            'category' => 'Landmark',
            'opening_hours' => 'Daily 9:00 AM - 9:00 PM',
            'entrance_fee' => 'RM 85 (Adults)',
            'contact' => '+60 3-2331 8080',
            'website' => 'petronastwintowers.com.my',
        ],
        'Batu Caves' => [
            'category' => 'Culture & Heritage',
            'opening_hours' => 'Daily 6:00 AM - 9:00 PM',
            'entrance_fee' => 'Free',
            'contact' => '+60 3-6189 6284',
            'website' => 'batucaves.com',
        ],
        'Penang Hill' => [
            'category' => 'Nature & Scenic',
            'opening_hours' => '6:30 AM - 11:00 PM',
            'entrance_fee' => 'Varies by visitor category',
            'contact' => '+60 4-828 8880',
            'website' => 'penanghill.gov.my',
        ],
        'Mount Kinabalu' => [
            'category' => 'Nature & Scenic',
            'opening_hours' => 'Varies by trail',
            'entrance_fee' => 'RM 200 (includes guide)',
            'contact' => '+60 88-889098',
            'website' => 'sabahtourism.com',
        ],
        'Jonker Street' => [
            'category' => 'Culture & Heritage',
            'opening_hours' => 'Daily 10:00 AM - 10:00 PM',
            'entrance_fee' => 'Free',
            'contact' => null,
            'website' => null,
        ],
        'George Town' => [
            'category' => 'Culture & Heritage',
            'opening_hours' => 'Open 24 hours',
            'entrance_fee' => 'Free',
            'contact' => null,
            'website' => 'visitpenang.gov.my',
        ],
    ];

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
        return self::PRESENTATION[$this->attraction_name][$key] ?? null;
    }
}
