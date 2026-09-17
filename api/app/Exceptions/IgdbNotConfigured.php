<?php

namespace App\Exceptions;

use RuntimeException;

class IgdbNotConfigured extends RuntimeException
{
    public function __construct(string $message = 'IGDB nao configurada: defina IGDB_CLIENT_ID e IGDB_CLIENT_SECRET no .env.')
    {
        parent::__construct($message);
    }
}
