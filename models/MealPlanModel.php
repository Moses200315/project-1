<?php

/**
 * MealPlanModel – Weekly Meal Plan Operations
 * =============================================
 * Manages both the `meal_plans` parent table and the
 * `meal_plan_recipes` junction table.
 *
 * A meal plan belongs to one customer and holds a set of
 * recipe assignments, each tied to a specific day + meal type.
 */

declare(strict_types=1);

class MealPlanModel extends BaseModel
{
    protected string $table      = 'meal_plans';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // PLAN LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return all meal plans for a specific customer, newest first.
     */
    public function findByUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT mp.*,
                    COUNT(mpr.id) AS recipe_count
             FROM `meal_plans` mp
             LEFT JOIN `meal_plan_recipes` mpr ON mpr.meal_plan_id = mp.id
             WHERE mp.user_id = ?
             GROUP BY mp.id
             ORDER BY mp.created_at DESC",
            [$userId]
        );
    }

    /**
     * Return a single plan with its assigned recipe slots (full detail).
     * Returns null when not found or when the plan does not belong to $userId.
     */
    public function getWithRecipes(int $planId, int $userId): ?array
    {
        $plan = $this->db->fetchOne(
            "SELECT * FROM `meal_plans` WHERE `id` = ? AND `user_id` = ? LIMIT 1",
            [$planId, $userId]
        );

        if ($plan === null) {
            return null;
        }

        // Fetch all assigned slots with recipe details
        $slots = $this->db->fetchAll(
            "SELECT mpr.*,
                    r.title        AS recipe_title,
                    r.slug         AS recipe_slug,
                    r.image        AS recipe_image,
                    r.prep_time,
                    r.cook_time,
                    r.servings     AS default_servings,
                    r.calories,
                    r.difficulty,
                    c.name         AS category_name
             FROM `meal_plan_recipes` mpr
             JOIN `recipes`    r ON r.id = mpr.recipe_id
             JOIN `categories` c ON c.id = r.category_id
             WHERE mpr.meal_plan_id = ?
             ORDER BY FIELD(mpr.day_of_week,
                'Monday','Tuesday','Wednesday','Thursday',
                'Friday','Saturday','Sunday'),
                FIELD(mpr.meal_type,'breakfast','lunch','dinner','snack')",
            [$planId]
        );

        // Fetch ingredients for each recipe and scale based on servings
        foreach ($slots as &$slot) {
            $ingredients = $this->db->fetchAll(
                "SELECT * FROM `ingredients` WHERE `recipe_id` = ? ORDER BY `sort_order` ASC",
                [$slot['recipe_id']]
            );

            // Scale ingredients if servings differ from recipe default
            if ($slot['servings'] != $slot['default_servings'] && $slot['default_servings'] > 0) {
                $scaleFactor = $slot['servings'] / $slot['default_servings'];
                foreach ($ingredients as &$ing) {
                    $ing['quantity'] = scale_quantity($ing['quantity'], $slot['default_servings'], $slot['servings']);
                }
            }

            $slot['ingredients'] = $ingredients;
        }

        // Group slots by day for the weekly grid view
        $byDay = [];
        $days  = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
        foreach ($days as $day) {
            $byDay[$day] = array_filter($slots, fn($s) => $s['day_of_week'] === $day);
        }

        $plan['slots']  = $slots;
        $plan['by_day'] = $byDay;

        return $plan;
    }

    /**
     * Return the most recent active meal plan for a user.
     */
    public function getActivePlan(int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `meal_plans`
             WHERE `user_id` = ? AND `status` = 'active'
             ORDER BY `created_at` DESC
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Count meal plans for a user (used to enforce plan-limit per subscription).
     *
     * @param int    $userId
     * @param string $period  'month' | 'all'
     */
    public function countByUser(int $userId, string $period = 'all'): int
    {
        if ($period === 'month') {
            return (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM `meal_plans`
                 WHERE `user_id` = ?
                   AND MONTH(`created_at`) = MONTH(NOW())
                   AND YEAR(`created_at`)  = YEAR(NOW())",
                [$userId]
            );
        }
        return $this->count(['user_id' => $userId]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PLAN CRUD
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a new meal plan for a customer.
     *
     * @param array $data {user_id, name, description?, week_start, week_end, status?}
     * @return int  New plan ID
     */
    public function createPlan(array $data): int
    {
        return $this->create([
            'user_id'     => (int) $data['user_id'],
            'name'        => Security::cleanString($data['name']),
            'description' => !empty($data['description']) ? Security::cleanTextarea($data['description']) : null,
            'week_start'  => $data['week_start'],
            'week_end'    => $data['week_end'],
            'status'      => $data['status'] ?? 'draft',
        ]);
    }

    /**
     * Update a meal plan's metadata.
     */
    public function updatePlan(int $id, array $data): bool
    {
        return $this->update($id, [
            'name'        => Security::cleanString($data['name']),
            'description' => !empty($data['description']) ? Security::cleanTextarea($data['description']) : null,
            'week_start'  => $data['week_start'],
            'week_end'    => $data['week_end'],
            'status'      => $data['status'] ?? 'draft',
            'updated_at'  => $this->now(),
        ]);
    }

    /**
     * Delete a meal plan (cascade removes all assigned recipe slots).
     * Verifies ownership before deleting.
     *
     * @return bool  false if the plan does not belong to the user
     */
    public function deletePlan(int $planId, int $userId): bool
    {
        $plan = $this->db->fetchOne(
            "SELECT id FROM `meal_plans` WHERE `id` = ? AND `user_id` = ? LIMIT 1",
            [$planId, $userId]
        );

        if ($plan === null) {
            return false;
        }

        return $this->delete($planId);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RECIPE SLOT MANAGEMENT  (meal_plan_recipes)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Assign a recipe to a day/meal-type slot within a meal plan.
     *
     * @param array $data {meal_plan_id, recipe_id, day_of_week, meal_type, servings?, notes?}
     * @return int  New slot ID
     */
    public function addRecipeSlot(array $data): int
    {
        return $this->db->insert('meal_plan_recipes', [
            'meal_plan_id' => (int) $data['meal_plan_id'],
            'recipe_id'    => (int) $data['recipe_id'],
            'day_of_week'  => $data['day_of_week'],
            'meal_type'    => $data['meal_type'],
            'servings'     => max(1, (int) ($data['servings'] ?? 1)),
            'notes'        => !empty($data['notes']) ? Security::cleanTextarea($data['notes']) : null,
        ]);
    }

    /**
     * Update the servings or notes for a slot.
     */
    public function updateSlot(int $slotId, int $servings, string $notes = ''): bool
    {
        $affected = $this->db->update('meal_plan_recipes', [
            'servings' => max(1, $servings),
            'notes'    => $notes !== '' ? Security::cleanTextarea($notes) : null,
        ], '`id` = ?', [$slotId]);

        return $affected > 0;
    }

    /**
     * Remove a recipe from a meal plan slot.
     * Verifies the slot belongs to a plan owned by $userId.
     */
    public function removeRecipeSlot(int $slotId, int $userId): bool
    {
        // Ownership check via JOIN
        $slot = $this->db->fetchOne(
            "SELECT mpr.id FROM `meal_plan_recipes` mpr
             JOIN `meal_plans` mp ON mp.id = mpr.meal_plan_id
             WHERE mpr.id = ? AND mp.user_id = ?
             LIMIT 1",
            [$slotId, $userId]
        );

        if ($slot === null) {
            return false;
        }

        return $this->db->delete('meal_plan_recipes', '`id` = ?', [$slotId]) > 0;
    }

    /**
     * Remove all slots for a specific day within a meal plan.
     */
    public function clearDay(int $planId, string $dayOfWeek): void
    {
        $this->db->delete(
            'meal_plan_recipes',
            '`meal_plan_id` = ? AND `day_of_week` = ?',
            [$planId, $dayOfWeek]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OWNERSHIP HELPERS  (called from controllers)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Find a plan by ID, verifying it belongs to a specific user.
     * Returns null when not found or ownership doesn't match.
     */
    public function findByIdAndUser(int $planId, int $userId): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `meal_plans` WHERE `id` = ? AND `user_id` = ? LIMIT 1",
            [$planId, $userId]
        );
    }

    /**
     * Check whether a plan exists and belongs to a user (lighter than fetchOne).
     */
    public function ownedBy(int $planId, int $userId): bool
    {
        return (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `meal_plans` WHERE `id` = ? AND `user_id` = ?",
            [$planId, $userId]
        ) > 0;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return aggregate meal plan statistics for the admin dashboard.
     */
    public function getStats(): array
    {
        return [
            'total'    => $this->count(),
            'active'   => $this->count(['status' => 'active']),
            'draft'    => $this->count(['status' => 'draft']),
            'completed'=> $this->count(['status' => 'completed']),
            'this_month' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM `meal_plans`
                 WHERE MONTH(created_at) = MONTH(NOW())
                   AND YEAR(created_at)  = YEAR(NOW())"
            ),
        ];
    }
}
