<?php

declare(strict_types=1);

namespace NixPHP\Session\Migrations;

use NixPHP\Database\Core\AbstractMigration;
use PDO;
use PDOException;

class SessionTableMigration extends AbstractMigration
{
    public function up(PDO $connection): void
    {
        $connection->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `sessions`
            (
                `id` VARCHAR(255) PRIMARY KEY,
                `user_id` INT NULL,
                `ip_address` VARCHAR(45) NULL,
                `user_agent` TEXT NULL,
                `payload` TEXT NOT NULL,
                `last_activity` INT NOT NULL
            );
        SQL
        );

        $this->createIndex($connection, 'CREATE INDEX `idx_sessions_user_id` ON `sessions` (`user_id`)');
        $this->createIndex($connection, 'CREATE INDEX `idx_sessions_last_activity` ON `sessions` (`last_activity`)');
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS `sessions`');
    }

    private function createIndex(PDO $connection, string $sql): void
    {
        try {
            $connection->exec($sql);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '42000' || !str_contains($exception->getMessage(), '1061')) {
                throw $exception;
            }
        }
    }
}
