<?php

namespace NixPHP\Session;

use NixPHP\Session\Support\Session;
use function NixPHP\app;

function session(): Session
{
    return app()->container()->get('session');
}