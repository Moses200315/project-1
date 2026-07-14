<?php

/**
 * MealKit – Application Configuration (Example)
 * ============================================
 * This is an example configuration file for setting up MealKit.
 * Copy this file to config.php and update the values for your environment.
 *
 * IMPORTANT: Never commit config.php to version control as it contains
 * sensitive credentials. Use this example file as a template.
 */

declare(strict_types=1);

// ── Guard: prevent direct browser access ─────────────────────────────────────
defined('ROOT_PATH') or die('Direct access not permitted.');

// ══════════════════════════════════════════════════════════════════════════════
// APPLICATION
// ══════════════════════════════════════════════════════════════════════════════

define('APP_NAME',        'MealKit');
define('APP_TAGLINE',     'Subscription-Based Recipe & Meal Planning');
define('APP_VERSION',     '1.0.0');
define('APP_ENV',         'development');          // 'development' | 'production'
define('APP_DEBUG',       APP_ENV === 'development');
define('APP_TIMEZONE',    'Africa/Dar_es_Salaam');
define('APP_LOCALE',      'en_TZ');
define('APP_CHARSET',     'UTF-8');

// ── Base URL (no trailing slash) ──────────────────────────────────────────────
// Change to your domain in production: define('APP_URL', 'https://yourdomain.com');
define('APP_URL',         'http://localhost/mealkit');

// ══════════════════════════════════════════════════════════════════════════════
// DATABASE
// ══════════════════════════════════════════════════════════════════════════════

define('DB_HOST',         'localhost');
define('DB_PORT',         '3306');
define('DB_NAME',         'mealkit_db');
define('DB_USER',         'your_database_user');
define('DB_PASS',         'your_database_password');
define('DB_CHARSET',      'utf8mb4');
define('DB_COLLATION',    'utf8mb4_unicode_ci');

// ══════════════════════════════════════════════════════════════════════════════
// FILESYSTEM PATHS  (absolute, no trailing slash)
// ══════════════════════════════════════════════════════════════════════════════

define('CONFIG_PATH',     ROOT_PATH . DS . 'config');
define('CONTROLLERS_PATH',ROOT_PATH . DS . 'controllers');
define('MODELS_PATH',     ROOT_PATH . DS . 'models');
define('VIEWS_PATH',      ROOT_PATH . DS . 'views');
define('INCLUDES_PATH',   ROOT_PATH . DS . 'includes');
define('UPLOADS_PATH',    ROOT_PATH . DS . 'uploads');
define('ASSETS_PATH',     ROOT_PATH . DS . 'assets');
define('DATABASE_PATH',   ROOT_PATH . DS . 'database');

// Upload sub-directories
define('RECIPE_IMG_PATH', UPLOADS_PATH . DS . 'recipes');
define('PROFILE_IMG_PATH',UPLOADS_PATH . DS . 'profiles');
define('PDF_PATH',        UPLOADS_PATH . DS . 'pdfs');

// ══════════════════════════════════════════════════════════════════════════════
// PUBLIC URL PATHS  (relative to APP_URL, no trailing slash)
// ══════════════════════════════════════════════════════════════════════════════

define('ASSETS_URL',      APP_URL . '/assets');
define('UPLOADS_URL',     APP_URL . '/uploads');
define('RECIPE_IMG_URL',  UPLOADS_URL . '/recipes');
define('PROFILE_IMG_URL', UPLOADS_URL . '/profiles');
define('CSS_URL',         ASSETS_URL . '/css');
define('JS_URL',          ASSETS_URL . '/js');
define('IMG_URL',         ASSETS_URL . '/images');

// ══════════════════════════════════════════════════════════════════════════════
// SESSION
// ══════════════════════════════════════════════════════════════════════════════

define('SESSION_NAME',        'mealkit_sess');
define('SESSION_LIFETIME',    7200);               // seconds  (2 hours)
define('SESSION_REGEN_AFTER', 1800);               // regenerate ID every 30 min

// ══════════════════════════════════════════════════════════════════════════════
// SECURITY
// ══════════════════════════════════════════════════════════════════════════════

define('BCRYPT_COST',          12);                // bcrypt work factor
define('CSRF_TOKEN_LENGTH',    32);                // bytes → 64 hex chars
define('PASSWORD_MIN_LENGTH',  8);
define('MAX_LOGIN_ATTEMPTS',   5);                 // before lockout
define('LOGIN_LOCKOUT_SECS',   900);               // 15 minutes

// ══════════════════════════════════════════════════════════════════════════════
// FILE UPLOADS
// ══════════════════════════════════════════════════════════════════════════════

define('MAX_FILE_SIZE',      5 * 1024 * 1024);     // 5 MB in bytes

// Allowed MIME types for image uploads
define('ALLOWED_IMAGE_MIMES', serialize([
    'image/jpeg', 'image/jpg', 'image/png',
    'image/webp', 'image/gif',
]));

// Allowed file extensions (lowercase)
define('ALLOWED_IMAGE_EXTS', serialize([
    'jpg', 'jpeg', 'png', 'webp', 'gif',
]));

// Default placeholder images
define('DEFAULT_AVATAR',      'default.png');
define('DEFAULT_RECIPE_IMG',  'default-recipe.jpg');

// ══════════════════════════════════════════════════════════════════════════════
// PAGINATION
// ══════════════════════════════════════════════════════════════════════════════

define('ITEMS_PER_PAGE',        12);               // customer-facing grids
define('ADMIN_ITEMS_PER_PAGE',  20);               // admin table rows
define('NOTIFICATION_LIMIT',    50);               // max notifications to load

// ══════════════════════════════════════════════════════════════════════════════
// CURRENCY  (Tanzanian Shilling – change per locale)
// ══════════════════════════════════════════════════════════════════════════════

define('CURRENCY_CODE',   'TZS');
define('CURRENCY_SYMBOL', 'TSh');

// ══════════════════════════════════════════════════════════════════════════════
// MOBILE MONEY SANDBOX
// Replace sandbox values with live credentials in production.
// ══════════════════════════════════════════════════════════════════════════════

define('MOMO_MODE',           'sandbox');          // 'sandbox' | 'live'
define('MOMO_SANDBOX_URL',    'https://sandbox.momodemo.example.com/api/v1');
define('MOMO_LIVE_URL',       'https://api.momodemo.example.com/api/v1');
define('MOMO_API_KEY',        'YOUR_LIVE_API_KEY_HERE');
define('MOMO_MERCHANT_ID',    'YOUR_MERCHANT_ID_HERE');
define('MOMO_CURRENCY',       'TZS');
define('MOMO_CALLBACK_URL',   APP_URL . '/payments/callback');

// Supported Mobile Money providers (Tanzania)
define('MOMO_PROVIDERS', serialize([
    'Mpesa'      => 'M-Pesa',
    'TigoPesa'   => 'Tigo Pesa',
    'AirtelMoney'=> 'Airtel Money',
    'Halotel'    => 'Halotel Money',
]));

// Sandbox simulation success rate (0.0 – 1.0)
// e.g. 0.85 = 85% of sandbox transactions "succeed"
define('MOMO_SANDBOX_SUCCESS_RATE', 0.85);

// ══════════════════════════════════════════════════════════════════════════════
// PAYMENT METHODS
// ══════════════════════════════════════════════════════════════════════════════

// Supported payment methods
define('PAYMENT_METHODS', serialize([
    'mobile'     => 'Mobile Money',
]));

// ══════════════════════════════════════════════════════════════════════════════
// PDF  (uses FPDF – place fpdf.php in includes/fpdf/)
// ══════════════════════════════════════════════════════════════════════════════

define('PDF_LIB_PATH',    INCLUDES_PATH . DS . 'fpdf' . DS . 'fpdf.php');
define('PDF_FONT',        'Helvetica');
define('PDF_AUTHOR',      APP_NAME);
define('PDF_CREATOR',     APP_NAME . ' v' . APP_VERSION);

// ══════════════════════════════════════════════════════════════════════════════
// SERVING SIZE CALCULATOR
// ══════════════════════════════════════════════════════════════════════════════

define('SERVING_MIN', 1);
define('SERVING_MAX', 50);

// ══════════════════════════════════════════════════════════════════════════════
// RECIPE DIFFICULTY LABELS
// ══════════════════════════════════════════════════════════════════════════════

define('DIFFICULTY_LABELS', serialize([
    'easy'   => ['label' => 'Easy',   'class' => 'success'],
    'medium' => ['label' => 'Medium', 'class' => 'warning'],
    'hard'   => ['label' => 'Hard',   'class' => 'danger'],
]));

// ══════════════════════════════════════════════════════════════════════════════
// RUNTIME SETTINGS
// ══════════════════════════════════════════════════════════════════════════════

// Set timezone globally
date_default_timezone_set(APP_TIMEZONE);

// Error reporting – verbose in dev, silent in production
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors',         '1');
    ini_set('display_startup_errors', '1');
    ini_set('log_errors',             '1');
} else {
    error_reporting(0);
    ini_set('display_errors',  '0');
    ini_set('log_errors',      '1');
    ini_set('error_log',       ROOT_PATH . DS . 'logs' . DS . 'error.log');
}

// Create essential upload directories if they don't exist
$_uploadDirs = [RECIPE_IMG_PATH, PROFILE_IMG_PATH, PDF_PATH];
foreach ($_uploadDirs as $_dir) {
    if (!is_dir($_dir)) {
        mkdir($_dir, 0755, true);
    }
}
unset($_uploadDirs, $_dir);

// ── Auto-load all model and controller base classes via spl_autoload ─────────
spl_autoload_register(function (string $className): void {
    $paths = [
        ROOT_PATH . DS . 'models'      . DS . $className . '.php',
        ROOT_PATH . DS . 'controllers' . DS . $className . '.php',
        ROOT_PATH . DS . 'includes'    . DS . $className . '.php',
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// ── Load remaining core files ─────────────────────────────────────────────────
require_once CONFIG_PATH   . DS . 'database.php';
require_once INCLUDES_PATH . DS . 'Security.php';
require_once INCLUDES_PATH . DS . 'Session.php';
require_once INCLUDES_PATH . DS . 'helpers.php';

// Start the session immediately after all classes are loaded
Session::start();
