<?php

/**
 * NotificationModel – In-App Notification Operations
 * ====================================================
 * Manages the `notifications` table.
 * Provides factory methods to create typed notifications
 * (subscription activated, payment received, new recipe, etc.)
 */

declare(strict_types=1);

class NotificationModel extends BaseModel
{
    protected string $table      = 'notifications';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return the most recent N notifications for a user.
     * Ordered newest-first.
     */
    public function getForUser(int $userId, int $limit = NOTIFICATION_LIMIT): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `notifications`
             WHERE `user_id` = ?
             ORDER BY `created_at` DESC
             LIMIT ?",
            [$userId, $limit]
        );
    }

    /**
     * Return only unread notifications for a user.
     */
    public function getUnread(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `notifications`
             WHERE `user_id` = ? AND `is_read` = 0
             ORDER BY `created_at` DESC",
            [$userId]
        );
    }

    /**
     * Count unread notifications for the nav-bar badge.
     */
    public function countUnread(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `notifications`
             WHERE `user_id` = ? AND `is_read` = 0",
            [$userId]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WRITE OPERATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a notification record.
     *
     * @param array $data {
     *   user_id, title, message,
     *   type?     ('info'|'success'|'warning'|'error'),
     *   category? ('subscription'|'payment'|'recipe'|'meal_plan'|'system'|'general'),
     *   action_url?
     * }
     * @return int  New notification ID
     */
    public function notify(array $data): int
    {
        return $this->create([
            'user_id'    => (int) $data['user_id'],
            'title'      => Security::cleanString($data['title']),
            'message'    => Security::cleanTextarea($data['message']),
            'type'       => $data['type']       ?? 'info',
            'category'   => $data['category']   ?? 'general',
            'is_read'    => 0,
            'action_url' => !empty($data['action_url']) ? $data['action_url'] : null,
        ]);
    }

    /**
     * Mark a single notification as read.
     * Verifies ownership before updating.
     */
    public function markRead(int $notificationId, int $userId): bool
    {
        $affected = $this->db->update('notifications', [
            'is_read' => 1,
            'read_at' => $this->now(),
        ], '`id` = ? AND `user_id` = ?', [$notificationId, $userId]);

        return $affected > 0;
    }

    /**
     * Mark ALL of a user's unread notifications as read in one query.
     */
    public function markAllRead(int $userId): void
    {
        $this->db->query(
            "UPDATE `notifications`
             SET `is_read` = 1, `read_at` = NOW()
             WHERE `user_id` = ? AND `is_read` = 0",
            [$userId]
        );
    }

    /**
     * Delete a notification (ownership verified).
     */
    public function deleteForUser(int $notificationId, int $userId): bool
    {
        $affected = $this->db->delete(
            'notifications',
            '`id` = ? AND `user_id` = ?',
            [$notificationId, $userId]
        );
        return $affected > 0;
    }

    /**
     * Delete all read notifications older than N days for a user.
     * Used to keep the notification inbox tidy.
     */
    public function pruneRead(int $userId, int $olderThanDays = 30): void
    {
        $this->db->query(
            "DELETE FROM `notifications`
             WHERE `user_id` = ?
               AND `is_read` = 1
               AND `created_at` < NOW() - INTERVAL ? DAY",
            [$userId, $olderThanDays]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // TYPED NOTIFICATION FACTORIES
    // Quick methods for common events — keeps controller code clean.
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Notify a user that their subscription was activated.
     */
    public function notifySubscriptionActivated(int $userId, string $planName, string $endsAt): void
    {
        $this->notify([
            'user_id'    => $userId,
            'title'      => 'Subscription Activated – ' . $planName,
            'message'    => "Your {$planName} subscription is now active and runs until " . format_date($endsAt, 'F j, Y') . '.',
            'type'       => 'success',
            'category'   => 'subscription',
            'action_url' => url('subscriptions'),
        ]);
    }

    /**
     * Notify a user that a payment was successful.
     */
    public function notifyPaymentSuccess(int $userId, float $amount, string $provider): void
    {
        $this->notify([
            'user_id'    => $userId,
            'title'      => 'Payment Successful – ' . format_currency($amount),
            'message'    => "We received your payment of " . format_currency($amount) . " via {$provider}. Your subscription has been activated.",
            'type'       => 'success',
            'category'   => 'payment',
            'action_url' => url('payments'),
        ]);
    }

    /**
     * Notify a user that a payment failed.
     */
    public function notifyPaymentFailed(int $userId, float $amount, string $reason): void
    {
        $this->notify([
            'user_id'    => $userId,
            'title'      => 'Payment Failed',
            'message'    => "Your payment of " . format_currency($amount) . " could not be processed. Reason: {$reason} Please try again.",
            'type'       => 'error',
            'category'   => 'payment',
            'action_url' => url('subscriptions'),
        ]);
    }

    /**
     * Notify a user that their subscription is expiring soon.
     */
    public function notifySubscriptionExpiringSoon(int $userId, string $planName, int $daysLeft): void
    {
        $this->notify([
            'user_id'    => $userId,
            'title'      => 'Subscription Expiring Soon',
            'message'    => "Your {$planName} subscription expires in {$daysLeft} day(s). Renew now to keep uninterrupted access.",
            'type'       => 'warning',
            'category'   => 'subscription',
            'action_url' => url('subscriptions'),
        ]);
    }

    /**
     * Notify a user that a new recipe has been published.
     */
    public function notifyNewRecipe(int $userId, string $recipeTitle, int $recipeId): void
    {
        $this->notify([
            'user_id'    => $userId,
            'title'      => 'New Recipe: ' . $recipeTitle,
            'message'    => "A delicious new recipe – \"{$recipeTitle}\" – has just been added to your library.",
            'type'       => 'info',
            'category'   => 'recipe',
            'action_url' => url('recipes/view/' . $recipeId),
        ]);
    }
}
