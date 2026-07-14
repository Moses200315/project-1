<?php

/**
 * CategoryModel – Recipe Category Operations
 * ============================================
 * Manages the `categories` table.
 * Includes recipe-count joins for display and
 * slug uniqueness checks for SEO-friendly URLs.
 */

declare(strict_types=1);

class CategoryModel extends BaseModel
{
    protected string $table      = 'categories';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Find a category by its URL slug.
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `categories` WHERE `slug` = ? LIMIT 1",
            [$slug]
        );
    }

    /**
     * Return all active categories, each with a count of published recipes.
     * Used for navigation menus, filter bars, and the category browse page.
     */
    public function getAllWithRecipeCount(): array
    {
        return $this->db->fetchAll(
            "SELECT c.*,
                    COUNT(r.id) AS recipe_count
             FROM `categories` c
             LEFT JOIN `recipes` r
                    ON r.category_id = c.id AND r.status = 'published'
             WHERE c.status = 'active'
             GROUP BY c.id
             ORDER BY c.name ASC"
        );
    }

    /**
     * Return all categories (any status) for the admin panel table,
     * with recipe counts for both published and total recipes.
     */
    public function getAllForAdmin(): array
    {
        return $this->db->fetchAll(
            "SELECT c.*,
                    a.first_name AS creator_first,
                    a.last_name  AS creator_last,
                    SUM(CASE WHEN r.status = 'published' THEN 1 ELSE 0 END) AS published_count,
                    COUNT(r.id)                                              AS total_count
             FROM `categories` c
             JOIN  `admins` a ON a.id = c.admin_id
             LEFT JOIN `recipes` r ON r.category_id = c.id
             GROUP BY c.id
             ORDER BY c.created_at DESC"
        );
    }

    /**
     * Return only active categories as a simple id => name map.
     * Useful for populating <select> dropdowns.
     */
    public function getDropdownList(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT `id`, `name` FROM `categories` WHERE `status` = 'active' ORDER BY `name` ASC"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['id']] = $row['name'];
        }
        return $map;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CRUD
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a new category.
     *
     * @param array $data {name, slug, description?, image?, status, admin_id}
     * @return int  New category ID
     */
    public function createCategory(array $data): int
    {
        return $this->create([
            'admin_id'    => (int) $data['admin_id'],
            'name'        => Security::cleanString($data['name']),
            'slug'        => Security::cleanString($data['slug']),
            'description' => isset($data['description']) ? Security::cleanTextarea($data['description']) : null,
            'image'       => $data['image'] ?? null,
            'status'      => $data['status'] ?? 'active',
        ]);
    }

    /**
     * Update an existing category.
     *
     * @param int   $id
     * @param array $data
     */
    public function updateCategory(int $id, array $data): bool
    {
        $update = [
            'name'        => Security::cleanString($data['name']),
            'slug'        => Security::cleanString($data['slug']),
            'description' => isset($data['description']) ? Security::cleanTextarea($data['description']) : null,
            'status'      => $data['status'] ?? 'active',
            'updated_at'  => $this->now(),
        ];
        if (isset($data['image'])) {
            $update['image'] = $data['image'];
        }
        return $this->update($id, $update);
    }

    /**
     * Delete a category only if it has no recipes attached.
     *
     * @return array {success: bool, message: string}
     */
    public function safeDelete(int $id): array
    {
        $recipeCount = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipes` WHERE `category_id` = ?",
            [$id]
        );

        if ($recipeCount > 0) {
            return [
                'success' => false,
                'message' => "Cannot delete: {$recipeCount} recipe(s) are assigned to this category.",
            ];
        }

        $this->delete($id);
        return ['success' => true, 'message' => 'Category deleted successfully.'];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // VALIDATION HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Check whether a slug is already in use (for create/update validation).
     *
     * @param string $slug
     * @param int    $excludeId  ID of the category being updated (0 for new)
     */
    public function isSlugTaken(string $slug, int $excludeId = 0): bool
    {
        return $this->exists('slug', $slug, $excludeId);
    }
}
