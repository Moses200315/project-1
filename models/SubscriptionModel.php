<?php

/**
 * SubscriptionModel – Subscription Plan & User Subscription Operations
 * =====================================================================
 * Manages two tables:
 *   - `subscription_plans`  (plan definitions – admin managed)
 *   - `subscriptions`       (individual user subscription records)
 *
 * Core responsibility: determining what a logged-in user IS and IS NOT
 * allowed to do based on their active subscription tier.
 */

declare(strict_types=1);

class SubscriptionModel extends BaseModel
{
    protected string $table      = 'subscriptions';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // SUBSCRIPTION PLAN QUERIES
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return all active subscription plans, cheapest first.
     */
    public function getAllPlans(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `subscription_plans`
             WHERE `status` = 'active'
             ORDER BY `price` ASC"
        );
    }

    /**
     * Find a plan by its primary key.
     */
    public function findPlanById(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `subscription_plans` WHERE `id` = ? LIMIT 1",
            [$id]
        );
    }

    /**
     * Find a plan by its URL slug.
     */
    public function findPlanBySlug(string $slug): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `subscription_plans` WHERE `slug` = ? LIMIT 1",
            [$slug]
        );
    }

    /**
     * Return all plans (including inactive) for the admin panel.
     */
    public function getAllPlansAdmin(): array
    {
        return $this->db->fetchAll(
            "SELECT sp.*,
                    COUNT(s.id) AS subscriber_count
             FROM `subscription_plans` sp
             LEFT JOIN `subscriptions` s
                    ON s.plan_id = sp.id AND s.status = 'active'
             GROUP BY sp.id
             ORDER BY sp.price ASC"
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // USER SUBSCRIPTION QUERIES
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return a user's single active subscription (with plan details).
     * Returns null when no active subscription exists.
     */
    public function getActiveSubscription(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT s.*,
                    sp.name                AS plan_name,
                    sp.slug                AS plan_slug,
                    sp.price               AS plan_price,
                    sp.can_download        AS plan_can_download,
                    sp.can_access_premium  AS plan_can_access_premium,
                    sp.meal_plan_limit     AS plan_meal_plan_limit,
                    sp.recipe_limit        AS plan_recipe_limit,
                    sp.features            AS plan_features,
                    sp.duration_days       AS plan_duration_days
             FROM `subscriptions` s
             JOIN `subscription_plans` sp ON sp.id = s.plan_id
             WHERE s.user_id = ?
               AND s.status  = 'active'
               AND s.ends_at > NOW()
             ORDER BY s.created_at DESC
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Return all subscription history for a user (any status), newest first.
     */
    public function getSubscriptionHistory(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT s.*, sp.name AS plan_name, sp.price AS plan_price
             FROM `subscriptions` s
             JOIN `subscription_plans` sp ON sp.id = s.plan_id
             WHERE s.user_id = ?
             ORDER BY s.created_at DESC",
            [$userId]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PERMISSION CHECKS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return true if the user has any active subscription.
     */
    public function hasActiveSubscription(int $userId): bool
    {
        return $this->getActiveSubscription($userId) !== null;
    }

    /**
     * Return true if the user can access premium recipes.
     */
    public function canAccessPremium(int $userId): bool
    {
        $sub = $this->getActiveSubscription($userId);
        return $sub !== null && (bool) $sub['plan_can_access_premium'];
    }

    /**
     * Return true if the user is allowed to download recipe PDFs.
     */
    public function canDownload(int $userId): bool
    {
        $sub = $this->getActiveSubscription($userId);
        return $sub !== null && (bool) $sub['plan_can_download'];
    }

    /**
     * Return the meal plan limit for the user's current plan.
     * 0 = unlimited, null = no active subscription (free tier).
     */
    public function getMealPlanLimit(int $userId): ?int
    {
        $sub = $this->getActiveSubscription($userId);
        if ($sub === null) {
            return 1; // Unauthenticated / no subscription fallback
        }
        return (int) $sub['plan_meal_plan_limit'];
    }

    /**
     * Build a permissions summary array for the current user.
     * Returned to controllers to gate UI features without multiple DB calls.
     *
     * @return array {
     *   has_subscription, plan_name, plan_slug, ends_at,
     *   can_download, can_access_premium,
     *   meal_plan_limit, recipe_limit,
     *   days_remaining
     * }
     */
    public function getUserPermissions(int $userId): array
    {
        $sub = $this->getActiveSubscription($userId);

        if ($sub === null) {
            return [
                'has_subscription'    => false,
                'plan_name'           => 'Free',
                'plan_slug'           => 'free',
                'ends_at'             => null,
                'can_download'        => false,
                'can_access_premium'  => false,
                'meal_plan_limit'     => 1,
                'recipe_limit'        => 5,
                'days_remaining'      => 0,
            ];
        }

        $daysRemaining = 0;
        if ($sub['ends_at']) {
            $diff = (new DateTime($sub['ends_at']))->diff(new DateTime());
            $daysRemaining = max(0, (int) $diff->days);
        }

        return [
            'has_subscription'    => true,
            'plan_name'           => $sub['plan_name'],
            'plan_slug'           => $sub['plan_slug'],
            'ends_at'             => $sub['ends_at'],
            'can_download'        => (bool) $sub['plan_can_download'],
            'can_access_premium'  => (bool) $sub['plan_can_access_premium'],
            'meal_plan_limit'     => (int) $sub['plan_meal_plan_limit'],
            'recipe_limit'        => (int) $sub['plan_recipe_limit'],
            'days_remaining'      => $daysRemaining,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SUBSCRIPTION LIFECYCLE
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a new PENDING subscription record.
     * The subscription becomes active only after payment succeeds.
     *
     * @param int $userId
     * @param int $planId
     * @return int  New subscription ID
     */
    public function createPending(int $userId, int $planId): int
    {
        // Cancel any existing pending subscription for this user+plan
        $this->db->update('subscriptions', ['status' => 'cancelled'],
            '`user_id` = ? AND `plan_id` = ? AND `status` = ?',
            [$userId, $planId, 'pending']
        );

        return $this->create([
            'user_id'   => $userId,
            'plan_id'   => $planId,
            'status'    => 'pending',
        ]);
    }

    /**
     * Activate a subscription after successful payment.
     * Sets starts_at = now, ends_at = now + plan duration.
     *
     * @param int $subscriptionId
     * @param int $planDurationDays  From subscription_plans.duration_days
     */
    public function activate(int $subscriptionId, int $planDurationDays): bool
    {
        // First expire any existing active subscription for the same user
        $sub = $this->findById($subscriptionId);
        if ($sub) {
            $this->db->update('subscriptions', ['status' => 'expired'],
                '`user_id` = ? AND `status` = ? AND `id` != ?',
                [$sub['user_id'], 'active', $subscriptionId]
            );
        }

        $now    = $this->now();
        $endsAt = date('Y-m-d H:i:s', time() + ($planDurationDays * 86400));

        return $this->update($subscriptionId, [
            'status'     => 'active',
            'starts_at'  => $now,
            'ends_at'    => $endsAt,
            'updated_at' => $now,
        ]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancel(int $subscriptionId, int $userId): bool
    {
        $affected = $this->db->update('subscriptions', [
            'status'       => 'cancelled',
            'cancelled_at' => $this->now(),
            'auto_renew'   => 0,
            'updated_at'   => $this->now(),
        ], '`id` = ? AND `user_id` = ?', [$subscriptionId, $userId]);

        return $affected > 0;
    }

    /**
     * Batch-expire all subscriptions whose ends_at has passed.
     * Run from a cron job or on each admin login.
     *
     * @return int  Number of subscriptions expired
     */
    public function expireOverdue(): int
    {
        $stmt = $this->db->query(
            "UPDATE `subscriptions`
             SET `status` = 'expired', `updated_at` = NOW()
             WHERE `status` = 'active' AND `ends_at` <= NOW()"
        );
        return $stmt->rowCount();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN STATISTICS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return aggregate subscription stats for the admin dashboard.
     */
    public function getStats(): array
    {
        return [
            'total'          => $this->count(),
            'active'         => $this->count(['status' => 'active']),
            'expired'        => $this->count(['status' => 'expired']),
            'cancelled'      => $this->count(['status' => 'cancelled']),
            'new_this_month' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM `subscriptions`
                 WHERE MONTH(created_at) = MONTH(NOW())
                   AND YEAR(created_at)  = YEAR(NOW())
                   AND status = 'active'"
            ),
        ];
    }

    /**
     * Return subscriber counts grouped by plan for admin reports.
     */
    public function getCountByPlan(): array
    {
        return $this->db->fetchAll(
            "SELECT sp.name, COUNT(s.id) AS subscriber_count
             FROM `subscription_plans` sp
             LEFT JOIN `subscriptions` s
                    ON s.plan_id = sp.id AND s.status = 'active'
             GROUP BY sp.id
             ORDER BY sp.price ASC"
        );
    }

    /**
     * Return a paginated list of all subscriptions for the admin panel.
     */
    public function getPaginatedAdmin(int $page = 1, int $perPage = ADMIN_ITEMS_PER_PAGE, string $status = ''): array
    {
        $where  = $status !== '' ? "WHERE s.status = ?" : '';
        $params = $status !== '' ? [$status] : [];

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `subscriptions` s {$where}",
            $params
        );

        $pager  = paginate($total, $perPage, $page, url('admin/subscriptions'));
        $offset = $pager['offset'];

        $rows = $this->db->fetchAll(
            "SELECT s.*,
                    CONCAT(u.first_name,' ',u.last_name) AS user_name,
                    u.email    AS user_email,
                    sp.name    AS plan_name,
                    sp.price   AS plan_price
             FROM `subscriptions` s
             JOIN `users`              u  ON u.id  = s.user_id
             JOIN `subscription_plans` sp ON sp.id = s.plan_id
             {$where}
             ORDER BY s.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return ['rows' => $rows, 'pager' => $pager];
    }
}
