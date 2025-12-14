<?php

declare(strict_types=1);

use NixPHP\Session\Core\Session;
use function NixPHP\app;
use function NixPHP\Session\session;

app()->container()->set(Session::class, fn () => new Session());

session()->start();