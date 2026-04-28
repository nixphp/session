<?php

declare(strict_types=1);

namespace NixPHP\Session\Storage;

use PDO;
use PDOException;
use SessionHandlerInterface;
use function NixPHP\log;

/**
 * Session handler backed by a sessions table.
 *
 * Requires MySQL 8.0.19+ when using the default driver because the upsert
 * relies on the `AS new` syntax that older versions do not support.
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $connection;
    private string $table;
    private string $driver;
    private array $columns;
    /** @var callable|null */
    private $contextProvider = null;

    public function __construct(PDO $connection, string $table, array $columns = [], ?callable $contextProvider = null)
    {
        $this->connection = $connection;
        $this->driver     = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $this->table      = $table;
        $this->columns    = array_merge([
            'id'            => 'id',
            'payload'       => 'payload',
            'last_activity' => 'last_activity',
            'ip'            => 'ip_address',
            'user_agent'    => 'user_agent',
            'user_id'       => 'user_id',
        ], $columns);
        $this->contextProvider = $contextProvider;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        try {
            $stmt = $this->connection->prepare(sprintf(
                'SELECT %s, %s FROM %s WHERE %s = :id',
                $this->quoteIdentifier($this->columns['payload']),
                $this->quoteIdentifier($this->columns['last_activity']),
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->columns['id'])
            ));

            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (false === $result) {
                return '';
            }

            $lastActivity = (int)($result[$this->columns['last_activity']] ?? 0);
            if ($this->isExpired($lastActivity)) {
                $this->destroyIfLastActivityMatches($id, $lastActivity);
                return '';
            }

            return (string)($result[$this->columns['payload']] ?? '');
        } catch (PDOException $e) {
            \NixPHP\log()->error($e->getMessage());
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        $now     = time();
        $context = $this->resolveContext();
        $ip      = $context['ip'] ?? null;
        $agent   = $context['user_agent'] ?? null;
        $userId  = $context['user_id'] ?? null;

        $bindings = [
            'id'            => $id,
            'payload'       => $data,
            'last_activity' => $now,
            'ip'            => $ip,
            'user_agent'    => $agent,
            'user_id'       => $userId,
        ];

        try {
            $sql  = $this->buildUpsert();
            $stmt = $this->connection->prepare($sql);

            return $stmt->execute($bindings);
        } catch (PDOException $e) {
            $this->log($e);
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->connection->prepare(sprintf(
                'DELETE FROM %s WHERE %s = :id',
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->columns['id'])
            ));

            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            $this->log($e);
            return false;
        }
    }

    private function destroyIfLastActivityMatches(string $id, int $lastActivity): bool
    {
        try {
            $stmt = $this->connection->prepare(sprintf(
                'DELETE FROM %s WHERE %s = :id AND %s = :last_activity',
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->columns['id']),
                $this->quoteIdentifier($this->columns['last_activity'])
            ));

            return $stmt->execute([
                'id' => $id,
                'last_activity' => $lastActivity,
            ]);
        } catch (PDOException $e) {
            $this->log($e);
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        $threshold = time() - $max_lifetime;

        try {
            $stmt = $this->connection->prepare(sprintf(
                'DELETE FROM %s WHERE %s < :threshold',
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->columns['last_activity'])
            ));

            $stmt->execute(['threshold' => $threshold]);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            $this->log($e);
            return false;
        }
    }

    private function buildUpsert(): string
    {
        $fields = [
            $this->quoteIdentifier($this->columns['id']),
            $this->quoteIdentifier($this->columns['payload']),
            $this->quoteIdentifier($this->columns['last_activity']),
            $this->quoteIdentifier($this->columns['ip']),
            $this->quoteIdentifier($this->columns['user_agent']),
            $this->quoteIdentifier($this->columns['user_id']),
        ];

        if (in_array($this->driver, ['sqlite', 'pgsql', 'postgres', 'postgresql'], true)) {
            return sprintf(
                'INSERT INTO %s (%s) VALUES (:id, :payload, :last_activity, :ip, :user_agent, :user_id) ON CONFLICT(%s) DO UPDATE SET %s = excluded.%s, %s = excluded.%s, %s = excluded.%s, %s = excluded.%s, %s = excluded.%s',
                $this->quoteIdentifier($this->table),
                implode(', ', $fields),
                $this->quoteIdentifier($this->columns['id']),
                $this->quoteIdentifier($this->columns['payload']),
                $this->quoteIdentifier($this->columns['payload']),
                $this->quoteIdentifier($this->columns['last_activity']),
                $this->quoteIdentifier($this->columns['last_activity']),
                $this->quoteIdentifier($this->columns['ip']),
                $this->quoteIdentifier($this->columns['ip']),
                $this->quoteIdentifier($this->columns['user_agent']),
                $this->quoteIdentifier($this->columns['user_agent']),
                $this->quoteIdentifier($this->columns['user_id']),
                $this->quoteIdentifier($this->columns['user_id'])
            );
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES (:id, :payload, :last_activity, :ip, :user_agent, :user_id) AS new ON DUPLICATE KEY UPDATE %s = new.%s, %s = new.%s, %s = new.%s, %s = new.%s, %s = new.%s',
            $this->quoteIdentifier($this->table),
            implode(', ', $fields),
            $this->quoteIdentifier($this->columns['payload']),
            $this->quoteIdentifier($this->columns['payload']),
            $this->quoteIdentifier($this->columns['last_activity']),
            $this->quoteIdentifier($this->columns['last_activity']),
            $this->quoteIdentifier($this->columns['ip']),
            $this->quoteIdentifier($this->columns['ip']),
            $this->quoteIdentifier($this->columns['user_agent']),
            $this->quoteIdentifier($this->columns['user_agent']),
            $this->quoteIdentifier($this->columns['user_id']),
            $this->quoteIdentifier($this->columns['user_id'])
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        $driver = $this->driver;
        return match ($driver) {
            'pgsql',
            'postgres',
            'postgresql' => sprintf('"%s"', str_replace('"', '""', $identifier)),
            default => sprintf('`%s`', str_replace('`', '``', $identifier)),
        };
    }

    private function resolveContext(): array
    {
        $fallback = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'user_id' => $_SESSION['user_id'] ?? $_SESSION['userId'] ?? null,
        ];

        if (null === $this->contextProvider) {
            return $fallback;
        }

        $context = (array) call_user_func($this->contextProvider);

        return [
            'ip' => $context['ip'] ?? $fallback['ip'],
            'user_agent' => $context['user_agent'] ?? $fallback['user_agent'],
            'user_id' => $context['user_id'] ?? $fallback['user_id'],
        ];
    }

    private function isExpired(int $lastActivity): bool
    {
        if ($lastActivity <= 0) {
            return true;
        }

        $maxLifetime = (int)ini_get('session.gc_maxlifetime');
        if ($maxLifetime <= 0) {
            $maxLifetime = 1440;
        }

        return $lastActivity < (time() - $maxLifetime);
    }

    private function log(PDOException $exception): void
    {
        log()->error('Session database error: ' . $exception->getMessage(), ['exception' => $exception]);
    }
}
