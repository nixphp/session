<div style="text-align: center;">

![Logo](https://nixphp.github.io/docs/assets/nixphp-logo-small-square.png)

[![NixPHP Session Plugin](https://github.com/nixphp/session/actions/workflows/php.yml/badge.svg)](https://github.com/nixphp/session/actions/workflows/php.yml)

</div>

[← Back to NixPHP](https://github.com/nixphp/framework)

---

# nixphp/session

> **Simple session management for NixPHP — with flash message support built-in.**

This plugin adds a lightweight, dependency-free session handler to your NixPHP app.
It starts the session automatically and provides helpers for storing data across requests — including flash messages.

> 🧩 Part of the official NixPHP plugin collection.
> Install it when you need session persistence — and nothing else.

---

## 📦 Features

* ✅ Starts PHP sessions automatically
* ✅ Store/retrieve session data easily
* ✅ Flash message system for one-time notices
* ✅ No configuration needed
* ✅ PSR-11 container integration (`session()`)

---

## 📥 Installation

```bash
composer require nixphp/session
```

Once installed, the plugin is autoloaded and ready to use.

---

## 🚀 Usage

### 📌 Accessing the session

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

### ✨ Flash messages

Use flash messages to store data for the *next* request only (e.g. after a redirect):

```php
session()->flash('success', 'Profile updated.');
```

In the next request, access it using:

```php
<?php if ($message = session()->get('success')): ?>
    <p class="success"><?= $message ?></p>
<?php endif; ?>
```

The message is then **automatically removed** after it has been read.

---

## 🔍 Internals

* Automatically starts `session_start()` if it hasn't run yet.
* Flash data is stored in a dedicated key and removed after access.
* Registers the `session()` helper and binds it in the service container.

---

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