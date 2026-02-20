<?php

declare(strict_types=1);

namespace NixPHP\Session\Commands;

use PDO;
use NixPHP\Session\Migrations\SessionTableMigration;
use NixPHP\CLI\Core\AbstractCommand;
use NixPHP\CLI\Core\Input;
use NixPHP\CLI\Core\Output;

if (!class_exists(AbstractCommand::class)) {
    return;
}

class SessionMigrationCommand extends AbstractCommand
{
    public const NAME = 'session:migrate';

    protected function configure(): void
    {
        $this
            ->setTitle('Run session schema migrations')
            ->setDescription('Create or drop the session table when the database plugin is available.')
            ->addArgument('direction', true);
    }

    public function run(Input $input, Output $output): int
    {
        $direction = $input->getArgument('direction') ?? 'up';

        if (!in_array($direction, ['up', 'down'], true)) {
            $output->writeLine('Direction must be either up or down.', 'error');
            return self::ERROR;
        }

        if (!function_exists('\NixPHP\Database\database')) {
            $output->writeLine('Database plugin is not installed.', 'error');
            return self::ERROR;
        }

        $connection = \NixPHP\Database\database();

        if (!$connection instanceof PDO) {
            $output->writeLine('Database connection not available.', 'error');
            return self::ERROR;
        }

        $migration = new SessionTableMigration();
        $migration->$direction($connection);

        $output->writeLine(sprintf('✔ session table %s successfully.', $direction), 'ok');

        return self::SUCCESS;
    }
}
