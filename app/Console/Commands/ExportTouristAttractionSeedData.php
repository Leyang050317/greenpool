<?php

namespace App\Console\Commands;

use App\Models\Attraction;
use App\Models\AttractionDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportTouristAttractionSeedData extends Command
{
    protected $signature = 'attractions:export-seed-data';

    protected $description = 'Export current attraction data as a shareable SQL seed file';

    public function handle(): int
    {
        $path = database_path('seed-data/tourist-attractions.sql');
        File::ensureDirectoryExists(dirname($path));

        $sql = [
            '-- GreenPool Tourist Attraction seed data',
            '-- Import only into a database where tourist_attractions and attraction_details are empty.',
            '-- Run php artisan migrate before importing this file.',
            'SET NAMES utf8mb4;',
            'START TRANSACTION;',
        ];

        $this->appendInserts($sql, 'tourist_attractions', [
            'attraction_id', 'attraction_name', 'state', 'description', 'location', 'image_url', 'created_at', 'updated_at',
        ], Attraction::query()->orderBy('attraction_id')->get()->map->getAttributes()->all());

        $this->appendInserts($sql, 'attraction_details', [
            'detail_id', 'attraction_id', 'category', 'opening_hours', 'entrance_fee', 'contact', 'website', 'source_name',
            'source_place_id', 'google_place_id', 'latitude', 'longitude', 'image_attribution', 'created_at', 'updated_at',
        ], AttractionDetail::query()->orderBy('detail_id')->get()->map->getAttributes()->all());

        $sql[] = 'COMMIT;';
        File::put($path, implode(PHP_EOL, $sql).PHP_EOL);

        $this->info('Exported '.Attraction::count().' attractions and '.AttractionDetail::count().' details to '.$path);

        return self::SUCCESS;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function appendInserts(array &$sql, string $table, array $columns, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $sql[] = '';
        $sql[] = 'INSERT INTO `'.$table.'` (`'.implode('`, `', $columns).'`) VALUES';
        $values = collect($rows)->map(function (array $row) use ($columns) {
            return '('.collect($columns)->map(fn (string $column) => $this->sqlValue($row[$column] ?? null))->implode(', ').')';
        });
        $sql[] = $values->implode(','.PHP_EOL).';';
    }

    private function sqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'".str_replace(["\\", "'"], ["\\\\", "\\'"], (string) $value)."'";
    }
}
