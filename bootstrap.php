<?php

declare(strict_types=1);

use NixPHP\CLI\Support\CommandRegistry;
use NixPHP\Database\Core\Database;
use NixPHP\Session\Commands\SessionMigrationCommand;
use NixPHP\Session\Core\Session;
use NixPHP\Session\Storage\DatabaseSessionHandler;
use function NixPHP\app;
use function NixPHP\config;
use function NixPHP\Session\session;

$container = app()->container();

$container->set(Session::class, function () use ($container) {
    $session = new Session();

    $session->configureProxyTrust(
        config('session:trust_proxy_headers', false),
        config('session:trusted_proxies', [])
    );

    $storage = config('session:storage', 'default');

    if (
        $storage === 'database'
        && class_exists(Database::class)
        && $container->has(Database::class)
    ) {
        $database = $container->get(Database::class);
        $connection = $database->getConnection();
        $table = config('session:database_table', 'sessions');

        $session->setSessionHandler(new DatabaseSessionHandler(
            $connection,
            $table
        ));
    }

    return $session;
});

if (
    class_exists(CommandRegistry::class)
    && $container->has(CommandRegistry::class)
    && class_exists(SessionMigrationCommand::class)
) {
    $container->get(CommandRegistry::class)->add(SessionMigrationCommand::class);
}

if (PHP_SAPI !== 'cli') {
    session()->start();
}
