<?php

declare(strict_types=1);

namespace NixPHP\Session\Migrations;

use NixPHP\Database\Core\MigrationInterface;
use PDO;

if (!interface_exists(MigrationInterface::class)) {
    return;
}

class SessionTableMigration implements MigrationInterface
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
                `last_activity` INT NOT NULL,
                CONSTRAINT `fk_sessions_user`
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
            );
        SQL
        );

        $connection->exec('CREATE INDEX `idx_sessions_user_id` ON `sessions` (`user_id`)');
        $connection->exec('CREATE INDEX `idx_sessions_last_activity` ON `sessions` (`last_activity`)');
    }

    public function down(PDO $connection): void
    {
        $connection->exec('DROP TABLE IF EXISTS `sessions`');
    }
}
