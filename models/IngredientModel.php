<?php

/**
 * IngredientModel – Recipe Ingredient Operations
 * ================================================
 * Manages the `ingredients` table.
 * Each ingredient belongs to exactly one recipe (CASCADE DELETE).
 */

declare(strict_types=1);

class IngredientModel extends BaseModel
{
    protected string $table      = 'ingredients';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return all ingredients for a recipe, ordered by sort_order.
     */
    public function findByRecipe(int $recipeId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `ingredients`
             WHERE `recipe_id` = ?
             ORDER BY `sort_order` ASC, `id` ASC",
            [$recipeId]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WRITE OPERATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Insert a single ingredient row.
     *
     * @param array $data {recipe_id, name, quantity, unit?, sort_order?}
     */
    public function addIngredient(array $data): int
    {
        return $this->create([
            'recipe_id'  => (int) $data['recipe_id'],
            'name'       => Security::cleanString($data['name']),
            'quantity'   => Security::cleanString($data['quantity']),
            'unit'       => isset($data['unit']) ? Security::cleanString($data['unit']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * Bulk-replace all ingredients for a recipe in a single transaction.
     * Deletes the old rows then inserts the new set.
     * Ideal for the "save recipe" form where the full ingredient list is resubmitted.
     *
     * @param int   $recipeId
     * @param array $ingredients  Array of {name, quantity, unit?} associative arrays
     */
    public function syncIngredients(int $recipeId, array $ingredients): void
    {
        $this->db->transaction(function (Database $db) use ($recipeId, $ingredients): void {
            // Delete existing
            $db->delete('ingredients', '`recipe_id` = ?', [$recipeId]);

            // Re-insert
            foreach ($ingredients as $order => $ing) {
                $name     = Security::cleanString($ing['name'] ?? '');
                $quantity = Security::cleanString($ing['quantity'] ?? '');
                if ($name === '' || $quantity === '') {
                    continue; // Skip blank rows submitted from the form
                }
                $db->insert('ingredients', [
                    'recipe_id'  => $recipeId,
                    'name'       => $name,
                    'quantity'   => $quantity,
                    'unit'       => !empty($ing['unit']) ? Security::cleanString($ing['unit']) : null,
                    'sort_order' => $order + 1,
                ]);
            }
        });
    }

    /**
     * Update a single ingredient.
     */
    public function updateIngredient(int $id, array $data): bool
    {
        return $this->update($id, [
            'name'       => Security::cleanString($data['name']),
            'quantity'   => Security::cleanString($data['quantity']),
            'unit'       => isset($data['unit']) ? Security::cleanString($data['unit']) : null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);
    }

    /**
     * Delete all ingredients for a recipe (called on recipe delete).
     */
    public function deleteByRecipe(int $recipeId): void
    {
        $this->db->delete('ingredients', '`recipe_id` = ?', [$recipeId]);
    }
}
