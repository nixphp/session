<?php

declare(strict_types=1);

namespace NixPHP\Session;

use NixPHP\Session\Core\Session;
use function NixPHP\app;

function session(): Session
{
    return app()->container()->get(Session::class);
}