<?php

namespace App\Console\Commands;

use App\Services\Attractions\GooglePlacesService;
use Illuminate\Console\Command;
use RuntimeException;

class PreviewGoogleTouristAttractions extends Command
{
    protected $signature = 'attractions:preview-google
                            {--state= : Preview one Malaysian state only}
                            {--per-state=20 : Maximum candidates to request for each state (1-20)}';

    protected $description = 'Preview photo-ready Malaysian tourist attractions from Google Places without changing the database.';

    /** @var list<string> */
    private const STATES = [
        'Johor', 'Kedah', 'Kelantan', 'Kuala Lumpur', 'Labuan', 'Melaka',
        'Negeri Sembilan', 'Pahang', 'Penang', 'Perak', 'Perlis', 'Putrajaya',
        'Sabah', 'Sarawak', 'Selangor', 'Terengganu',
    ];

    public function handle(GooglePlacesService $googlePlaces): int
    {
        $requestedState = trim((string) $this->option('state'));
        $states = $requestedState === '' ? self::STATES : [$requestedState];
        $perState = (int) $this->option('per-state');

        if ($perState < 1 || $perState > 20) {
            $this->error('--per-state must be between 1 and 20.');

            return self::INVALID;
        }

        $this->info('Previewing Google Places tourist_attraction results. No database records will be created, updated, or deleted.');
        $rows = [];

        foreach ($states as $state) {
            try {
                $places = $googlePlaces->previewTouristAttractions($state, $perState);
            } catch (RuntimeException $exception) {
                $this->error("{$state}: {$exception->getMessage()}");
                $rows[] = [$state, '—', '—', '—'];

                continue;
            }

            $withPhoto = collect($places)->filter(fn (array $place) => filled(data_get($place, 'photos.0.name')));
            $rows[] = [$state, count($places), $withPhoto->count(), count($places) - $withPhoto->count()];

            foreach ($withPhoto->take(3) as $place) {
                $this->line("  • {$state}: ".data_get($place, 'displayName.text', 'Unnamed place'));
            }
        }

        $this->table(['State', 'Google matches', 'With photo', 'Without photo'], $rows);
        $this->newLine();
        $this->comment('Next step: review this coverage before running the future Google-only replacement command.');

        return self::SUCCESS;
    }
}
