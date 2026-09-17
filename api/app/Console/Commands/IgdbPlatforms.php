<?php

namespace App\Console\Commands;

use App\Exceptions\IgdbNotConfigured;
use App\Exceptions\IgdbRequestFailed;
use App\Services\IgdbClient;
use Illuminate\Console\Command;

class IgdbPlatforms extends Command
{
    protected $signature = 'igdb:platforms {search : Nome (ou parte) da plataforma}';

    protected $description = 'Consulta a IGDB e lista id/name/abbreviation das plataformas que batem com a busca';

    public function handle(IgdbClient $igdb): int
    {
        try {
            $platforms = $igdb->platforms($this->argument('search'));
        } catch (IgdbNotConfigured|IgdbRequestFailed $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($platforms === []) {
            $this->warn('Nenhuma plataforma encontrada.');

            return self::SUCCESS;
        }

        $this->table(['id', 'name', 'abbreviation'], $platforms);

        return self::SUCCESS;
    }
}
