<?php

/**
 * FavouriteModel – Bookmarked Recipe Operations
 * ===============================================
 * Manages the `favourites` junction table.
 * The composite unique key (user_id, recipe_id) prevents duplicates
 * at the database level; the toggle method relies on this.
 */

declare(strict_types=1);

class FavouriteModel extends BaseModel
{
    protected string $table      = 'favourites';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return a paginated list of a user's favourite recipes (with recipe details).
     */
    public function findByUser(int $userId, int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `favourites` WHERE `user_id` = ?",
            [$userId]
        );

        $pager  = paginate($total, $perPage, $page, url('favourites'));
        $offset = $pager['offset'];

        $rows = $this->db->fetchAll(
            "SELECT f.id AS fav_id, f.created_at AS saved_at,
                    r.id, r.title, r.slug, r.image, r.prep_time, r.cook_time,
                    r.servings, r.difficulty, r.calories, r.is_premium,
                    c.name AS category_name, c.slug AS category_slug
             FROM `favourites` f
             JOIN `recipes`    r ON r.id = f.recipe_id
             JOIN `categories` c ON c.id = r.category_id
             WHERE f.user_id = ?
               AND r.status  = 'published'
             ORDER BY f.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    /**
     * Return all favourite recipe IDs for a user as a flat array.
     * Used to mark heart icons as active on listing pages.
     */
    public function getUserFavouriteIds(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT `recipe_id` FROM `favourites` WHERE `user_id` = ?",
            [$userId]
        );
        return array_column($rows, 'recipe_id');
    }

    /**
     * Check whether a specific recipe is already in the user's favourites.
     */
    public function isFavourite(int $userId, int $recipeId): bool
    {
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `favourites` WHERE `user_id` = ? AND `recipe_id` = ?",
            [$userId, $recipeId]
        );
        return (int) $count > 0;
    }

    /**
     * Count how many users have favourited a specific recipe.
     */
    public function countByRecipe(int $recipeId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `favourites` WHERE `recipe_id` = ?",
            [$recipeId]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WRITE OPERATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Add a recipe to the user's favourites.
     * Silently ignores if it already exists (INSERT IGNORE).
     */
    public function add(int $userId, int $recipeId): void
    {
        // INSERT IGNORE respects the unique key without throwing an error on duplicate
        $this->db->query(
            "INSERT IGNORE INTO `favourites` (`user_id`, `recipe_id`) VALUES (?, ?)",
            [$userId, $recipeId]
        );
    }

    /**
     * Remove a recipe from the user's favourites.
     */
    public function remove(int $userId, int $recipeId): void
    {
        $this->db->delete('favourites', '`user_id` = ? AND `recipe_id` = ?', [$userId, $recipeId]);
    }

    /**
     * Toggle: add if not present, remove if present.
     * Returns true when the recipe was ADDED, false when REMOVED.
     */
    public function toggle(int $userId, int $recipeId): bool
    {
        if ($this->isFavourite($userId, $recipeId)) {
            $this->remove($userId, $recipeId);
            return false; // removed
        }
        $this->add($userId, $recipeId);
        return true; // added
    }
}
