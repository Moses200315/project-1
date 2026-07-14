<?php

/**
 * CustomerController – Customer Dashboard
 * =========================================
 * Serves the personalised customer home screen showing:
 *   - Subscription status & quick stats
 *   - Recent / favourite recipes
 *   - Active meal plan preview
 *   - Upcoming plan expiry alerts
 *   - Recent notifications
 */

declare(strict_types=1);

class CustomerController extends BaseController
{
    private RecipeModel       $recipeModel;
    private SubscriptionModel $subModel;
    private FavouriteModel    $favModel;
    private MealPlanModel     $mealPlanModel;
    private NotificationModel $notifModel;
    private RecipeDownloadModel $downloadModel;

    public function __construct()
    {
        $this->recipeModel   = new RecipeModel();
        $this->subModel      = new SubscriptionModel();
        $this->favModel      = new FavouriteModel();
        $this->mealPlanModel = new MealPlanModel();
        $this->notifModel    = new NotificationModel();
        $this->downloadModel = new RecipeDownloadModel();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /customer/dashboard */
    public function dashboard(): void
    {
        $this->requireCustomer();

        $userId      = $this->userId();
        $permissions = $this->subModel->getUserPermissions($userId);
        $activeSub   = $this->subModel->getActiveSubscription($userId);

        // Subscription expiry warning (< 5 days remaining)
        if ($activeSub && ($permissions['days_remaining'] <= 5) && ($permissions['days_remaining'] > 0)) {
            $this->notifModel->notifySubscriptionExpiringSoon(
                $userId,
                $permissions['plan_name'],
                $permissions['days_remaining']
            );
        }

        // Active meal plan
        $activePlan = $this->mealPlanModel->getActivePlan($userId);
        $planSlots  = $activePlan
            ? $this->mealPlanModel->getWithRecipes((int) $activePlan['id'], $userId)
            : null;

        $this->view('customer/dashboard', [
            'pageTitle'      => 'My Dashboard – ' . APP_NAME,
            'permissions'    => $permissions,
            'activeSub'      => $activeSub,
            'recentRecipes'  => $this->recipeModel->getRecent(6),
            'featuredRecipes'=> $this->recipeModel->getRecent(4),
            'favourites'     => $this->favModel->findByUser($userId, 1, 4)['rows'],
            'favCount'       => $this->favModel->count(['user_id' => $userId]),
            'mealPlans'      => $this->mealPlanModel->findByUser($userId),
            'activePlan'     => $planSlots,
            'notifications'  => $this->notifModel->getForUser($userId, 5),
            'unreadCount'    => $this->notifModel->countUnread($userId),
            'downloadCount'  => $this->downloadModel->countByUser($userId),
        ]);
    }

    /** Alias so /customer/index also works */
    public function index(): void
    {
        $this->dashboard();
    }
}
