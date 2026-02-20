<?php

declare(strict_types=1);

namespace NixPHP\Session\Storage;

use PDO;
use SessionHandlerInterface;

class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $connection;
    private string $table;
    private string $driver;
    private array $columns;

    public function __construct(PDO $connection, string $table, array $columns = [])
    {
        $this->connection = $connection;
        $this->driver = strtolower((string) $connection->getAttribute(PDO::ATTR_DRIVER_NAME));
        $this->table = $table;
        $this->columns = array_merge([
            'id' => 'id',
            'payload' => 'payload',
            'last_activity' => 'last_activity',
            'ip' => 'ip_address',
            'user_agent' => 'user_agent',
            'user_id' => 'user_id',
        ], $columns);
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
    }

    public function write(string $sessionId, string $data): bool
    {
        $now = time();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $userId = $_SESSION['user_id'] ?? null;

        $bindings = [
            'id' => $sessionId,
            'payload' => $data,
            'last_activity' => $now,
            'ip' => $ip,
            'user_agent' => $agent,
            'user_id' => $userId,
        ];

        $sql = $this->buildUpsert();
        $stmt = $this->connection->prepare($sql);

        return $stmt->execute($bindings);
    }

    public function destroy(string $sessionId): bool
    {
        $stmt = $this->connection->prepare(sprintf(
            'DELETE FROM %s WHERE %s = :id',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->columns['id'])
        ));

        return $stmt->execute(['id' => $sessionId]);
    }

    public function gc(int $maxLifetime): int|false
    {
        $threshold = time() - $maxLifetime;
        $stmt = $this->connection->prepare(sprintf(
            'DELETE FROM %s WHERE %s < :threshold',
            $this->quoteIdentifier($this->table),
            $this->quoteIdentifier($this->columns['last_activity'])
        ));

        $stmt->execute(['threshold' => $threshold]);
        return $stmt->rowCount();
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
}
