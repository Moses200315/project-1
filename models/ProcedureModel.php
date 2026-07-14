<?php

/**
 * ProcedureModel – Step-by-Step Cooking Instructions
 * ====================================================
 * Manages the `procedures` table.
 * Steps are uniquely ordered per recipe via the composite unique key
 * (recipe_id, step_number).
 */

declare(strict_types=1);

class ProcedureModel extends BaseModel
{
    protected string $table      = 'procedures';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return all procedure steps for a recipe, ordered by step_number ASC.
     */
    public function findByRecipe(int $recipeId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `procedures`
             WHERE `recipe_id` = ?
             ORDER BY `step_number` ASC",
            [$recipeId]
        );
    }

    /**
     * Return the highest step_number currently assigned to a recipe.
     * Returns 0 when no steps exist yet.
     */
    public function getMaxStep(int $recipeId): int
    {
        $result = $this->db->fetchColumn(
            "SELECT COALESCE(MAX(`step_number`), 0) FROM `procedures` WHERE `recipe_id` = ?",
            [$recipeId]
        );
        return (int) $result;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // WRITE OPERATIONS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Add a single procedure step.
     * Automatically assigns step_number = max + 1 if not provided.
     *
     * @param array $data {recipe_id, instruction, tip?, image?, step_number?}
     */
    public function addStep(array $data): int
    {
        $stepNumber = isset($data['step_number'])
            ? (int) $data['step_number']
            : $this->getMaxStep((int) $data['recipe_id']) + 1;

        return $this->create([
            'recipe_id'   => (int) $data['recipe_id'],
            'step_number' => $stepNumber,
            'instruction' => Security::cleanTextarea($data['instruction']),
            'tip'         => !empty($data['tip'])   ? Security::cleanTextarea($data['tip'])  : null,
            'image'       => $data['image'] ?? null,
        ]);
    }

    /**
     * Bulk-replace all steps for a recipe (delete + re-insert in a transaction).
     * Called from the "save recipe" form handler.
     *
     * @param int   $recipeId
     * @param array $steps  Array of {instruction, tip?} associative arrays (ordered)
     */
    public function syncProcedures(int $recipeId, array $steps): void
    {
        $this->db->transaction(function (Database $db) use ($recipeId, $steps): void {
            $db->delete('procedures', '`recipe_id` = ?', [$recipeId]);

            foreach ($steps as $index => $step) {
                $instruction = Security::cleanTextarea($step['instruction'] ?? '');
                if ($instruction === '') {
                    continue; // Skip blank steps
                }
                $db->insert('procedures', [
                    'recipe_id'   => $recipeId,
                    'step_number' => $index + 1,
                    'instruction' => $instruction,
                    'tip'         => !empty($step['tip']) ? Security::cleanTextarea($step['tip']) : null,
                    'image'       => $step['image'] ?? null,
                ]);
            }
        });
    }

    /**
     * Update a single procedure step.
     */
    public function updateStep(int $id, array $data): bool
    {
        $update = [
            'instruction' => Security::cleanTextarea($data['instruction']),
            'tip'         => !empty($data['tip']) ? Security::cleanTextarea($data['tip']) : null,
        ];
        if (isset($data['image'])) {
            $update['image'] = $data['image'];
        }
        return $this->update($id, $update);
    }

    /**
     * Delete all steps belonging to a recipe.
     */
    public function deleteByRecipe(int $recipeId): void
    {
        $this->db->delete('procedures', '`recipe_id` = ?', [$recipeId]);
    }

    /**
     * Renumber all steps for a recipe sequentially starting from 1.
     * Call after deleting a step in the middle of the sequence.
     */
    public function renumberSteps(int $recipeId): void
    {
        $steps = $this->findByRecipe($recipeId);
        foreach ($steps as $index => $step) {
            $this->update((int) $step['id'], ['step_number' => $index + 1]);
        }
    }
}
