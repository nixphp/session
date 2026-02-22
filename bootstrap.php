<?php

declare(strict_types=1);

use NixPHP\Database\Core\Database;
use NixPHP\Database\Support\MigrationRegistry;
use NixPHP\Session\Core\Session;
use NixPHP\Session\Storage\DatabaseSessionHandler;
use function NixPHP\app;
use function NixPHP\config;
use function NixPHP\Session\session;
use function NixPHP\log;

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
    ) {
        if (!app()->hasPlugin('nixphp/database')) {
            log()->warning('You\'ve configured to use the database as the session storage but the plugin nixphp/database is missing.');
            return $session;
        }
        
        $database   = $container->get(Database::class);
        $connection = $database->getConnection();
        $table      = config('session:database_table', 'sessions');

        $session->setSessionHandler(new DatabaseSessionHandler(
            $connection,
            $table
        ));
    }

    return $session;
});

if (app()->hasPlugin('nixphp/database')) {
    MigrationRegistry::addPath(__DIR__ . '/src/Migrations');
}

if (PHP_SAPI !== 'cli') {
    session()->start();
}
