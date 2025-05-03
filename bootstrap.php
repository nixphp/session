<?php

use NixPHP\Session\Support\Session;
use function NixPHP\app;

app()->container()->set('session', function() {
    return new Session();
});