<?php

declare(strict_types=1);

namespace Naf\Database\Commands;

use Naf\CLI\Core\AbstractCommand;
use Naf\CLI\Core\Input;
use Naf\CLI\Core\Output;
use function Naf\app;

/**
 * Command that scaffolds application migration files.
 *
 * Since 0.1.2 new migrations extend AbstractMigration and inherit the default
 * `shouldRun()` hook so conditional execution can be customized.
 *
 * @since 0.1.2
 */
class MigrationCreateCommand extends AbstractCommand
{

    public const string NAME = 'db:migration:create';

    protected function configure(): void
    {
        $this
            ->setTitle('Create Migration')
            ->setDescription('Create a skeleton migration in your application.');
    }

    /**
     * @param Input $input
     * @param Output $output
     * @return int
     */
    public function run(Input $input, Output $output): int
    {
        $timestamp = time();
        $template  = $this->buildTemplate($timestamp);

        $directory = app()->getBasePath() . '/app/Migrations';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $migrationsDir = realpath($directory);
        $filename      = sprintf('Migration%s.php', $timestamp);

        file_put_contents(sprintf('%s/%s', $migrationsDir, $filename), $template);

        $output->writeLine('Generated migration file successfully.', 'ok');

        return AbstractCommand::SUCCESS;
    }

    /**
     * @param int $timestamp
     * @return string
     */
    private function buildTemplate(int $timestamp): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use Naf\Database\Core\AbstractMigration;

class Migration{$timestamp} extends AbstractMigration
{

    /**
     * @param PDO \$connection
     */
    public function up(PDO \$connection): void
    {
        
    }

    /**
     * @param PDO \$connection
     */
    public function down(PDO \$connection): void
    {
        
    }
}

PHP;
    }

}