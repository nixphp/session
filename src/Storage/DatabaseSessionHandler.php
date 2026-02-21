<?php

declare(strict_types=1);

namespace NixPHP\Session\Storage;

use PDO;
use PDOException;
use SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $connection;
    private string $table;
    private string $driver;
    private array $columns;
    /** @var callable|null */
    private $contextProvider = null;

    public function __construct(PDO $connection, string $table, array $columns = [], callable $contextProvider = null)
    {
        $this->connection = $connection;
        $this->driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $this->table = $table;
        $this->columns = array_merge([
            'id'            => 'id',
            'payload'       => 'payload',
            'last_activity' => 'last_activity',
            'ip'            => 'ip_address',
            'user_agent'    => 'user_agent',
            'user_id'       => 'user_id',
        ], $columns);
        $this->contextProvider = $contextProvider;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string
    {
        try {
            $stmt = $this->connection->prepare(sprintf(
                'SELECT %s FROM %s WHERE %s = :id',
                $this->quoteIdentifier($this->columns['payload']),
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->columns['id'])
            ));

            $stmt->execute(['id' => $sessionId]);
            $result = $stmt->fetchColumn();

            if (false === $result) {
                return '';
            }

            return (string) $result;
        } catch (PDOException $e) {
            $this->log($e);
            return '';
        }
    }

    public function write(string $sessionId, string $data): bool
    {
        $now = time();
        $context = $this->resolveContext();
        $ip = $context['ip'] ?? null;
        $agent = $context['user_agent'] ?? null;
        $userId = $context['user_id'] ?? null;

        $bindings = [
            'id' => $sessionId,
            'payload' => $data,
            'last_activity' => $now,
            'ip' => $ip,
            'user_agent' => $agent,
            'user_id' => $userId,
        ];

        try {
            $sql = $this->buildUpsert();
            $stmt = $this->connection->prepare($sql);

            return $stmt->execute($bindings);
        } catch (PDOException $e) {
            $this->log($e);
            return false;
        }
    }

    public function destroy(string $sessionId): bool
    {
        try {
            $stmt = $this->connection->prepare(sprintf(
                'DELETE FROM %s WHERE %s = :id',
                $this->quoteIdentifier($this->table),
                $this->quoteIdentifier($this->columns['id'])
            ));

            return $stmt->execute(['id' => $sessionId]);
        } catch (PDOException $e) {
            $this->log($e);
            return false;
        }
    }

    public function gc(int $maxLifetime): int|false
    {
        $threshold = time() - $maxLifetime;
        $stmt = $this->connection->prepare(sprintf(
            'DELETE FROM %s WHERE %s < :threshold',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->columns['last_activity'])
        ));

        try {
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

        if ($this->driver === 'sqlite') {
            return sprintf(
                'INSERT INTO %s (%s) VALUES (:id, :payload, :last_activity, :ip, :user_agent, :user_id) ON CONFLICT(%s) DO UPDATE SET %s = :payload, %s = :last_activity, %s = :ip, %s = :user_agent, %s = :user_id',
                $this->quoteIdentifier($this->table),
                implode(', ', $fields),
                $this->quoteIdentifier($this->columns['id']),
                $this->quoteIdentifier($this->columns['payload']),
                $this->quoteIdentifier($this->columns['last_activity']),
                $this->quoteIdentifier($this->columns['ip']),
                $this->quoteIdentifier($this->columns['user_agent']),
                $this->quoteIdentifier($this->columns['user_id'])
            );
        }

        return sprintf(
            'INSERT INTO %s (%s) VALUES (:id, :payload, :last_activity, :ip, :user_agent, :user_id) ON DUPLICATE KEY UPDATE %s = VALUES(%s), %s = VALUES(%s), %s = VALUES(%s), %s = VALUES(%s), %s = VALUES(%s)',
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
        return sprintf('`%s`', str_replace('`', '``', $identifier));
    }

    private function resolveContext(): array
    {
        $fallback = [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'user_id' => $_SESSION['user_id'] ?? null,
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

    private function log(PDOException $exception): void
    {
        error_log('Session database error: ' . $exception->getMessage());
    }
}
