<?php

namespace Database\Seeders;

use App\Models\Console;
use Illuminate\Database\Seeder;

class ConsoleSeeder extends Seeder
{
    /**
     * IDs de plataforma da IGDB sao os publicamente conhecidos; confirmar com
     * `php artisan igdb:platforms "nome"` assim que houver credencial.
     */
    private const CONSOLES = [
        [1, 'NES', 'Nintendo', 1983, 18],
        [2, 'Super Nintendo', 'Nintendo', 1990, 19],
        [3, 'PlayStation', 'Sony', 1994, 7],
        [4, 'PlayStation 2', 'Sony', 2000, 8],
        [5, 'Xbox 360', 'Microsoft', 2005, 12],
        [6, 'PlayStation 4', 'Sony', 2013, 48],
        [7, 'Nintendo Switch', 'Nintendo', 2017, 130],
        [8, 'PlayStation 5', 'Sony', 2020, 167],
    ];

    public function run(): void
    {
        foreach (self::CONSOLES as [$sortOrder, $name, $manufacturer, $releaseYear, $igdbPlatformId]) {
            Console::updateOrCreate(
                ['name' => $name],
                [
                    'manufacturer' => $manufacturer,
                    'release_year' => $releaseYear,
                    'sort_order' => $sortOrder,
                    'igdb_platform_id' => $igdbPlatformId,
                ],
            );
        }
    }
}
