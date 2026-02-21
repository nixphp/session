<div align="center" style="text-align: center;">

![Logo](https://nixphp.github.io/docs/assets/nixphp-logo-small-square.png)

[![NixPHP Session Plugin](https://github.com/nixphp/session/actions/workflows/php.yml/badge.svg)](https://github.com/nixphp/session/actions/workflows/php.yml)

</div>

[← Back to NixPHP](https://github.com/nixphp/framework)

---

# nixphp/session

> **Simple session management for NixPHP, with flash message support built-in.**

This plugin adds a lightweight session layer to your NixPHP app, starts sessions safely in HTTP requests, and exposes helpers so you can store data (including flash messages) without worrying about headers or manual initialization.

> 🧩 Part of the official NixPHP plugin collection.
> Install it when you need session persistence, and nothing else.

---

## 📦 Features

* Starts PHP sessions automatically (skipping CLI)
* Safeguards cookie params (secure/HttpOnly/SameSite) and regenerates IDs on demand
* Flash message helpers (`flash`, `getFlash`)
* `session()` helper bound in the container
* Optional database-backed storage when `nixphp/database` is installed
* `session:migrate` CLI command to bootstrap the session table via the CLI plugin

---

## 📥 Installation

```bash
composer require nixphp/session
```

Once installed, the plugin is autoloaded and ready to use. If you install `nixphp/database` too, it can store sessions in your database table instead of native PHP files.

---

## Usage

### Accessing the session

Use the global `session()` helper to access the session storage:

```php
session()->set('user_id', 42);

$userId = session()->get('user_id');
```

To remove a key:

```php
session()->forget('user_id');
```

---

### Flash messages

Use flash messages to store data for the *next* request only (e.g. after a redirect):

```php
session()->flash('success', 'Profile updated.');
```

In the next request, access it using:

```php
<?php if ($message = session()->getFlash('success')): ?>
    <p class="success"><?= $message ?></p>
<?php endif; ?>
```

The message is then **automatically removed** after it has been read.

---

## 🔍 Internals

* Automatically starts `session_start()` for web requests, with hardened cookie parameters and domain normalization.
* Offers `Session::regenerate()` so you can refresh the session ID during login flows without touching every request.
* Flash data is stored in a dedicated key and removed after access.
* Registers the `session()` helper and binds it in the service container.
* Provides `DatabaseSessionHandler` when the database plugin is configured.
* Includes a `session:migrate` CLI command (registered when `nixphp/cli` is installed) to build or drop the sessions table.

---

## Configuration

`src/config.php` exposes the following keys:

```php
return [
    'session' => [
        'storage'             => 'default', // switch to 'database' when using nixphp/database
        'trust_proxy_headers' => false,
        'trusted_proxies'     => [],
        'database_table'      => 'sessions',
    ],
];
```

To use the database handler:

1. Install [nixphp/database](https://github.com/nixphp/database) and configure its `database` settings.
2. Update the `session` config’s `storage` key to `database`.

## 🛠 Optional Usage in Controllers

You can also access the session directly from the container:

```php
$session = app()->container()->get(Session::class);
```

But using the `session()` helper is the recommended way.

---


## ✅ Requirements

* `nixphp/framework` >= 0.1.0

---

## 📄 License

MIT License.