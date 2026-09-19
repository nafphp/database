<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Naf\Database\Core\Database;

class DummyDatabase extends Database
{
    public string $lastDsn     = '';
    public ?string $usedDriver = null;

    public function __construct(array $config)
    {
        $this->lastDsn    = $this->buildDsn($config);
        $this->usedDriver = $config['driver'] ?? 'mysql';
        // skip parent::__construct to avoid real PDO init
    }

    // expose DSN for assertions
    public function getLastDsn(): string
    {
        return $this->lastDsn;
    }
}
