<?php

/**
 * RecipeDownloadModel – PDF Download Tracking
 * =============================================
 * Manages the `recipe_downloads` audit table.
 * Every time a user downloads a recipe PDF a record is written here.
 * Used for:
 *   - Download-limit enforcement per subscription plan
 *   - Admin reporting on popular downloads
 *   - Preventing duplicate same-day downloads
 */

declare(strict_types=1);

class RecipeDownloadModel extends BaseModel
{
    protected string $table      = 'recipe_downloads';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return a paginated download history for a customer.
     */
    public function getByUser(int $userId, int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipe_downloads` WHERE `user_id` = ?",
            [$userId]
        );

        $pager  = paginate($total, $perPage, $page, url('profile/downloads'));
        $offset = $pager['offset'];

        $rows = $this->db->fetchAll(
            "SELECT rd.*,
                    r.title AS recipe_title,
                    r.slug  AS recipe_slug,
                    r.image AS recipe_image,
                    c.name  AS category_name
             FROM `recipe_downloads` rd
             JOIN `recipes`    r ON r.id = rd.recipe_id
             JOIN `categories` c ON c.id = r.category_id
             WHERE rd.user_id = ?
             ORDER BY rd.downloaded_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    /**
     * Count total downloads by a user (all time).
     */
    public function countByUser(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipe_downloads` WHERE `user_id` = ?",
            [$userId]
        );
    }

    /**
     * Count downloads by a user in the current calendar month.
     * Used to enforce monthly download limits for Basic plan users.
     */
    public function countByUserThisMonth(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipe_downloads`
             WHERE `user_id` = ?
               AND MONTH(downloaded_at) = MONTH(NOW())
               AND YEAR(downloaded_at)  = YEAR(NOW())",
            [$userId]
        );
    }

    /**
     * Check whether a user already downloaded a specific recipe today.
     * Prevents redundant PDF generation and double-logging.
     */
    public function hasDownloadedToday(int $userId, int $recipeId): bool
    {
        $count = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `recipe_downloads`
             WHERE `user_id`   = ?
               AND `recipe_id` = ?
               AND DATE(downloaded_at) = CURDATE()",
            [$userId, $recipeId]
        );
        return (int) $count > 0;
    }

    /**
     * Return the most popular downloaded recipes.
     */
    public function getPopular(int $limit = 10): array
    {
        return $this->db->fetchAll(
            "SELECT r.id, r.title, r.slug, r.image,
                    COUNT(rd.id) AS download_count
             FROM `recipe_downloads` rd
             JOIN `recipes` r ON r.id = rd.recipe_id
             GROUP BY rd.recipe_id
             ORDER BY download_count DESC
             LIMIT ?",
            [$limit]
        );
    }

    /**
     * Return aggregate download statistics for the admin panel.
     */
    public function getStats(): array
    {
        return [
            'total'         => $this->count(),
            'this_month'    => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM `recipe_downloads`
                 WHERE MONTH(downloaded_at) = MONTH(NOW())
                   AND YEAR(downloaded_at)  = YEAR(NOW())"
            ),
            'unique_users'  => (int) $this->db->fetchColumn(
                "SELECT COUNT(DISTINCT user_id) FROM `recipe_downloads`"
            ),
            'unique_recipes'=> (int) $this->db->fetchColumn(
                "SELECT COUNT(DISTINCT recipe_id) FROM `recipe_downloads`"
            ),
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WRITE
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Log a recipe PDF download.
     *
     * @param int    $userId
     * @param int    $recipeId
     * @param string $ipAddress  Client IP (IPv4 or IPv6)
     * @return int   New record ID
     */
    public function logDownload(int $userId, int $recipeId, string $ipAddress = ''): int
    {
        return $this->create([
            'user_id'    => $userId,
            'recipe_id'  => $recipeId,
            'ip_address' => $ipAddress ?: ($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }
}
