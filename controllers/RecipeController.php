<?php

/**
 * RecipeController – Recipe Management & Viewing
 * ================================================
 * Customer routes:
 *   index         – paginated, filterable recipe browse
 *   view/{id}     – full recipe detail + serving calculator
 *   search        – fulltext search results
 *   download/{id} – PDF (print-optimised HTML) download
 *   calculate     – AJAX: scale ingredient quantities
 *
 * Admin routes:
 *   adminIndex    – admin recipe table
 *   create        – GET: creation form
 *   store         – POST: save new recipe
 *   edit/{id}     – GET: edit form
 *   update/{id}   – POST: save changes
 *   delete/{id}   – POST: soft-delete (archive)
 *   toggleFeatured/{id} – AJAX: toggle is_featured
 */

declare(strict_types=1);

class RecipeController extends BaseController
{
    private RecipeModel $recipeModel;
    private CategoryModel $categoryModel;
    private IngredientModel $ingModel;
    private ProcedureModel $procModel;
    private FavouriteModel $favModel;
    private SubscriptionModel $subModel;
    private RecipeDownloadModel $downloadModel;
    private NotificationModel $notifModel;

    public function __construct()
    {
        $this->recipeModel = new RecipeModel();
        $this->categoryModel = new CategoryModel();
        $this->ingModel = new IngredientModel();
        $this->procModel = new ProcedureModel();
        $this->favModel = new FavouriteModel();
        $this->subModel = new SubscriptionModel();
        $this->downloadModel = new RecipeDownloadModel();
        $this->notifModel = new NotificationModel();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CUSTOMER – BROWSE
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /recipes/index */
    public function index(): void
    {
        $this->requireLogin();

        $userId = $this->userId();
        $permissions = $this->subModel->getUserPermissions($userId);

        // Require active subscription to view any recipes (admins exempt)
        if (!Session::isAdmin() && !$permissions["has_subscription"]) {
            $this->warning(
                "Recipe viewing requires an active subscription. Please subscribe to access our recipe collection.",
            );
            $this->redirectTo(url("subscriptions"));
        }

        $filters = [
            "category_id" => $this->intInput("category"),
            "difficulty" => $this->query("difficulty"),
            "search" => $this->query("search"),
        ];

        $result = $this->recipeModel->getPublishedPaginated(
            $this->currentPage(),
            ITEMS_PER_PAGE,
            $filters,
            $this->query("sort", "newest"),
        );

        $this->view("customer/recipes/index", [
            "pageTitle" => "Recipes – " . APP_NAME,
            "rows" => $result["rows"],
            "pager" => $result["pager"],
            "categories" => $this->categoryModel->getAllWithRecipeCount(),
            "filters" => $filters,
            "sort" => $this->query("sort", "newest"),
            "favIds" => Session::isLoggedIn()
                ? $this->favModel->getUserFavouriteIds($userId)
                : [],
            "permissions" => $permissions,
        ]);
    }

    /** GET /recipes/view/{id} */
    public function viewRecipe(string $id = "0"): void
    {
        $this->show($id);
    }

    /** GET /recipes/show/{id} */
    public function show(string $id = "0"): void
    {
        $this->requireLogin();
        $recipeId = $this->resolveId($id);

        $userId = $this->userId();
        $permissions = $this->subModel->getUserPermissions($userId);

        // Require active subscription to view any recipes (admins exempt)
        if (!Session::isAdmin() && !$permissions["has_subscription"]) {
            $this->warning(
                "Recipe viewing requires an active subscription. Please subscribe to access our recipe collection.",
            );
            $this->redirectTo(url("subscriptions"));
        }

        $recipe = $this->recipeModel->getWithDetails($recipeId);
        if (!$recipe || $recipe["status"] !== "published") {
            $this->abort404("Recipe not found.");
        }

        // Increment view counter (fire-and-forget)
        $this->recipeModel->incrementViews($recipeId);

        // Scale ingredients to default 1 serving
        $originalServings = (int) $recipe["servings"];
        $defaultServings = 1;
        
        if ($originalServings > 0 && $originalServings !== $defaultServings) {
            $recipe["ingredients"] = array_map(function (array $ing) use (
                $originalServings,
                $defaultServings,
            ): array {
                $ing["quantity"] = scale_quantity(
                    $ing["quantity"],
                    $originalServings,
                    $defaultServings,
                );
                return $ing;
            }, $recipe["ingredients"]);
        }

        $this->view("customer/recipes/view", [
            "pageTitle" => e($recipe["title"]) . " – " . APP_NAME,
            "recipe" => $recipe,
            "related" => $this->recipeModel->getRelated(
                $recipeId,
                (int) $recipe["category_id"],
                4,
            ),
            "isFav" => $this->favModel->isFavourite($userId, $recipeId),
            "favCount" => $this->favModel->countByRecipe($recipeId),
            "permissions" => $permissions,
        ]);
    }

    /** GET /recipes/search */
    public function search(): void
    {
        $this->requireLogin();

        $userId = $this->userId();
        $permissions = $this->subModel->getUserPermissions($userId);

        // Require active subscription to search recipes (admins exempt)
        if (!Session::isAdmin() && !$permissions["has_subscription"]) {
            $this->warning(
                "Recipe search requires an active subscription. Please subscribe to access our recipe collection.",
            );
            $this->redirectTo(url("subscriptions"));
        }

        $query = $this->query("q");
        $result =
            $query !== ""
                ? $this->recipeModel->search($query, $this->currentPage())
                : ["rows" => [], "pager" => paginate(0, ITEMS_PER_PAGE, 1)];

        $this->view("customer/recipes/search", [
            "pageTitle" => "Search: " . e($query) . " – " . APP_NAME,
            "query" => $query,
            "rows" => $result["rows"],
            "pager" => $result["pager"],
            "favIds" => $this->favModel->getUserFavouriteIds($userId),
            "permissions" => $permissions,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SERVING SIZE CALCULATOR  (AJAX)
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /recipes/calculate?recipe_id=X&servings=Y */
    public function calculate(): never
    {
        $this->requireLogin();

        $recipeId = $this->intInput("recipe_id");
        $newServings = $this->intInput("servings");

        if (
            $recipeId <= 0 ||
            $newServings < SERVING_MIN ||
            $newServings > SERVING_MAX
        ) {
            $this->json(["error" => "Invalid parameters."], 400);
        }

        $recipe = $this->recipeModel->findById($recipeId);
        if (!$recipe) {
            $this->json(["error" => "Recipe not found."], 404);
        }

        $originalServings = (int) $recipe["servings"];
        $ingredients = $this->ingModel->findByRecipe($recipeId);

        // Scale from original recipe servings to requested servings
        $scaled = array_map(function (array $ing) use (
            $originalServings,
            $newServings,
        ): array {
            $ing["scaled_quantity"] = scale_quantity(
                $ing["quantity"],
                $originalServings,
                $newServings,
            );
            return $ing;
        }, $ingredients);

        $this->json([
            "original_servings" => $originalServings,
            "new_servings" => $newServings,
            "ingredients" => $scaled,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PDF DOWNLOAD
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /recipes/download/{id} */
    public function download(string $id = "0"): void
    {
        $this->requireCustomer();
        $recipeId = $this->resolveId($id);

        $userId = $this->userId();
        $permissions = $this->subModel->getUserPermissions($userId);

        if (!$permissions["can_download"]) {
            $this->warning(
                "PDF downloads require a Basic or Premium subscription.",
            );
            $this->redirectTo(url("subscriptions"));
        }

        $recipe = $this->recipeModel->getWithDetails($recipeId);
        if (!$recipe || $recipe["status"] !== "published") {
            $this->abort404("Recipe not found.");
        }

        if ($recipe["is_premium"] && !$permissions["can_access_premium"]) {
            $this->warning(
                "Premium subscription required to download this recipe.",
            );
            $this->redirectTo(url("subscriptions"));
        }

        // Get requested servings from URL parameter (default to original)
        $requestedServings = (int) ($_GET["servings"] ?? $recipe["servings"]);
        $requestedServings = max(1, min(50, $requestedServings)); // Clamp between 1 and 50

        // Scale ingredients based on requested servings
        if ($requestedServings != $recipe["servings"]) {
            $originalServings = (int) $recipe["servings"];
            foreach ($recipe["ingredients"] as &$ing) {
                $ing["quantity"] = scale_quantity($ing["quantity"], $originalServings, $requestedServings);
            }
            $recipe["servings"] = $requestedServings;
        }

        // Log the download (skip if already downloaded today)
        if (!$this->downloadModel->hasDownloadedToday($userId, $recipeId)) {
            $this->downloadModel->logDownload($userId, $recipeId);
        }

        // Deliver a print-optimised HTML page that the user saves as PDF via Ctrl+P
        $this->servePrintPage($recipe);
    }

    private function servePrintPage(array $recipe): never
    {
        header("Content-Type: text/html; charset=UTF-8");
        header(
            'Content-Disposition: inline; filename="' .
                slugify($recipe["title"]) .
                '.html"',
        );

        $totalTime = (int) $recipe["prep_time"] + (int) $recipe["cook_time"];
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($recipe["title"]) ?></title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'Segoe UI',Arial,sans-serif; color:#222; padding:2rem; max-width:800px; margin:0 auto; }
  h1 { font-size:1.8rem; margin-bottom:.4rem; color:#2d6a4f; }
  .meta { color:#666; font-size:.9rem; margin-bottom:1.2rem; display:flex; gap:1.5rem; flex-wrap:wrap; }
  .meta span { display:flex; align-items:center; gap:.3rem; }
  .description { background:#f8f9fa; padding:1rem; border-left:4px solid #2d6a4f; margin-bottom:1.5rem; font-style:italic; }
  h2 { font-size:1.1rem; text-transform:uppercase; letter-spacing:.05em; color:#2d6a4f;
       border-bottom:2px solid #2d6a4f; padding-bottom:.3rem; margin:1.5rem 0 .8rem; }
  .ingredients { list-style:none; columns:2; gap:1rem; }
  .ingredients li { padding:.25rem 0; border-bottom:1px dotted #ddd; break-inside:avoid; }
  .ingredients li strong { color:#333; }
  .step { display:flex; gap:1rem; margin-bottom:1rem; }
  .step-num { width:32px; height:32px; background:#2d6a4f; color:#fff; border-radius:50%;
              display:flex; align-items:center; justify-content:center; font-weight:bold; flex-shrink:0; }
  .step-body { flex:1; }
  .tip { background:#fff9e6; border-left:3px solid #f4a261; padding:.5rem .8rem; margin-top:.4rem;
         font-size:.85rem; color:#555; border-radius:0 4px 4px 0; }
  .footer { margin-top:2rem; text-align:center; font-size:.8rem; color:#aaa; border-top:1px solid #eee; padding-top:1rem; }
  @media print {
    body { padding:.5rem; }
    @page { margin:1.5cm; }
    .no-print { display:none; }
  }
</style>
</head>
<body>
<div class="no-print" style="background:#2d6a4f;color:#fff;padding:.8rem 1rem;margin-bottom:1.5rem;border-radius:6px;display:flex;justify-content:space-between;align-items:center;">
  <span>📄 Print or save as PDF using your browser's print function (Ctrl+P)</span>
  <button onclick="window.print()" style="background:#fff;color:#2d6a4f;border:none;padding:.4rem .8rem;border-radius:4px;cursor:pointer;font-weight:bold;">🖨️ Print / Save PDF</button>
</div>

<h1><?= e($recipe["title"]) ?></h1>
<div class="meta">
  <span>📂 <?= e($recipe["category_name"]) ?></span>
  <span>⏱️ Prep: <?= format_duration((int) $recipe["prep_time"]) ?></span>
  <span>🔥 Cook: <?= format_duration((int) $recipe["cook_time"]) ?></span>
  <span>⏰ Total: <?= format_duration($totalTime) ?></span>
  <span>👤 Serves: <?= e($recipe["servings"]) ?></span>
  <?php if ($recipe["calories"]): ?><span>🥗 Energy: <?= e(
    $recipe["calories"],
) ?> kcal/serving</span><?php endif; ?>
</div>

<div class="description"><?= e($recipe["description"]) ?></div>

<h2>🛒 Ingredients</h2>
<ul class="ingredients">
<?php foreach ($recipe["ingredients"] as $ing): ?>
  <li><strong><?= e($ing["quantity"]) ?> <?= e(
     $ing["unit"] ?? "",
 ) ?></strong> <?= e($ing["name"]) ?></li>
<?php endforeach; ?>
</ul>

<h2>👨‍🍳 Method</h2>
<?php foreach ($recipe["procedures"] as $step): ?>
<div class="step">
  <div class="step-num"><?= (int) $step["step_number"] ?></div>
  <div class="step-body">
    <p><?= e($step["instruction"]) ?></p>
    <?php if (!empty($step["tip"])): ?>
      <div class="tip">💡 <em><?= e($step["tip"]) ?></em></div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<div class="footer">
  Generated by <?= APP_NAME ?> &bull; <?= date("d F Y") ?> &bull; <?= APP_URL ?>
</div>
<script>setTimeout(()=>window.print(),800);</script>
</body></html>
        <?php
        echo ob_get_clean();
        exit();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN – RECIPE LIST
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /recipes/adminIndex */
    public function adminIndex(): void
    {
        $this->requireAdmin();

        $result = $this->recipeModel->getPaginatedAdmin(
            $this->currentPage(),
            ADMIN_ITEMS_PER_PAGE,
            $this->query("search"),
            $this->intInput("category"),
            $this->query("status"),
        );

        $this->view("admin/recipes/index", [
            "pageTitle" => "Recipe Management",
            "rows" => $result["rows"],
            "pager" => $result["pager"],
            "categories" => $this->categoryModel->getDropdownList(),
            "stats" => $this->recipeModel->getStats(),
            "search" => $this->query("search"),
            "category" => $this->intInput("category"),
            "status" => $this->query("status"),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN – CREATE
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /recipes/create  |  POST /recipes/store */
    public function create(): void
    {
        $this->requireAdmin();

        $this->view("admin/recipes/create", [
            "pageTitle" => "Add New Recipe",
            "categories" => $this->categoryModel->getDropdownList(),
        ]);
    }

    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $errors = $this->validateRecipeInput();
        if (!empty($errors)) {
            foreach ($errors as $e) {
                $this->error($e);
            }
            Session::setOldInput($_POST);
            $this->redirectTo(url("recipes/create"));
        }

        // Handle image upload
        $imageName = null;
        if (!empty($_FILES["image"]["name"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {
            $upload = upload_image($_FILES["image"], RECIPE_IMG_PATH);
            if (!$upload["success"]) {
                $this->error("Image upload failed: " . $upload["error"]);
                Session::setOldInput($_POST);
                $this->redirectTo(url("recipes/create"));
            }
            $imageName = $upload["filename"];
        }

        $status = $this->post("status", "published");
        
        $recipeData = [
            "admin_id" => $this->userId(),
            "category_id" => $this->intInput("category_id"),
            "title" => $this->post("title"),
            "slug" => generate_unique_slug($this->post("title"), "recipes"),
            "description" => $_POST["description"] ?? "",
            "prep_time" => $this->intInput("prep_time"),
            "cook_time" => $this->intInput("cook_time"),
            "servings" => $this->intInput("servings", 2),
            "difficulty" => $this->post("difficulty", "medium"),
            "calories" => $this->intInput("calories") ?: null,
            "is_premium" => (int) isset($_POST["is_premium"]),
            "status" => $status,
            "image" => $imageName,
        ];

        $recipeId = $this->recipeModel->createRecipe($recipeData);

        if (!$recipeId) {
            $this->error("Failed to save recipe to database. Please try again.");
            Session::setOldInput($_POST);
            $this->redirectTo(url("recipes/create"));
        }

        // Sync ingredients and procedures
        $this->syncSubRecords($recipeId);

        $statusMsg = $status === 'published' ? 'published' : 'saved as draft';
        $this->success(
            'Recipe "' . e($this->post("title")) . '" ' . $statusMsg . ' successfully.',
        );
        $this->redirectTo(url("recipes/adminIndex"));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN – EDIT / UPDATE
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /recipes/edit/{id} */
    public function edit(string $id = "0"): void
    {
        $this->requireAdmin();
        $recipeId = $this->resolveId($id);

        $recipe = $this->recipeModel->getWithDetails($recipeId);
        if (!$recipe) {
            $this->abort404("Recipe not found.");
        }

        $this->view("admin/recipes/edit", [
            "pageTitle" => "Edit: " . e($recipe["title"]),
            "recipe" => $recipe,
            "categories" => $this->categoryModel->getDropdownList(),
        ]);
    }

    /** POST /recipes/update/{id} */
    public function update(string $id = "0"): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $recipeId = $this->resolveId($id);

        $recipe = $this->recipeModel->findById($recipeId);
        if (!$recipe) {
            $this->abort404();
        }

        $errors = $this->validateRecipeInput();
        if (!empty($errors)) {
            foreach ($errors as $e) {
                $this->error($e);
            }
            $this->redirectTo(url("recipes/edit/" . $recipeId));
        }

        $updateData = [
            "category_id" => $this->intInput("category_id"),
            "title" => $this->post("title"),
            "slug" => generate_unique_slug(
                $this->post("title"),
                "recipes",
                "slug",
                $recipeId,
            ),
            "description" => $_POST["description"] ?? "",
            "prep_time" => $this->intInput("prep_time"),
            "cook_time" => $this->intInput("cook_time"),
            "servings" => $this->intInput("servings", 2),
            "difficulty" => $this->post("difficulty", "medium"),
            "calories" => $this->intInput("calories") ?: null,
            "is_premium" => (int) isset($_POST["is_premium"]),
            "status" => $this->post("status", "published"),
        ];

        if (!empty($_FILES["image"]["name"])) {
            $upload = upload_image(
                $_FILES["image"],
                RECIPE_IMG_PATH,
                $recipe["image"],
            );
            if (!$upload["success"]) {
                $this->error($upload["error"]);
                $this->redirectTo(url("recipes/edit/" . $recipeId));
            }
            $updateData["image"] = $upload["filename"];
        }

        $this->recipeModel->updateRecipe($recipeId, $updateData);
        $this->syncSubRecords($recipeId);

        $this->success("Recipe updated successfully.");
        $this->redirectTo(url("recipes/adminIndex"));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN – DELETE
    // ══════════════════════════════════════════════════════════════════════════

    /** POST /recipes/delete/{id} */
    public function delete(string $id = "0"): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $recipeId = $this->resolveId($id);

        $recipe = $this->recipeModel->findById($recipeId);
        if (!$recipe) {
            $this->abort404();
        }

        // Archive instead of hard delete to preserve download/payment history
        $this->recipeModel->updateStatus($recipeId, "archived");

        $this->success(
            'Recipe "' . e($recipe["title"]) . '" has been archived.',
        );
        $this->redirectTo(url("recipes/adminIndex"));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    private function validateRecipeInput(): array
    {
        $errors = [];
        if (empty(trim($this->post("title")))) {
            $errors[] = "Recipe title is required.";
        }
        if (empty(trim($_POST["description"] ?? ""))) {
            $errors[] = "Description is required.";
        }
        if ($this->intInput("category_id") <= 0) {
            $errors[] = "Please select a category.";
        }
        if (
            !in_array(
                $this->post("difficulty"),
                ["easy", "medium", "hard"],
                true,
            )
        ) {
            $errors[] = "Please select a valid difficulty.";
        }
        if ($this->intInput("servings") < 1) {
            $errors[] = "Servings must be at least 1.";
        }
        return $errors;
    }

    private function syncSubRecords(int $recipeId): void
    {
        try {
            // Ingredients arrive as ingredients[0][name], ingredients[0][quantity], etc.
            if (!empty($_POST["ingredients"]) && is_array($_POST["ingredients"])) {
                $this->ingModel->syncIngredients($recipeId, $_POST["ingredients"]);
            }

            // Procedures arrive as procedures[0][instruction], procedures[0][tip], etc.
            if (!empty($_POST["procedures"]) && is_array($_POST["procedures"])) {
                $this->procModel->syncProcedures($recipeId, $_POST["procedures"]);
            }
        } catch (Exception $e) {
            // Log error but don't fail the recipe creation
            error_log("Failed to sync recipe sub-records: " . $e->getMessage());
        }
    }
}
