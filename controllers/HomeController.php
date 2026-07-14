<?php

/**
 * HomeController – Public Landing Page
 * ======================================
 * Serves the marketing/landing page with featured recipes,
 * category highlights, subscription plan cards, and
 * redirects for already-authenticated users.
 */

declare(strict_types=1);

class HomeController extends BaseController
{
    private RecipeModel       $recipeModel;
    private CategoryModel     $categoryModel;
    private SubscriptionModel $subModel;

    public function __construct()
    {
        $this->recipeModel   = new RecipeModel();
        $this->categoryModel = new CategoryModel();
        $this->subModel      = new SubscriptionModel();
    }

    // ── GET / ─────────────────────────────────────────────────────────────────
    public function index(): void
    {
        // Redirect authenticated users straight to their dashboard
        if (Session::isLoggedIn()) {
            $this->redirectTo(Session::isAdmin() ? url('admin/dashboard') : url('customer/dashboard'));
        }

        // Get popular recipes to display on homepage
        $allPopular = $this->recipeModel->getPopular(10);
        $popularRecipes = array_slice($allPopular, 0, 4);
        $hasMoreRecipes = count($allPopular) > 4;

        $this->view('home/index', [
            'pageTitle'      => APP_NAME . ' – ' . APP_TAGLINE,
            'featured'       => $popularRecipes,
            'hasMoreRecipes' => $hasMoreRecipes,
            'popular'        => $this->recipeModel->getPopular(4),
            'categories'     => $this->categoryModel->getAllWithRecipeCount(),
            'plans'          => $this->subModel->getAllPlans(),
        ]);
    }
}
