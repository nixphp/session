<?php

declare(strict_types=1);

use NixPHP\Session\Core\Session;
use function NixPHP\app;

app()->container()->set(Session::class, function() {
    return new Session();
});