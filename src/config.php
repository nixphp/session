<?php

declare(strict_types=1);

return [
    'sessionStorage' => 'default',
    'session' => [
        'trust_proxy_headers' => false,
        'trusted_proxies' => [],
        'database_table' => 'sessions',
        'migration_path' => __DIR__ . '/Migrations',
    ],
];
