<?php

/**
 * AdminController – Admin Dashboard & User Management
 * =====================================================
 * Routes under /admin/*
 *   dashboard   – aggregated stats overview
 *   users       – paginated customer list
 *   viewUser    – single customer profile
 *   toggleStatus – activate / ban customer
 *   sendNotification – broadcast notification to a user
 */

declare(strict_types=1);

class AdminController extends BaseController
{
    private UserModel         $userModel;
    private RecipeModel       $recipeModel;
    private SubscriptionModel $subModel;
    private PaymentModel      $paymentModel;
    private NotificationModel $notifModel;
    private MealPlanModel     $mealPlanModel;
    private RecipeDownloadModel $downloadModel;

    public function __construct()
    {
        $this->userModel     = new UserModel();
        $this->recipeModel   = new RecipeModel();
        $this->subModel      = new SubscriptionModel();
        $this->paymentModel  = new PaymentModel();
        $this->notifModel    = new NotificationModel();
        $this->mealPlanModel = new MealPlanModel();
        $this->downloadModel = new RecipeDownloadModel();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DASHBOARD
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /admin/dashboard */
    public function dashboard(): void
    {
        $this->requireAdmin();

        // Expire overdue subscriptions on every admin login
        $this->subModel->expireOverdue();

        $payStats  = $this->paymentModel->getStats();
        $monthly   = $this->paymentModel->getMonthlyRevenue();
        $byPlan    = $this->subModel->getCountByPlan();

        // Ensure monthlyRevenue is a simple indexed array of float values
        $monthlyRevenue = [];
        if (is_array($monthly)) {
            // Re-index to ensure 0-based numeric keys
            $monthly = array_values($monthly);
            foreach ($monthly as $val) {
                // Force to float, ignore any non-scalar
                if (is_scalar($val) && is_numeric($val)) {
                    $monthlyRevenue[] = (float)$val;
                } else {
                    $monthlyRevenue[] = 0.0;
                }
            }
        } else {
            // Default to 12 months of zeros
            $monthlyRevenue = array_fill(0, 12, 0.0);
        }

        // Ensure byPlan data is properly formatted for chart
        $planNames = [];
        $planCounts = [];
        if (is_array($byPlan)) {
            foreach ($byPlan as $row) {
                if (is_array($row)) {
                    $name = isset($row['name']) && is_scalar($row['name']) ? (string)$row['name'] : '';
                    $count = isset($row['subscriber_count']) && is_scalar($row['subscriber_count']) ? (int)$row['subscriber_count'] : 0;
                    $planNames[] = $name;
                    $planCounts[] = $count;
                }
            }
        }
        // Re-index to ensure 0-based numeric keys
        $planNames = array_values($planNames);
        $planCounts = array_values($planCounts);

        $this->view('admin/dashboard', [
            'pageTitle'      => 'Admin Dashboard – ' . APP_NAME,
            'userStats'      => $this->userModel->getStats(),
            'recipeStats'    => $this->recipeModel->getStats(),
            'subStats'       => $this->subModel->getStats(),
            'payStats'       => $payStats,
            'mealPlanStats'  => $this->mealPlanModel->getStats(),
            'downloadStats'  => $this->downloadModel->getStats(),
            'recentPayments' => $this->paymentModel->getPaginatedAdmin(1, 5)['rows'],
            'recentUsers'    => $this->userModel->getPaginatedAdmin(1, 5)['rows'],
            'topRecipes'     => $this->recipeModel->getTopViewed(5),
            'popularDownloads'=> $this->downloadModel->getPopular(5),
            'monthlyRevenue' => $monthlyRevenue,
            'byPlan'         => $byPlan,
            'planNames'      => $planNames,
            'planCounts'     => $planCounts,
            'byCategory'     => $this->recipeModel->getCountByCategory(),
            'byProvider'     => $this->paymentModel->getRevenueByProvider(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // USER MANAGEMENT
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /admin/users */
    public function users(): void
    {
        $this->requireAdmin();

        $search = $this->query('search');
        $status = $this->query('status');
        $page   = $this->currentPage();

        $result = $this->userModel->getPaginatedAdmin($page, ADMIN_ITEMS_PER_PAGE, $search, $status);

        $this->view('admin/users/index', [
            'pageTitle' => 'Customer Management',
            'rows'      => $result['rows'],
            'pager'     => $result['pager'],
            'search'    => $search,
            'status'    => $status,
            'stats'     => $this->userModel->getStats(),
        ]);
    }

    /** GET /admin/viewUser/{id} */
    public function viewUser(string $id = '0'): void
    {
        $this->requireAdmin();
        $userId = $this->resolveId($id);

        $user = $this->userModel->findById($userId);
        if (!$user) { $this->abort404('Customer not found.'); }

        $this->view('admin/users/view', [
            'pageTitle'    => 'Customer: ' . e($user['first_name'] . ' ' . $user['last_name']),
            'user'         => $user,
            'subscription' => $this->subModel->getActiveSubscription($userId),
            'subHistory'   => $this->subModel->getSubscriptionHistory($userId),
            'payments'     => $this->paymentModel->getByUser($userId, 1, 5)['rows'],
            'downloads'    => $this->downloadModel->getByUser($userId, 1, 5)['rows'],
            'mealPlans'    => $this->mealPlanModel->findByUser($userId),
        ]);
    }

    /** POST /admin/toggleUserStatus */
    public function toggleUserStatus(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $userId    = $this->intInput('user_id');
        $newStatus = $this->post('status');

        if (!in_array($newStatus, ['active', 'inactive', 'banned'], true)) {
            $this->error('Invalid status value.');
            $this->redirectTo(url('admin/users'));
        }

        $user = $this->userModel->findById($userId);
        if (!$user) {
            $this->error('User not found.');
            $this->redirectTo(url('admin/users'));
        }

        $this->userModel->updateStatus($userId, $newStatus);
        $this->success("Account status updated to \"{$newStatus}\".");
        $this->redirectTo(url('admin/viewUser/' . $userId));
    }

    /** POST /admin/sendNotification */
    public function sendNotification(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $userId  = $this->intInput('user_id');
        $title   = Security::cleanString($this->post('title'));
        $message = Security::cleanTextarea($this->post('message'));
        $type    = $this->post('type', 'info');

        if ($userId <= 0 || empty($title) || empty($message)) {
            $this->error('User, title and message are all required.');
            $this->back(url('admin/users'));
        }

        $this->notifModel->notify([
            'user_id'  => $userId,
            'title'    => $title,
            'message'  => $message,
            'type'     => in_array($type, ['info','success','warning','error'], true) ? $type : 'info',
            'category' => 'system',
        ]);

        $this->success('Notification sent successfully.');
        $this->redirectTo(url('admin/viewUser/' . $userId));
    }
}
