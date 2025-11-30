<?php

declare(strict_types=1);

namespace NixPHP\Session\Core;

class Session
{
    protected bool $started = false;

    public function start(?callable $sessionHandler = null): void
    {
        if (null === $sessionHandler) {
            $sessionHandler = function () {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_set_cookie_params([
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                    session_start();
                }
            };
        }

        $sessionHandler();

        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function flash(string $key, mixed $value): void
    {
        $this->set('__flash__.' . $key, $value);
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $this->get('__flash__.' . $key, $default);
        $this->forget('__flash__.' . $key);
        return $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }
}
