<?php

/**
 * Router
 * =======
 * Parses the incoming URL and maps it to the correct Controller
 * and action. Falls back to a 404 page when no route is matched.
 *
 * URL pattern:  /controller/action/param1/param2/…
 *
 * Examples:
 *   /                          → HomeController@index
 *   /auth/login                → AuthController@login
 *   /admin/dashboard           → AdminController@dashboard
 *   /recipes/view/42           → RecipeController@view  (id=42)
 */

declare(strict_types=1);

class Router
{
    /** @var string Parsed controller name */
    private string $controller = 'HomeController';

    /** @var string Parsed action (method) name */
    private string $action = 'index';

    /** @var array<string> URL segments after controller/action */
    private array $params = [];

    // ── Route Table ──────────────────────────────────────────────────────────
    /**
     * Explicit route map: 'segment' => 'ControllerClass'
     * Any segment not listed here falls through to dynamic resolution.
     */
    private array $routes = [
        ''           => 'HomeController',
        'home'       => 'HomeController',
        'auth'       => 'AuthController',
        'admin'      => 'AdminController',
        'customer'   => 'CustomerController',
        'recipes'    => 'RecipeController',
        'categories' => 'CategoryController',
        'ingredients'=> 'IngredientController',
        'mealplans'  => 'MealPlanController',
        'favourites' => 'FavouriteController',
        'subscriptions'=> 'SubscriptionController',
        'payments'   => 'PaymentController',
        'notifications'=> 'NotificationController',
        'profile'    => 'ProfileController',
        'reports'    => 'ReportController',
    ];

    // ── Action Aliases ────────────────────────────────────────────────────────
    /**
     * Map action names to actual method names for specific controllers.
     * Used to avoid conflicts with inherited methods.
     */
    private array $actionAliases = [
        'RecipeController' => [
            'view' => 'viewRecipe',
        ],
        'MealPlanController' => [
            'view' => 'viewMealPlan',
        ],
        'PaymentController' => [
            'view' => 'viewPayment',
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Parse the URL query-string value set by .htaccess (url=…).
     */
    public function parseUrl(): void
    {
        $rawUrl = isset($_GET['url'])
            ? rtrim(filter_var($_GET['url'], FILTER_SANITIZE_URL), '/')
            : '';

        $segments = explode('/', $rawUrl);

        // Segment 0 → controller
        $controllerKey = strtolower($segments[0] ?? '');
        if (isset($this->routes[$controllerKey])) {
            $this->controller = $this->routes[$controllerKey];
        } else {
            $this->controller = 'HomeController';
        }

        // Segment 1 → action
        $rawAction = isset($segments[1]) && $segments[1] !== ''
            ? $this->sanitizeMethodName($segments[1])
            : 'index';

        // Apply action aliases if defined for this controller
        if (isset($this->actionAliases[$this->controller][$rawAction])) {
            $this->action = $this->actionAliases[$this->controller][$rawAction];
        } else {
            $this->action = $rawAction;
        }

        // Remaining segments → params
        $this->params = array_slice($segments, 2);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve, instantiate and call the controller action.
     */
    public function dispatch(): void
    {
        $this->parseUrl();

        $controllerFile = ROOT_PATH . DS . 'controllers' . DS . $this->controller . '.php';

        if (!file_exists($controllerFile)) {
            $this->show404();
            return;
        }

        require_once $controllerFile;

        if (!class_exists($this->controller)) {
            $this->show404();
            return;
        }

        $controllerObj = new $this->controller();

        if (!method_exists($controllerObj, $this->action)) {
            $this->show404();
            return;
        }

        // Call action with URL params as individual arguments
        call_user_func_array([$controllerObj, $this->action], $this->params);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Allow only alphanumeric + underscore method names to prevent
     * arbitrary method invocation (security).
     */
    private function sanitizeMethodName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $name);
    }

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render a clean 404 response.
     */
    private function show404(): void
    {
        http_response_code(404);
        $view = ROOT_PATH . DS . 'views' . DS . 'layouts' . DS . '404.php';
        if (file_exists($view)) {
            require_once $view;
        } else {
            echo '<h1>404 – Page Not Found</h1>';
        }
        exit;
    }
}
