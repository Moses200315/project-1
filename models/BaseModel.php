<?php

/**
 * BaseModel – Abstract Data Access Layer
 * ========================================
 * Every model extends this class to inherit:
 *   - Shared PDO connection via Database singleton
 *   - Generic CRUD helpers (findById, create, update, delete)
 *   - WHERE clause builder for simple condition arrays
 *   - Pagination wrapper
 *   - Duplicate-check helper
 *
 * Child classes must define:
 *   protected string $table;          // DB table name
 *   protected string $primaryKey = 'id';
 */

declare(strict_types=1);

abstract class BaseModel
{
    /** Shared Database singleton */
    protected Database $db;

    /** Raw PDO connection (for complex, hand-written queries) */
    protected PDO $pdo;

    /** The DB table this model manages */
    protected string $table;

    /** Primary key column name */
    protected string $primaryKey = 'id';

    // ─────────────────────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->db  = Database::getInstance();
        $this->pdo = $this->db->getConnection();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GENERIC FINDERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Find a single row by primary key.
     * Returns null when the record does not exist.
     */
    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1",
            [$id]
        );
    }

    /**
     * Find the first row matching a specific column value.
     *
     * @param string $column  Column name
     * @param mixed  $value   Value to match
     */
    public function findBy(string $column, mixed $value): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `{$this->table}` WHERE `{$column}` = ? LIMIT 1",
            [$value]
        );
    }

    /**
     * Return all rows matching an optional set of conditions.
     *
     * @param array  $conditions  ['column' => value, …]
     * @param string $orderBy     e.g. 'created_at DESC'
     * @param int    $limit       0 = no limit
     * @param int    $offset
     */
    public function findAll(
        array  $conditions = [],
        string $orderBy    = '',
        int    $limit      = 0,
        int    $offset     = 0
    ): array {
        [$whereSQL, $params] = $this->buildWhereClause($conditions);

        $sql = "SELECT * FROM `{$this->table}`" . $whereSQL;

        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
            if ($offset > 0) {
                $sql .= " OFFSET {$offset}";
            }
        }

        return $this->db->fetchAll($sql, $params);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GENERIC WRITE OPERATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Insert a new row and return the new auto-increment ID.
     *
     * @param array $data  Column => value pairs
     */
    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    /**
     * Update the row with the given primary key.
     * Returns true if at least one row was affected.
     *
     * @param int   $id   Primary key value
     * @param array $data Column => value pairs to update
     */
    public function update(int $id, array $data): bool
    {
        $affected = $this->db->update(
            $this->table,
            $data,
            "`{$this->primaryKey}` = ?",
            [$id]
        );
        return $affected > 0;
    }

    /**
     * Delete the row with the given primary key.
     * Returns true if the row existed and was deleted.
     */
    public function delete(int $id): bool
    {
        $affected = $this->db->delete(
            $this->table,
            "`{$this->primaryKey}` = ?",
            [$id]
        );
        return $affected > 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // AGGREGATE HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Count rows matching optional conditions.
     *
     * @param array $conditions ['column' => value, …]
     */
    public function count(array $conditions = []): int
    {
        [$whereSQL, $params] = $this->buildWhereClause($conditions);
        $result = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->table}`" . $whereSQL,
            $params
        );
        return (int) $result;
    }

    /**
     * Check whether a value already exists in a column.
     * Useful for unique constraint validation before insert/update.
     *
     * @param string $column     Column to check
     * @param mixed  $value      Value to look for
     * @param int    $excludeId  Exclude this ID (for update operations)
     */
    public function exists(string $column, mixed $value, int $excludeId = 0): bool
    {
        $sql    = "SELECT COUNT(*) FROM `{$this->table}` WHERE `{$column}` = ?";
        $params = [$value];

        if ($excludeId > 0) {
            $sql      .= " AND `{$this->primaryKey}` != ?";
            $params[]  = $excludeId;
        }

        return (int) $this->db->fetchColumn($sql, $params) > 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PAGINATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return a paginated result set plus pagination metadata.
     *
     * @param int    $page        Current page (1-based)
     * @param int    $perPage     Rows per page
     * @param array  $conditions  ['column' => value, …]
     * @param string $orderBy     ORDER BY clause (without keyword)
     * @param string $baseUrl     Base URL for pagination links
     * @return array {rows: array, pager: array}
     */
    public function paginate(
        int    $page       = 1,
        int    $perPage    = ITEMS_PER_PAGE,
        array  $conditions = [],
        string $orderBy    = 'created_at DESC',
        string $baseUrl    = ''
    ): array {
        $total  = $this->count($conditions);
        $pager  = paginate($total, $perPage, $page, $baseUrl);
        $rows   = $this->findAll($conditions, $orderBy, $perPage, $pager['offset']);

        return ['rows' => $rows, 'pager' => $pager];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Build a parameterised WHERE clause from a simple conditions array.
     * All conditions are joined with AND and use equality (=).
     *
     * @param  array $conditions  ['column' => value, …]
     * @return array              [' WHERE col=? AND …', [values…]]
     */
    protected function buildWhereClause(array $conditions): array
    {
        if (empty($conditions)) {
            return ['', []];
        }

        $parts  = [];
        $params = [];
        foreach ($conditions as $column => $value) {
            if ($value === null) {
                $parts[] = "`{$column}` IS NULL";
            } else {
                $parts[]  = "`{$column}` = ?";
                $params[] = $value;
            }
        }

        return [' WHERE ' . implode(' AND ', $parts), $params];
    }

    /**
     * Helper to get current UTC datetime string for created_at/updated_at.
     */
    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
