<?php

/**
 * FavouriteController – Saved / Bookmarked Recipes
 * ==================================================
 * index   – paginated favourites list
 * toggle  – AJAX/POST: add or remove a favourite
 * remove  – POST: explicit remove (from list page)
 */

declare(strict_types=1);

class FavouriteController extends BaseController
{
    private FavouriteModel    $favModel;
    private SubscriptionModel $subModel;

    public function __construct()
    {
        $this->favModel = new FavouriteModel();
        $this->subModel = new SubscriptionModel();
    }

    /** GET /favourites/index */
    public function index(): void
    {
        $this->requireCustomer();
        $userId = $this->userId();

        $result = $this->favModel->findByUser($userId, $this->currentPage());

        $this->view('customer/favourites/index', [
            'pageTitle'   => 'My Favourite Recipes',
            'rows'        => $result['rows'],
            'pager'       => $result['pager'],
            'permissions' => $this->subModel->getUserPermissions($userId),
        ]);
    }

    /**
     * POST /favourites/toggle
     * Works as both AJAX (returns JSON) and standard form POST (redirects).
     */
    public function toggle(): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();

        $recipeId = $this->intInput('recipe_id');

        if ($recipeId <= 0) {
            if ($this->isAjax()) {
                $this->json(['error' => 'Invalid recipe.'], 422);
            }
            $this->error('Invalid recipe.');
            $this->back(url('recipes/index'));
        }

        $added = $this->favModel->toggle($this->userId(), $recipeId);
        $count = $this->favModel->countByRecipe($recipeId);

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'added'   => $added,
                'count'   => $count,
                'message' => $added ? 'Added to favourites.' : 'Removed from favourites.',
            ]);
        }

        $added
            ? $this->success('Recipe added to your favourites.')
            : $this->info('Recipe removed from your favourites.');

        $this->back(url('recipes/index'));
    }

    /** POST /favourites/remove/{recipeId} */
    public function remove(string $recipeId = '0'): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();

        $rid = $this->resolveId($recipeId);
        $this->favModel->remove($this->userId(), $rid);

        $this->success('Removed from your favourites.');
        $this->redirectTo(url('favourites/index'));
    }
}
