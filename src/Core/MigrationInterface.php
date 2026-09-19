<?php

declare(strict_types=1);

namespace Naf\Database\Core;

use PDO;

interface MigrationInterface
{
    public function up(PDO $connection): void;

    public function down(PDO $connection): void;

    /**
     * Determine whether the migration should be executed when discovered.
     *
     * @return bool
     * @since 0.1.2
     */
    public function shouldRun(): bool;
}
