<?php

declare(strict_types=1);

namespace Naf\Database;

use Naf\Database\Core\Database;
use function Naf\app;

function database():? \PDO
{
    return app()->container()->get(Database::class)?->getConnection();
}