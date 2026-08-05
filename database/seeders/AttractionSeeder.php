<?php

namespace Database\Seeders;

use App\Models\Attraction;
use Illuminate\Database\Seeder;

class AttractionSeeder extends Seeder
{
    public function run(): void
    {
        $attractions = [
            [
                'attraction_name' => 'Petronas Twin Towers',
                'state' => 'Kuala Lumpur',
                'description' => 'Iconic twin skyscrapers that define the Kuala Lumpur skyline, once the tallest buildings in the world.',
                'location' => 'Kuala Lumpur',
                'image_url' => 'https://images.unsplash.com/photo-1597148543182-830ef7bbb904?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=800',
            ],
            [
                'attraction_name' => 'Batu Caves',
                'state' => 'Selangor',
                'description' => 'Ancient limestone caves housing sacred Hindu shrines, reached by 272 colourful steps at the entrance.',
                'location' => 'Gombak, Selangor',
                'image_url' => 'https://images.unsplash.com/photo-1561176162-0b01b0df76ed?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=800',
            ],
            [
                'attraction_name' => 'Penang Hill',
                'state' => 'Penang',
                'description' => 'Panoramic views overlooking George Town and Penang Island from 830 metres above sea level.',
                'location' => 'Air Itam, Penang',
                'image_url' => 'https://images.unsplash.com/photo-1641305012733-6ec9dc01918a?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=800',
            ],
            [
                'attraction_name' => 'Mount Kinabalu',
                'state' => 'Sabah',
                'description' => "Southeast Asia's highest peak and a UNESCO World Heritage site, rising to 4,095 metres.",
                'location' => 'Kinabalu Park, Sabah',
                'image_url' => 'https://images.unsplash.com/photo-1670308268425-6948df38f7a2?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=800',
            ],
            [
                'attraction_name' => 'Jonker Street',
                'state' => 'Melaka',
                'description' => "Melaka's most iconic heritage street, lined with antique shops, art galleries, and local cuisine stalls.",
                'location' => 'Melaka City, Melaka',
                'image_url' => 'https://images.unsplash.com/photo-1600320637615-02d3d91816df?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=800',
            ],
            [
                'attraction_name' => 'George Town',
                'state' => 'Penang',
                'description' => 'A UNESCO World Heritage city known for remarkable colonial architecture, street art, and hawker food.',
                'location' => 'George Town, Penang',
                'image_url' => 'https://images.unsplash.com/photo-1591802266103-2816a8828a7e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&q=80&w=800',
            ],
        ];

        foreach ($attractions as $attraction) {
            Attraction::query()->updateOrCreate(
                ['attraction_name' => $attraction['attraction_name'], 'state' => $attraction['state']],
                $attraction,
            );
        }

        Attraction::query()
            ->whereIn('attraction_name', ['Langkawi Sky Bridge', 'Perhentian Islands', 'Sarawak Cultural Village'])
            ->delete();
    }
}
