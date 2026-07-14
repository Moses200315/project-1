<?php

/**
 * MealPlanController – Customer Meal Planning
 * =============================================
 * index        – list all meal plans
 * create       – GET: creation form
 * store        – POST: save plan
 * view/{id}    – weekly plan grid
 * edit/{id}    – GET: edit form
 * update/{id}  – POST: update plan metadata
 * delete/{id}  – POST: delete plan
 * addSlot      – POST/AJAX: add recipe to a day slot
 * removeSlot   – POST/AJAX: remove recipe from slot
 * clearDay     – POST: clear all slots for a day
 * activate/{id} – POST: mark plan as active
 */

declare(strict_types=1);

class MealPlanController extends BaseController
{
    private MealPlanModel $mealPlanModel;
    private RecipeModel $recipeModel;
    private CategoryModel $categoryModel;
    private SubscriptionModel $subModel;

    public function __construct()
    {
        $this->mealPlanModel = new MealPlanModel();
        $this->recipeModel = new RecipeModel();
        $this->categoryModel = new CategoryModel();
        $this->subModel = new SubscriptionModel();
    }

    /** GET /mealplans/index */
    public function index(): void
    {
        $this->requireCustomer();
        $userId = $this->userId();

        $this->view("customer/meal_plans/index", [
            "pageTitle" => "My Meal Plans",
            "plans" => $this->mealPlanModel->findByUser($userId),
            "permissions" => $this->subModel->getUserPermissions($userId),
        ]);
    }

    /** GET /mealplans/create */
    public function create(): void
    {
        $this->requireCustomer();
        $userId = $this->userId();
        $permissions = $this->subModel->getUserPermissions($userId);

        // Enforce plan limit
        $limit = $permissions["meal_plan_limit"];
        if ($limit > 0) {
            $existing = $this->mealPlanModel->countByUser($userId, "month");
            if ($existing >= $limit) {
                $this->warning(
                    "Your {$permissions["plan_name"]} plan allows {$limit} meal plan(s) per month. Upgrade to create more.",
                );
                $this->redirectTo(url("mealplans/index"));
            }
        }

        // Default week: start from next Monday
        $monday = date("Y-m-d", strtotime("monday this week"));
        $sunday = date("Y-m-d", strtotime("monday this week +6 days"));

        $this->view("customer/meal_plans/create", [
            "pageTitle" => "Create Meal Plan",
            "weekStart" => $monday,
            "weekEnd" => $sunday,
            "permissions" => $permissions,
        ]);
    }

    /** POST /mealplans/store */
    public function store(): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();
        $userId = $this->userId();

        $name = Security::cleanString($this->post("name"));
        $weekStart = $this->post("week_start");
        $weekEnd = $this->post("week_end");
        $status = $this->post("status", "draft");

        if (empty($name)) {
            $this->error("Plan name is required.");
            $this->redirectTo(url("mealplans/create"));
        }

        $planId = $this->mealPlanModel->createPlan([
            "user_id" => $userId,
            "name" => $name,
            "description" => Security::cleanTextarea(
                $this->post("description"),
            ),
            "week_start" => $weekStart,
            "week_end" => $weekEnd,
            "status" => in_array($status, ["draft", "active"], true)
                ? $status
                : "draft",
        ]);

        $this->success("Meal plan created! Now add recipes to your plan.");
        $this->redirectTo(url("mealplans/show/" . $planId));
    }

    /** GET /mealplans/view/{id} */
    public function viewMealPlan(string $id = "0"): void
    {
        $this->show($id);
    }

    /** GET /mealplans/show/{id} */
    public function show(string $id = "0"): void
    {
        $this->requireCustomer();
        $planId = $this->resolveId($id);
        $userId = $this->userId();

        $plan = $this->mealPlanModel->getWithRecipes($planId, $userId);
        if (!$plan) {
            $this->abort404("Meal plan not found.");
        }

        // Build recipe options for the add-recipe modal
        $permissions = $this->subModel->getUserPermissions($userId);
        $filters = $permissions["can_access_premium"]
            ? []
            : ["is_premium" => 0];
        $recipes = $this->recipeModel->getPublishedPaginated(1, 50, $filters)[
            "rows"
        ];

        $this->view("customer/meal_plans/view", [
            "pageTitle" => e($plan["name"]),
            "plan" => $plan,
            "recipes" => $recipes,
            "categories" => $this->categoryModel->getDropdownList(),
            "days" => [
                "Monday",
                "Tuesday",
                "Wednesday",
                "Thursday",
                "Friday",
                "Saturday",
                "Sunday",
            ],
            "mealTypes" => ["breakfast", "lunch", "dinner", "snack"],
            "permissions" => $permissions,
        ]);
    }

    /** GET /mealplans/edit/{id} */
    public function edit(string $id = "0"): void
    {
        $this->requireCustomer();
        $planId = $this->resolveId($id);
        $plan = $this->mealPlanModel->findByIdAndUser($planId, $this->userId());
        if (!$plan) {
            $this->abort404();
        }

        $this->view("customer/meal_plans/edit", [
            "pageTitle" => "Edit: " . e($plan["name"]),
            "plan" => $plan,
        ]);
    }

    /** POST /mealplans/update/{id} */
    public function update(string $id = "0"): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();
        $planId = $this->resolveId($id);

        $name = Security::cleanString($this->post("name"));
        if (empty($name)) {
            $this->error("Plan name is required.");
            $this->redirectTo(url("mealplans/edit/" . $planId));
        }

        $this->mealPlanModel->updatePlan($planId, [
            "name" => $name,
            "description" => Security::cleanTextarea(
                $this->post("description"),
            ),
            "week_start" => $this->post("week_start"),
            "week_end" => $this->post("week_end"),
            "status" => $this->post("status", "draft"),
        ]);

        $this->success("Meal plan updated.");
        $this->redirectTo(url("mealplans/show/" . $planId));
    }

    /** POST /mealplans/delete/{id} */
    public function delete(string $id = "0"): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();
        $planId = $this->resolveId($id);

        $deleted = $this->mealPlanModel->deletePlan($planId, $this->userId());
        $deleted
            ? $this->success("Meal plan deleted.")
            : $this->error(
                "Could not delete – plan not found or permission denied.",
            );

        $this->redirectTo(url("mealplans/index"));
    }

    /** POST /mealplans/addSlot */
    public function addSlot(): never
    {
        $this->requireCustomer();
        $this->verifyCsrf();

        $planId = $this->intInput("meal_plan_id");
        $recipeId = $this->intInput("recipe_id");
        $day = $this->post("day_of_week");
        $mealType = $this->post("meal_type");
        $servings = max(1, $this->intInput("servings", 1));

        $validDays = [
            "Monday",
            "Tuesday",
            "Wednesday",
            "Thursday",
            "Friday",
            "Saturday",
            "Sunday",
        ];
        $validTypes = ["breakfast", "lunch", "dinner", "snack"];

        if (
            !in_array($day, $validDays, true) ||
            !in_array($mealType, $validTypes, true)
        ) {
            $this->json(["error" => "Invalid day or meal type."], 422);
        }

        // Verify plan ownership
        if (!$this->mealPlanModel->ownedBy($planId, $this->userId())) {
            $this->json(["error" => "Plan not found."], 404);
        }

        $slotId = $this->mealPlanModel->addRecipeSlot([
            "meal_plan_id" => $planId,
            "recipe_id" => $recipeId,
            "day_of_week" => $day,
            "meal_type" => $mealType,
            "servings" => $servings,
            "notes" => Security::cleanTextarea($this->post("notes")),
        ]);

        $recipe = $this->recipeModel->findById($recipeId);

        $this->json([
            "success" => true,
            "slot_id" => $slotId,
            "recipe_title" => $recipe["title"] ?? "",
            "message" => "Recipe added to " . $day . " " . $mealType . ".",
        ]);
    }

    /** POST /mealplans/removeSlot */
    public function removeSlot(): never
    {
        $this->requireCustomer();
        $this->verifyCsrf();

        $slotId = $this->intInput("slot_id");
        $removed = $this->mealPlanModel->removeRecipeSlot(
            $slotId,
            $this->userId(),
        );

        $this->json([
            "success" => $removed,
            "message" => $removed
                ? "Recipe removed from plan."
                : "Slot not found.",
        ]);
    }

    /** POST /mealplans/activate/{id} */
    public function activate(string $id = "0"): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();
        $planId = $this->resolveId($id);

        $this->mealPlanModel->update($planId, ["status" => "active"]);
        $this->success("Meal plan is now active!");
        $this->redirectTo(url("mealplans/show/" . $planId));
    }
}
