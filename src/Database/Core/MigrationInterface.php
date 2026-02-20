<?php

declare(strict_types=1);

namespace NixPHP\Database\Core;

use PDO;

if (!interface_exists(MigrationInterface::class)) {
    interface MigrationInterface
    {
        public function up(PDO $connection): void;
        public function down(PDO $connection): void;
    }
}
