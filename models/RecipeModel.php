<?php

/**
 * RecipeModel – Core Recipe Operations
 * ======================================
 * The most complex model in the application:
 *   - Full recipe CRUD with joined category data
 *   - Ingredient + procedure sub-record management
 *   - Fulltext search
 *   - Premium / subscription access gating
 *   - Featured recipe queries
 *   - View counter
 *   - Admin analytics
 */

declare(strict_types=1);

class RecipeModel extends BaseModel
{
    protected string $table      = 'recipes';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // FULL DETAIL FETCH (recipe + category + ingredients + procedures)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Fetch a recipe with all related data in one call.
     * Returns null when not found.
     *
     * Result shape:
     *  ['id', 'title', …, 'category_name', 'admin_name',
     *   'ingredients' => [ {id, name, quantity, unit, sort_order} ],
     *   'procedures'  => [ {id, step_number, instruction, tip, image} ] ]
     */
    public function getWithDetails(int $id): ?array
    {
        // Core recipe + category + admin name
        $recipe = $this->db->fetchOne(
            "SELECT r.*,
                    c.name        AS category_name,
                    c.slug        AS category_slug,
                    CONCAT(a.first_name,' ',a.last_name) AS admin_name
             FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             JOIN `admins`     a ON a.id = r.admin_id
             WHERE r.id = ?
             LIMIT 1",
            [$id]
        );

        if ($recipe === null) {
            return null;
        }

        // Attach ingredients
        $recipe['ingredients'] = $this->db->fetchAll(
            "SELECT * FROM `ingredients` WHERE `recipe_id` = ? ORDER BY `sort_order` ASC",
            [$id]
        );

        // Attach procedures
        $recipe['procedures'] = $this->db->fetchAll(
            "SELECT * FROM `procedures` WHERE `recipe_id` = ? ORDER BY `step_number` ASC",
            [$id]
        );

        return $recipe;
    }

    /**
     * Fetch a recipe by its URL slug (with full details).
     */
    public function getBySlugWithDetails(string $slug): ?array
    {
        $recipe = $this->db->fetchOne(
            "SELECT r.* FROM `recipes` r WHERE r.`slug` = ? LIMIT 1",
            [$slug]
        );

        return $recipe ? $this->getWithDetails((int) $recipe['id']) : null;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CUSTOMER-FACING QUERIES
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return a paginated list of published recipes for customers.
     * Supports filtering by category, difficulty, and premium status.
     *
     * @param int    $page
     * @param int    $perPage
     * @param array  $filters  {category_id?, difficulty?, is_premium?, search?}
     * @param string $orderBy  'newest' | 'popular' | 'title'
     */
    public function getPublishedPaginated(
        int    $page    = 1,
        int    $perPage = ITEMS_PER_PAGE,
        array  $filters = [],
        string $orderBy = 'newest'
    ): array {
        [$whereSQL, $params] = $this->buildPublicFilters($filters);

        $orderClause = match ($orderBy) {
            'popular' => 'r.views DESC, r.created_at DESC',
            'title'   => 'r.title ASC',
            default   => 'r.created_at DESC',
        };

        // Total
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             WHERE r.status = 'published' {$whereSQL}",
            $params
        );

        $pager  = paginate($total, $perPage, $page, url('recipes'));
        $offset = $pager['offset'];

        $rows = $this->db->fetchAll(
            "SELECT r.*, c.name AS category_name, c.slug AS category_slug
             FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             WHERE r.status = 'published' {$whereSQL}
             ORDER BY {$orderClause}
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    /**
     * Return the most-viewed published recipes.
     */
    public function getPopular(int $limit = 8): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, c.name AS category_name
             FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             WHERE r.status = 'published'
             ORDER BY r.views DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Return recently added published recipes.
     */
    public function getRecent(int $limit = 6): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, c.name AS category_name
             FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             WHERE r.status = 'published'
             ORDER BY r.created_at DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Search published recipes using MySQL FULLTEXT index.
     *
     * @param string $query
     * @param int    $page
     * @param int    $perPage
     */
    public function search(string $query, int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $safeQuery = trim($query);
        if ($safeQuery === '') {
            return ['rows' => [], 'pager' => paginate(0, $perPage, $page)];
        }

        // Use LIKE fallback for short queries (FULLTEXT needs > 3 chars by default)
        if (mb_strlen($safeQuery) < 4) {
            $like   = '%' . $safeQuery . '%';
            $whereExtra = "AND (r.title LIKE ? OR r.description LIKE ?)";
            $params     = [$like, $like];
            $orderClause = 'r.created_at DESC';
        } else {
            $whereExtra  = "AND MATCH(r.title, r.description) AGAINST(? IN BOOLEAN MODE)";
            $params      = [$safeQuery . '*'];
            $orderClause = "MATCH(r.title, r.description) AGAINST(? IN BOOLEAN MODE) DESC";
        }

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipes` r
             WHERE r.status = 'published' {$whereExtra}",
            $params
        );

        $pager  = paginate($total, $perPage, $page, url('recipes/search') . '?q=' . urlencode($query));
        $offset = $pager['offset'];

        if (mb_strlen($safeQuery) < 4) {
            $rows = $this->db->fetchAll(
                "SELECT r.*, c.name AS category_name
                 FROM `recipes` r
                 JOIN `categories` c ON c.id = r.category_id
                 WHERE r.status = 'published' {$whereExtra}
                 ORDER BY {$orderClause}
                 LIMIT ? OFFSET ?",
                array_merge($params, [$perPage, $offset])
            );
        } else {
            $rows = $this->db->fetchAll(
                "SELECT r.*, c.name AS category_name
                 FROM `recipes` r
                 JOIN `categories` c ON c.id = r.category_id
                 WHERE r.status = 'published' {$whereExtra}
                 ORDER BY {$orderClause}
                 LIMIT ? OFFSET ?",
                array_merge($params, [$safeQuery . '*'], [$perPage, $offset])
            );
        }

        return ['rows' => $rows, 'pager' => $pager];
    }

    /**
     * Return recipes related to the same category (excluding current recipe).
     */
    public function getRelated(int $recipeId, int $categoryId, int $limit = 4): array
    {
        return $this->db->fetchAll(
            "SELECT r.*, c.name AS category_name
             FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             WHERE r.status = 'published'
               AND r.category_id = ?
               AND r.id != ?
             ORDER BY r.views DESC
             LIMIT ?",
            [$categoryId, $recipeId, $limit]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN-FACING QUERIES
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return a paginated recipe list for the admin panel with full filter support.
     *
     * @param int    $page
     * @param int    $perPage
     * @param string $search      Title search
     * @param int    $categoryId  0 = all
     * @param string $status      '' = all | 'published' | 'draft' | 'archived'
     */
    public function getPaginatedAdmin(
        int    $page       = 1,
        int    $perPage    = ADMIN_ITEMS_PER_PAGE,
        string $search     = '',
        int    $categoryId = 0,
        string $status     = ''
    ): array {
        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = "r.title LIKE ?";
            $params[] = '%' . $search . '%';
        }
        if ($categoryId > 0) {
            $where[]  = "r.category_id = ?";
            $params[] = $categoryId;
        }
        if ($status !== '') {
            $where[]  = "r.status = ?";
            $params[] = $status;
        }

        $whereSQL = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipes` r" . $whereSQL,
            $params
        );

        $pager  = paginate($total, $perPage, $page, url('admin/recipes'));
        $offset = $pager['offset'];

        $rows = $this->db->fetchAll(
            "SELECT r.*,
                    c.name AS category_name,
                    CONCAT(a.first_name,' ',a.last_name) AS admin_name
             FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             JOIN `admins`     a ON a.id = r.admin_id
             {$whereSQL}
             ORDER BY r.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CRUD
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a complete recipe (record only – ingredients/procedures are separate).
     *
     * @param array $data {title, slug, description, category_id, admin_id,
     *                     prep_time, cook_time, servings, difficulty,
     *                     calories?, is_premium, is_featured, status, image?}
     * @return int  New recipe ID
     */
    public function createRecipe(array $data): int
    {
        return $this->create([
            'admin_id'    => (int) $data['admin_id'],
            'category_id' => (int) $data['category_id'],
            'title'       => Security::cleanString($data['title']),
            'slug'        => Security::cleanString($data['slug']),
            'description' => Security::cleanTextarea($data['description']),
            'image'       => $data['image'] ?? null,
            'prep_time'   => (int) ($data['prep_time'] ?? 0),
            'cook_time'   => (int) ($data['cook_time'] ?? 0),
            'servings'    => max(1, (int) ($data['servings'] ?? 2)),
            'difficulty'  => $data['difficulty'] ?? 'medium',
            'calories'    => !empty($data['calories']) ? (int) $data['calories'] : null,
            'is_premium'  => (int) ($data['is_premium'] ?? 0),
            'is_featured' => (int) ($data['is_featured'] ?? 0),
            'status'      => $data['status'] ?? 'published',
        ]);
    }

    /**
     * Update a recipe record.
     */
    public function updateRecipe(int $id, array $data): bool
    {
        $update = [
            'category_id' => (int) $data['category_id'],
            'title'       => Security::cleanString($data['title']),
            'slug'        => Security::cleanString($data['slug']),
            'description' => Security::cleanTextarea($data['description']),
            'prep_time'   => (int) ($data['prep_time'] ?? 0),
            'cook_time'   => (int) ($data['cook_time'] ?? 0),
            'servings'    => max(1, (int) ($data['servings'] ?? 2)),
            'difficulty'  => $data['difficulty'] ?? 'medium',
            'calories'    => !empty($data['calories']) ? (int) $data['calories'] : null,
            'is_premium'  => (int) ($data['is_premium'] ?? 0),
            'is_featured' => (int) ($data['is_featured'] ?? 0),
            'status'      => $data['status'] ?? 'published',
            'updated_at'  => $this->now(),
        ];
        if (isset($data['image'])) {
            $update['image'] = $data['image'];
        }
        return $this->update($id, $update);
    }

    /**
     * Change a recipe's published status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['published', 'draft', 'archived'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        return $this->update($id, ['status' => $status, 'updated_at' => $this->now()]);
    }

    /**
     * Increment the view counter by 1 (non-blocking, low priority).
     */
    public function incrementViews(int $id): void
    {
        $this->db->query(
            "UPDATE `recipes` SET `views` = `views` + 1 WHERE `id` = ?",
            [$id]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN STATISTICS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return aggregate recipe stats for the admin dashboard.
     */
    public function getStats(): array
    {
        return [
            'total'     => $this->count(),
            'published' => $this->count(['status' => 'published']),
            'draft'     => $this->count(['status' => 'draft']),
            'premium'   => $this->count(['is_premium' => 1]),
            'featured'  => $this->count(['is_featured' => 1]),
            'total_views' => (int) $this->db->fetchColumn(
                "SELECT COALESCE(SUM(views), 0) FROM `recipes`"
            ),
        ];
    }

    /**
     * Return the top N most-viewed recipes.
     */
    public function getTopViewed(int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT r.id, r.title, r.views, r.status, c.name AS category_name
             FROM `recipes` r
             JOIN `categories` c ON c.id = r.category_id
             ORDER BY r.views DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Return recipe counts grouped by category for report charts.
     */
    public function getCountByCategory(): array
    {
        return $this->db->fetchAll(
            "SELECT c.name, COUNT(r.id) AS recipe_count
             FROM `categories` c
             LEFT JOIN `recipes` r ON r.category_id = c.id AND r.status = 'published'
             GROUP BY c.id
             ORDER BY recipe_count DESC"
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INTERNAL HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Build the extra WHERE conditions for public recipe filters.
     * Returns [SQL fragment (without leading AND), params].
     */
    private function buildPublicFilters(array $filters): array
    {
        $parts  = [];
        $params = [];

        if (!empty($filters['category_id'])) {
            $parts[]  = "AND r.category_id = ?";
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['difficulty'])) {
            $parts[]  = "AND r.difficulty = ?";
            $params[] = $filters['difficulty'];
        }
        if (isset($filters['is_premium'])) {
            $parts[]  = "AND r.is_premium = ?";
            $params[] = (int) $filters['is_premium'];
        }
        if (!empty($filters['search'])) {
            $parts[]  = "AND r.title LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }

        return [implode(' ', $parts), $params];
    }
}
