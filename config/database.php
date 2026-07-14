<?php

/**
 * MealKit – Database Class  (PDO Singleton)
 * ==========================================
 * Provides a single, shared PDO connection throughout the request
 * lifecycle. Using the Singleton pattern guarantees only one
 * connection is opened per request, avoiding resource exhaustion.
 *
 * Usage:
 *   $pdo = Database::getInstance()->getConnection();
 *
 * All queries MUST use prepared statements – never interpolate
 * user input directly into SQL strings.
 */

declare(strict_types=1);

// Guard: config must be loaded first (defines DB_* constants)
defined('DB_HOST') or die('Configuration not loaded.');

class Database
{
    // ── Singleton instance ────────────────────────────────────────────────────
    private static ?self $instance = null;

    // ── PDO connection handle ─────────────────────────────────────────────────
    private PDO $connection;

    // ── Query statistics (available in debug mode) ────────────────────────────
    private int   $queryCount   = 0;
    private float $queryTime    = 0.0;

    // ─────────────────────────────────────────────────────────────────────────
    // Constructor – private to enforce Singleton
    // ─────────────────────────────────────────────────────────────────────────

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            // Throw exceptions on errors (never silent failures)
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,

            // Return rows as associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Use real prepared statements (security: prevents emulated injection)
            PDO::ATTR_EMULATE_PREPARES   => false,

            // Force character set at connection level
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, " .
                "time_zone = '+00:00', " .
                "sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE," .
                            "ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",

            // Persistent connections (optional – improves performance under load)
            // PDO::ATTR_PERSISTENT => true,
        ];

        try {
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In debug mode expose the message; in production show a generic error
            if (APP_DEBUG) {
                $msg = 'Database connection failed: ' . $e->getMessage();
            } else {
                $msg = 'A database error occurred. Please contact support.';
                // Log the real error server-side
                error_log('[MealKit DB] Connection failed: ' . $e->getMessage());
            }
            // Fatal – cannot continue without a DB
            http_response_code(503);
            die($msg);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Public API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return (or create) the singleton instance.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Return the raw PDO connection for use in models.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Convenience: prepare and execute a statement, return the PDOStatement.
     * Automatically tracks query count and time in debug mode.
     *
     * @param string $sql    Parameterised SQL query
     * @param array  $params Bound parameter values (positional or named)
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $start = microtime(true);

        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);

        if (APP_DEBUG) {
            $this->queryCount++;
            $this->queryTime += microtime(true) - $start;
        }

        return $stmt;
    }

    /**
     * Fetch a single row.
     * Returns null when no row is found.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();
        return ($result !== false) ? $result : null;
    }

    /**
     * Fetch all matching rows as an array of associative arrays.
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single scalar value (e.g. COUNT, SUM).
     */
    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    /**
     * Execute an INSERT and return the new row's auto-increment ID.
     */
    public function insert(string $table, array $data): int
    {
        $columns      = array_keys($data);
        $colList      = implode(', ', array_map(fn($c) => "`{$c}`", $columns));
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));

        $this->query(
            "INSERT INTO `{$table}` ({$colList}) VALUES ({$placeholders})",
            array_values($data)
        );

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Execute an UPDATE and return the number of affected rows.
     *
     * @param string $table
     * @param array  $data       Columns to update (column => value)
     * @param string $where      WHERE clause (e.g. "id = ?")
     * @param array  $whereParams Values for the WHERE clause placeholders
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(fn($col) => "`{$col}` = ?", array_keys($data));
        $setClause = implode(', ', $setParts);

        $stmt = $this->query(
            "UPDATE `{$table}` SET {$setClause} WHERE {$where}",
            array_merge(array_values($data), $whereParams)
        );

        return $stmt->rowCount();
    }

    /**
     * Execute a DELETE and return the number of affected rows.
     *
     * @param string $table
     * @param string $where       WHERE clause (e.g. "id = ?")
     * @param array  $whereParams Values for the WHERE placeholders
     */
    public function delete(string $table, string $where, array $whereParams = []): int
    {
        $stmt = $this->query(
            "DELETE FROM `{$table}` WHERE {$where}",
            $whereParams
        );
        return $stmt->rowCount();
    }

    // ── Transaction helpers ───────────────────────────────────────────────────

    public function beginTransaction(): bool
    {
        return $this->connection->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->connection->commit();
    }

    public function rollback(): bool
    {
        return $this->connection->rollBack();
    }

    /**
     * Execute a callable within a transaction.
     * Automatically commits on success, rolls back on any exception.
     *
     * @param callable $callback  Receives the Database instance as its argument
     * @return mixed              Return value of the callback
     * @throws Throwable          Re-throws any exception after rollback
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    // ── Debug helpers ─────────────────────────────────────────────────────────

    /**
     * Return query stats (only meaningful when APP_DEBUG = true).
     */
    public function getStats(): array
    {
        return [
            'query_count' => $this->queryCount,
            'query_time'  => round($this->queryTime * 1000, 2) . ' ms',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Prevent cloning and unserialization of the singleton
    // ─────────────────────────────────────────────────────────────────────────

    private function __clone(): void {}

    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize the Database singleton.');
    }
}
