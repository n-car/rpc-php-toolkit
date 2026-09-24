<?php

declare(strict_types=1);

namespace RpcPhpToolkit\Middleware;

/**
 * MySQL-backed rate-limit storage for traditional request-per-process hosting.
 *
 * Use a dedicated PDO connection: an existing application transaction would
 * make rate-limit increments subject to an unrelated commit or rollback.
 */
final class MySqlRateLimitStore implements RateLimitStoreInterface
{
    private \PDO $pdo;
    private string $table;

    public function __construct(\PDO $pdo, string $table = 'rpc_rate_limits')
    {
        if ($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            throw new \InvalidArgumentException('MySqlRateLimitStore requires a MySQL PDO connection');
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new \InvalidArgumentException('Invalid rate-limit table name');
        }

        $this->pdo = $pdo;
        $this->table = $table;
    }

    /**
     * Creates the storage table when the application is installed or migrated.
     */
    public function createTable(): void
    {
        $table = $this->quotedTable();
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS {$table} (" .
            'bucket_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,' .
            'request_count INT UNSIGNED NOT NULL,' .
            'window_start BIGINT UNSIGNED NOT NULL,' .
            'expires_at BIGINT UNSIGNED NOT NULL,' .
            'PRIMARY KEY (bucket_key),' .
            'KEY idx_rpc_rate_limits_expires_at (expires_at)' .
            ') ENGINE=InnoDB'
        );
    }

    public function increment(string $key, int $timeWindow, int $now): array
    {
        if ($this->pdo->inTransaction()) {
            throw new \LogicException('MySqlRateLimitStore requires a dedicated PDO connection');
        }

        $table = $this->quotedTable();
        $cutoff = $now - $timeWindow;
        $expiresAt = $now + ($timeWindow * 2);

        $this->pdo->beginTransaction();

        try {
            // The upsert holds an exclusive row lock until the following read.
            $statement = $this->pdo->prepare(
                "INSERT INTO {$table} " .
                '(bucket_key, request_count, window_start, expires_at) VALUES (?, 1, ?, ?) ' .
                'ON DUPLICATE KEY UPDATE ' .
                'request_count = IF(window_start <= ?, 1, request_count + 1), ' .
                'expires_at = IF(window_start <= ?, ?, expires_at), ' .
                'window_start = IF(window_start <= ?, ?, window_start)'
            );
            $statement->execute([
                $key,
                $now,
                $expiresAt,
                $cutoff,
                $cutoff,
                $expiresAt,
                $cutoff,
                $now
            ]);

            $statement = $this->pdo->prepare(
                "SELECT request_count, window_start FROM {$table} WHERE bucket_key = ?"
            );
            $statement->execute([$key]);
            $bucket = $statement->fetch(\PDO::FETCH_ASSOC);

            if (!is_array($bucket)) {
                throw new \RuntimeException('Unable to read the rate-limit bucket');
            }

            $this->pdo->commit();

            return [
                'requests' => (int) $bucket['request_count'],
                'window_start' => (int) $bucket['window_start']
            ];
        } catch (\Throwable $error) {
            try {
                $this->pdo->rollBack();
            } catch (\PDOException) {
                // Preserve the storage error when the failed commit already ended the transaction.
            }

            throw $error;
        }
    }

    /**
     * Removes expired buckets. Call this periodically from application cleanup.
     */
    public function purgeExpired(?int $now = null): int
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM ' . $this->quotedTable() . ' WHERE expires_at <= ?'
        );
        $statement->execute([$now ?? time()]);

        return $statement->rowCount();
    }

    private function quotedTable(): string
    {
        return '`' . $this->table . '`';
    }
}
