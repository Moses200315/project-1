<?php

/**
 * ReportController – Admin Analytics & Reports
 * ==============================================
 * index       – overview dashboard (charts + summary)
 * revenue     – monthly revenue breakdown
 * recipes     – recipe popularity + download stats
 * users       – user growth + subscription stats
 * exportCsv   – download any report as CSV
 */

declare(strict_types=1);

class ReportController extends BaseController
{
    private PaymentModel        $paymentModel;
    private SubscriptionModel   $subModel;
    private RecipeModel         $recipeModel;
    private UserModel           $userModel;
    private RecipeDownloadModel $downloadModel;

    public function __construct()
    {
        $this->paymentModel  = new PaymentModel();
        $this->subModel      = new SubscriptionModel();
        $this->recipeModel   = new RecipeModel();
        $this->userModel     = new UserModel();
        $this->downloadModel = new RecipeDownloadModel();
    }

    /** GET /reports/index */
    public function index(): void
    {
        $this->requireAdmin();

        $year = (int) $this->query('year', (string) date('Y'));

        $this->view('admin/reports/index', [
            'pageTitle'      => 'Reports & Analytics',
            'year'           => $year,
            'payStats'       => $this->paymentModel->getStats(),
            'subStats'       => $this->subModel->getStats(),
            'recipeStats'    => $this->recipeModel->getStats(),
            'userStats'      => $this->userModel->getStats(),
            'downloadStats'  => $this->downloadModel->getStats(),
            'monthlyRevenue' => array_values($this->paymentModel->getMonthlyRevenue($year)),
            'byPlan'         => $this->subModel->getCountByPlan(),
            'byProvider'     => $this->paymentModel->getRevenueByProvider(),
            'byCategory'     => $this->recipeModel->getCountByCategory(),
            'topViewed'      => $this->recipeModel->getTopViewed(10),
            'topDownloads'   => $this->downloadModel->getPopular(10),
        ]);
    }

    /** GET /reports/revenue */
    public function revenue(): void
    {
        $this->requireAdmin();
        $year = (int) $this->query('year', (string) date('Y'));

        $monthly  = $this->paymentModel->getMonthlyRevenue($year);
        $payments = $this->paymentModel->getPaginatedAdmin($this->currentPage(), ADMIN_ITEMS_PER_PAGE)['rows'];

        $this->view('admin/reports/revenue', [
            'pageTitle'      => 'Revenue Report – ' . $year,
            'year'           => $year,
            'monthlyRevenue' => array_values($monthly),
            'stats'          => $this->paymentModel->getStats(),
            'byProvider'     => $this->paymentModel->getRevenueByProvider(),
            'payments'       => $payments,
        ]);
    }

    /** GET /reports/recipes */
    public function recipes(): void
    {
        $this->requireAdmin();

        $this->view('admin/reports/recipes', [
            'pageTitle'    => 'Recipe Analytics',
            'stats'        => $this->recipeModel->getStats(),
            'topViewed'    => $this->recipeModel->getTopViewed(20),
            'topDownloads' => $this->downloadModel->getPopular(20),
            'byCategory'   => $this->recipeModel->getCountByCategory(),
            'downloadStats'=> $this->downloadModel->getStats(),
        ]);
    }

    /** GET /reports/users */
    public function users(): void
    {
        $this->requireAdmin();

        $this->view('admin/reports/users', [
            'pageTitle'  => 'User & Subscription Report',
            'userStats'  => $this->userModel->getStats(),
            'subStats'   => $this->subModel->getStats(),
            'byPlan'     => $this->subModel->getCountByPlan(),
            'recentSubs' => $this->subModel->getPaginatedAdmin(1, 20)['rows'],
        ]);
    }

    /** GET /reports/exportCsv?type=revenue|subscriptions|recipes|users */
    public function exportCsv(): never
    {
        $this->requireAdmin();

        $type = $this->query('type', 'revenue');
        $year = (int) $this->query('year', (string) date('Y'));

        [$filename, $headers, $rows] = match ($type) {
            'revenue'       => $this->buildRevenueCsv($year),
            'subscriptions' => $this->buildSubscriptionsCsv(),
            'recipes'       => $this->buildRecipesCsv(),
            'users'         => $this->buildUsersCsv(),
            default         => $this->buildRevenueCsv($year),
        };

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM for Excel compatibility
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    // ── CSV Builders ──────────────────────────────────────────────────────────

    private function buildRevenueCsv(int $year): array
    {
        $rows    = $this->paymentModel->getPaginatedAdmin(1, 9999)['rows'];
        $headers = ['Ref','User','Plan','Amount','Provider','Status','Date'];
        $data    = array_map(fn($r) => [
            $r['transaction_ref'],
            $r['user_name'],
            $r['plan_name'] ?? 'N/A',
            $r['amount'],
            $r['provider'],
            $r['status'],
            $r['created_at'],
        ], $rows);
        return ['mealkit_revenue_' . $year . '.csv', $headers, $data];
    }

    private function buildSubscriptionsCsv(): array
    {
        $rows    = $this->subModel->getPaginatedAdmin(1, 9999)['rows'];
        $headers = ['User','Email','Plan','Price','Status','Start','End'];
        $data    = array_map(fn($r) => [
            $r['user_name'],
            $r['user_email'],
            $r['plan_name'],
            $r['plan_price'],
            $r['status'],
            $r['starts_at'],
            $r['ends_at'],
        ], $rows);
        return ['mealkit_subscriptions_' . date('Ymd') . '.csv', $headers, $data];
    }

    private function buildRecipesCsv(): array
    {
        $rows    = $this->recipeModel->getTopViewed(9999);
        $headers = ['Title','Category','Views','Status'];
        $data    = array_map(fn($r) => [
            $r['title'], $r['category_name'], $r['views'], $r['status'],
        ], $rows);
        return ['mealkit_recipes_' . date('Ymd') . '.csv', $headers, $data];
    }

    private function buildUsersCsv(): array
    {
        $rows    = $this->userModel->getPaginatedAdmin(1, 9999)['rows'];
        $headers = ['Name','Email','Phone','Plan','Status','Joined'];
        $data    = array_map(fn($r) => [
            $r['first_name'] . ' ' . $r['last_name'],
            $r['email'],
            $r['phone'] ?? '',
            $r['plan_name'] ?? 'Free',
            $r['status'],
            $r['created_at'],
        ], $rows);
        return ['mealkit_users_' . date('Ymd') . '.csv', $headers, $data];
    }
}
