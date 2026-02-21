<?php

declare(strict_types=1);

namespace NixPHP\Session\Core;

use SessionHandlerInterface;

class Session
{
    protected bool $started = false;
    private bool $trustProxyHeaders = false;
    private array $trustedProxies = [];
    private ?SessionHandlerInterface $sessionHandler = null;
    private ?int $lastRegeneratedAt = null;

    public function start(?callable $sessionHandler = null): void
    {
        if (null === $sessionHandler) {
            $sessionHandler = function () {
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    if ($this->sessionHandler !== null) {
                        session_set_save_handler($this->sessionHandler, true);
                    }
                    session_set_cookie_params($this->getDefaultCookieParams());
                    session_start();
                }
            };
        }

        $sessionHandler();

        $this->started = true;
    }

    /**
     * Configure trusted proxies when relying on forwarded headers.
     *
     * Populate your config (e.g. session.trust_proxy_headers + session.trusted_proxies)
     * before calling start() so that HTTP_X_FORWARDED_PROTO is only trusted when it
     * originates from a known proxy, protecting against header spoofing.
     */
    public function configureProxyTrust(bool $trustProxyHeaders, array $trustedProxies = []): void
    {
        $this->trustProxyHeaders = $trustProxyHeaders;
        $this->trustedProxies = $trustedProxies;
    }

    public function setSessionHandler(SessionHandlerInterface $handler): void
    {
        $this->sessionHandler = $handler;
    }

    public function clear(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        session_unset();
        session_destroy();

        $cookieParams = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires'  => time() - 42000,
                'path'     => $cookieParams['path'] ?? '/',
                'domain'   => $cookieParams['domain'] ?? '',
                'secure'   => $cookieParams['secure'] ?? false,
                'httponly' => $cookieParams['httponly'] ?? false,
                'samesite' => $cookieParams['samesite'] ?? 'Lax',
            ]
        );

        unset($_COOKIE[session_name()]);
    }

    private function getDefaultCookieParams(): array
    {
        $secureCookie = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || $this->isForwardedProtoTrusted();

        $rawHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $domain = $rawHost === '' ? '' : preg_replace('/:\\d+$/', '', trim($rawHost));

        return [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $domain,
            'secure'   => $secureCookie,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private function isForwardedProtoTrusted(): bool
    {
        if (!$this->trustProxyHeaders || !isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            return false;
        }

        if (empty($this->trustedProxies)) {
            trigger_error('Proxy headers trusted but no trusted proxies configured', E_USER_WARNING);
            return false;
        }

        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        if (null === $remoteAddr) {
            return false;
        }

        return in_array($remoteAddr, $this->trustedProxies, true)
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function regenerate(int $intervalSeconds = 300): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $last = $_SESSION['_id_regenerated_at'] ?? $this->lastRegeneratedAt;

        if ($last !== null && time() - $last < $intervalSeconds) {
            return;
        }

        session_regenerate_id(true);
        $_SESSION['_id_regenerated_at'] = time();
        $this->lastRegeneratedAt = $_SESSION['_id_regenerated_at'];
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
