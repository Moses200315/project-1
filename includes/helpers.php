<?php

/**
 * MealKit – Global Helper Functions
 * ===================================
 * A collection of pure utility functions available everywhere in the app.
 * These are intentionally procedural (not OOP) to keep call syntax short:
 *
 *   redirect('/mealkit/auth/login');
 *   echo e($userInput);
 *   echo url('recipes/view/12');
 *
 * Helpers depend only on the constants defined in config.php and
 * the static classes Session and Security.
 */

declare(strict_types=1);

// ══════════════════════════════════════════════════════════════════════════════
// OUTPUT & ESCAPING
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Escape a value for safe HTML output.
 * Short alias for Security::escape() – use in every view template.
 *
 * @param  mixed  $value
 * @return string
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Print an escaped value directly (convenience wrapper around e()).
 */
function pe(mixed $value): void
{
    echo e($value);
}

// ══════════════════════════════════════════════════════════════════════════════
// ROUTING & URLS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Build a full application URL from a relative path.
 *
 * @param  string $path  e.g. 'recipes/view/12'  or  '/auth/login'
 * @return string        e.g. 'http://localhost/mealkit/recipes/view/12'
 */
function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return $path === '' ? APP_URL . '/' : APP_URL . '/' . $path;
}

/**
 * Build a URL to a public asset (CSS, JS, image, font).
 *
 * @param  string $path  e.g. 'css/style.css'
 * @return string
 */
function asset(string $path): string
{
    return ASSETS_URL . '/' . ltrim($path, '/');
}

/**
 * Build a URL to an uploaded file.
 *
 * @param  string $path  e.g. 'recipes/jollof.jpg'
 * @return string
 */
function upload_url(string $path): string
{
    return UPLOADS_URL . '/' . ltrim($path, '/');
}

/**
 * Build the URL for a recipe image, falling back to the default placeholder.
 *
 * @param  string|null $filename  Stored filename (null = use default)
 * @return string
 */
function recipe_img_url(?string $filename): string
{
    if ($filename) {
        // Try the uploads directory first
        $uploadPath = RECIPE_IMG_PATH . DS . $filename;
        if (file_exists($uploadPath)) {
            return RECIPE_IMG_URL . '/' . $filename;
        }
        // Fallback to assets directory if file exists there
        $assetPath = ASSETS_PATH . DS . 'images' . DS . $filename;
        if (file_exists($assetPath)) {
            return ASSETS_URL . '/images/' . $filename;
        }
    }
    return ASSETS_URL . '/images/' . DEFAULT_RECIPE_IMG;
}

/**
 * Build the URL for a user avatar, falling back to the default placeholder.
 *
 * @param  string|null $filename  Stored filename (null = use default)
 * @return string
 */
function avatar_url(?string $filename): string
{
    if ($filename && $filename !== DEFAULT_AVATAR && file_exists(PROFILE_IMG_PATH . DS . $filename)) {
        return PROFILE_IMG_URL . '/' . $filename;
    }
    return ASSETS_URL . '/images/default-avatar.png';
}

// ══════════════════════════════════════════════════════════════════════════════
// REDIRECTION
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Redirect to a URL and stop execution.
 *
 * @param  string $url         Full URL or APP_URL-relative path
 * @param  int    $statusCode  HTTP status (301 = permanent, 302 = temporary)
 */
function redirect(string $url, int $statusCode = 302): never
{
    // If the URL does not start with http(s) treat it as an APP_URL-relative path
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
        $url = APP_URL . '/' . ltrim($url, '/');
    }
    header('Location: ' . $url, true, $statusCode);
    exit;
}

/**
 * Redirect back to the HTTP Referer, or to a fallback URL.
 *
 * @param string $fallback  Fallback URL if Referer is unavailable
 */
function redirect_back(string $fallback = '/'): never
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($referer ?: $fallback);
}

// ══════════════════════════════════════════════════════════════════════════════
// FLASH MESSAGES
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Set a flash message (delegate to Session).
 *
 * @param string $type    'success' | 'error' | 'warning' | 'info'
 * @param string $message
 */
function flash(string $type, string $message): void
{
    Session::setFlash($type, $message);
}

/**
 * Render all pending flash messages as Bootstrap alert divs.
 * Clears messages after rendering.
 * Safe to call multiple times per page – messages are shown only once.
 *
 * @return string  HTML string ready for echo
 */
function render_flash(): string
{
    $typeMap = [
        'success' => 'success',
        'error'   => 'danger',
        'warning' => 'warning',
        'info'    => 'info',
    ];

    $icons = [
        'success' => '✅',
        'error'   => '❌',
        'warning' => '⚠️',
        'info'    => 'ℹ️',
    ];

    $html = '';
    foreach ($typeMap as $flashType => $bsType) {
        $messages = Session::getFlash($flashType);
        foreach ($messages as $msg) {
            $icon = $icons[$flashType] ?? '';
            $html .= '<div class="alert alert-' . $bsType . ' alert-dismissible fade show" role="alert">';
                $html .= '<span class="me-2">' . $icon . '</span>' . e($msg);
                $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                $html .= '</div>';
            }
        }
        return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// OLD FORM INPUT
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Retrieve an old form field value (for repopulating inputs after validation).
 *
 * @param  string $key      Form field name
 * @param  string $default  Fallback value
 * @return string           HTML-escaped value ready for use in value="" attribute
 */
function old(string $key, string $default = ''): string
{
    return Session::getOldInput($key, $default);
}

// ══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION SHORTCUTS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Return true if any user is currently logged in.
 */
function is_logged_in(): bool
{
    return Session::isLoggedIn();
}

/**
 * Return true if the logged-in user is a customer.
 */
function is_customer(): bool
{
    return Session::isCustomer();
}

/**
 * Return true if the logged-in user is an admin (any admin role).
 */
function is_admin(): bool
{
    return Session::isAdmin();
}

/**
 * Return the current user data array, or null for guests.
 */
function current_user(): ?array
{
    return Session::user();
}

/**
 * Return the current user's ID, or null for guests.
 */
function current_user_id(): ?int
{
    return Session::userId();
}

// ══════════════════════════════════════════════════════════════════════════════
// CSRF
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Render the CSRF hidden input field for embedding in forms.
 */
function csrf_field(): string
{
    return Security::csrfField();
}

/**
 * Return the raw CSRF token string.
 */
function csrf_token(): string
{
    return Security::generateCSRFToken();
}

// ══════════════════════════════════════════════════════════════════════════════
// STRING UTILITIES
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Convert a string to a URL-friendly slug.
 *
 * @param  string $text   Source string (e.g. recipe title)
 * @return string         e.g. 'ghanaian-jollof-rice'
 */
function slugify(string $text): string
{
    // Transliterate Unicode characters to ASCII equivalents
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', $text);
    return trim($text, '-');
}

/**
 * Truncate a string to a maximum length, appending a suffix if cut.
 *
 * @param  string $text    Input string
 * @param  int    $limit   Maximum character count
 * @param  string $suffix  Appended when truncated (default '…')
 * @return string
 */
function truncate(string $text, int $limit = 120, string $suffix = '…'): string
{
    if (mb_strlen($text, 'UTF-8') <= $limit) {
        return $text;
    }
    return rtrim(mb_substr($text, 0, $limit, 'UTF-8')) . $suffix;
}

/**
 * Convert a number of minutes to a human-readable duration string.
 *
 * @param  int    $minutes  e.g. 95
 * @return string           e.g. '1 hr 35 min'
 */
function format_duration(int $minutes): string
{
    if ($minutes <= 0) {
        return '0 min';
    }
    $hours = intdiv($minutes, 60);
    $mins  = $minutes % 60;
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' hr';
    }
    if ($mins > 0) {
        $parts[] = $mins . ' min';
    }
    return implode(' ', $parts);
}

/**
 * Ordinal suffix for an integer (1st, 2nd, 3rd, 4th …).
 *
 * @param  int    $n
 * @return string
 */
function ordinal(int $n): string
{
    $suffix = ['th','st','nd','rd'];
    $v      = $n % 100;
    return $n . ($suffix[($v - 20) % 10] ?? $suffix[$v] ?? $suffix[0]);
}

// ══════════════════════════════════════════════════════════════════════════════
// DATE & TIME
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Format a MySQL datetime string for display.
 *
 * @param  string|null $datetime  MySQL datetime (Y-m-d H:i:s) or null
 * @param  string      $format    PHP date format string
 * @return string
 */
function format_date(?string $datetime, string $format = 'd M Y'): string
{
    if (empty($datetime)) {
        return 'N/A';
    }
    try {
        return (new DateTime($datetime))->format($format);
    } catch (Exception) {
        return 'Invalid date';
    }
}

/**
 * Return a human-friendly relative time string ("2 hours ago", "3 days ago").
 *
 * @param  string $datetime  MySQL datetime string
 * @return string
 */
function time_ago(string $datetime): string
{
    try {
        $time  = (new DateTime($datetime))->getTimestamp();
        $diff  = time() - $time;

        return match (true) {
            $diff < 60                => 'Just now',
            $diff < 3600              => intdiv($diff, 60)     . ' minute'  . (intdiv($diff, 60)     !== 1 ? 's' : '') . ' ago',
            $diff < 86400             => intdiv($diff, 3600)   . ' hour'    . (intdiv($diff, 3600)   !== 1 ? 's' : '') . ' ago',
            $diff < 604800            => intdiv($diff, 86400)  . ' day'     . (intdiv($diff, 86400)  !== 1 ? 's' : '') . ' ago',
            $diff < 2592000           => intdiv($diff, 604800) . ' week'    . (intdiv($diff, 604800) !== 1 ? 's' : '') . ' ago',
            $diff < 31536000          => intdiv($diff, 2592000). ' month'   . (intdiv($diff, 2592000)!== 1 ? 's' : '') . ' ago',
            default                   => intdiv($diff, 31536000). ' year'   . (intdiv($diff, 31536000)!== 1 ? 's' : '') . ' ago',
        };
    } catch (Exception) {
        return 'Unknown time';
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// CURRENCY & NUMBERS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Format a numeric amount as currency.
 *
 * @param  float  $amount
 * @param  string $symbol   Currency symbol (default: from config)
 * @param  int    $decimals Number of decimal places
 * @return string           e.g. '₵ 99.99'
 */
function format_currency(float $amount, string $symbol = CURRENCY_SYMBOL, int $decimals = 2): string
{
    return $symbol . ' ' . number_format($amount, $decimals);
}

/**
 * Format a file size in bytes to a human-readable string.
 *
 * @param  int  $bytes
 * @return string  e.g. '2.4 MB'
 */
function format_file_size(int $bytes): string
{
    if ($bytes < 1024)       return $bytes       . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
    return round($bytes / 1073741824, 2) . ' GB';
}

// ══════════════════════════════════════════════════════════════════════════════
// FILE UPLOADS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Handle an image file upload: validate, rename, move, and optionally
 * delete the previously stored image.
 *
 * @param  array       $file        Single entry from $_FILES
 * @param  string      $directory   Absolute path to the destination directory
 * @param  string|null $oldFilename Existing filename to delete on success (optional)
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function upload_image(array $file, string $directory, ?string $oldFilename = null): array
{
    // 1. Validate
    $validation = Security::validateImageUpload($file);
    if (!$validation['valid']) {
        return ['success' => false, 'filename' => '', 'error' => $validation['error']];
    }

    // 2. Generate unique filename: timestamp_random.ext
    $ext         = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $newFilename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destination = rtrim($directory, DS) . DS . $newFilename;

    // 3. Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'filename' => '', 'error' => 'Failed to save the uploaded file. Check directory permissions.'];
    }

    // 4. Delete old file if provided and it exists (never delete default images)
    if ($oldFilename && !in_array($oldFilename, [DEFAULT_AVATAR, DEFAULT_RECIPE_IMG], true)) {
        $oldPath = rtrim($directory, DS) . DS . $oldFilename;
        if (file_exists($oldPath)) {
            @unlink($oldPath);
        }
    }

    return ['success' => true, 'filename' => $newFilename, 'error' => ''];
}

/**
 * Safely delete an uploaded file from disk.
 * Never deletes default placeholder images.
 *
 * @param  string $directory   Absolute directory path
 * @param  string $filename    Filename to delete
 * @return bool
 */
function delete_uploaded_file(string $directory, string $filename): bool
{
    if (in_array($filename, [DEFAULT_AVATAR, DEFAULT_RECIPE_IMG], true)) {
        return false; // Protect defaults
    }
    $fullPath = rtrim($directory, DS) . DS . $filename;
    return file_exists($fullPath) ? @unlink($fullPath) : false;
}

// ══════════════════════════════════════════════════════════════════════════════
// PAGINATION
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Build a pagination data array for use in view templates.
 *
 * @param  int    $totalItems   Total number of records
 * @param  int    $perPage      Records per page
 * @param  int    $currentPage  Current page number (1-based)
 * @param  string $baseUrl      Base URL for page links (no ?page= suffix)
 * @return array  {
 *   total_items, per_page, current_page, total_pages,
 *   has_prev, has_next, prev_page, next_page, offset,
 *   pages[]  (array of page numbers for link rendering)
 * }
 */
function paginate(int $totalItems, int $perPage, int $currentPage, string $baseUrl = ''): array
{
    $perPage     = max(1, $perPage);
    $totalPages  = (int) ceil($totalItems / $perPage);
    $currentPage = max(1, min($currentPage, max(1, $totalPages)));
    $offset      = ($currentPage - 1) * $perPage;

    // Build page number range (show at most 5 page buttons)
    $range   = 2;
    $start   = max(1, $currentPage - $range);
    $end     = min($totalPages, $currentPage + $range);
    $pages   = range($start, $end);

    return [
        'total_items'  => $totalItems,
        'per_page'     => $perPage,
        'current_page' => $currentPage,
        'total_pages'  => $totalPages,
        'has_prev'     => $currentPage > 1,
        'has_next'     => $currentPage < $totalPages,
        'prev_page'    => $currentPage - 1,
        'next_page'    => $currentPage + 1,
        'offset'       => $offset,
        'pages'        => $pages,
        'base_url'     => $baseUrl,
    ];
}

/**
 * Render a Bootstrap 5 pagination nav from a paginate() data array.
 *
 * @param  array  $pager  Output of paginate()
 * @return string         HTML <nav> element
 */
function render_pagination(array $pager): string
{
    if ($pager['total_pages'] <= 1) {
        return '';
    }

    $base = rtrim($pager['base_url'], '?&');
    $sep  = str_contains($base, '?') ? '&' : '?';

    $html  = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center flex-wrap">';

    // Previous button
    if ($pager['has_prev']) {
        $html .= '<li class="page-item">'
               . '<a class="page-link" href="' . e($base . $sep . 'page=' . $pager['prev_page']) . '">&laquo; Prev</a>'
               . '</li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">&laquo; Prev</span></li>';
    }

    // First page + ellipsis
    if ($pager['pages'][0] > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($base . $sep . 'page=1') . '">1</a></li>';
        if ($pager['pages'][0] > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    // Numbered pages
    foreach ($pager['pages'] as $page) {
        $active = ($page === $pager['current_page']) ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">'
               . '<a class="page-link" href="' . e($base . $sep . 'page=' . $page) . '">' . $page . '</a>'
               . '</li>';
    }

    // Last page + ellipsis
    $lastVisible = end($pager['pages']);
    if ($lastVisible < $pager['total_pages']) {
        if ($lastVisible < $pager['total_pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="'
               . e($base . $sep . 'page=' . $pager['total_pages']) . '">' . $pager['total_pages'] . '</a></li>';
    }

    // Next button
    if ($pager['has_next']) {
        $html .= '<li class="page-item">'
               . '<a class="page-link" href="' . e($base . $sep . 'page=' . $pager['next_page']) . '">Next &raquo;</a>'
               . '</li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>';
    }

    $html .= '</ul></nav>';
    return $html;
}

// ══════════════════════════════════════════════════════════════════════════════
// VIEW / NAVIGATION HELPERS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Return a CSS class string if the given URL path matches the current URL.
 * Used to highlight the active nav item.
 *
 * @param  string $path   Path segment to match (e.g. 'recipes' or 'admin/reports')
 * @param  string $class  Class to apply (default 'active')
 * @return string
 */
function active_class(string $path, string $class = 'active'): string
{
    $current = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($current, $path) ? $class : '';
}

/**
 * Render a Bootstrap badge for a recipe difficulty level.
 *
 * @param  string $difficulty  'easy' | 'medium' | 'hard'
 * @return string              HTML <span class="badge …"> element
 */
function difficulty_badge(string $difficulty): string
{
    $map = unserialize(DIFFICULTY_LABELS);
    $d   = $map[$difficulty] ?? ['label' => ucfirst($difficulty), 'class' => 'secondary'];
    return '<span class="badge bg-' . $d['class'] . '">' . e($d['label']) . '</span>';
}

/**
 * Render a Bootstrap badge for a subscription / payment status.
 *
 * @param  string $status
 * @return string
 */
function status_badge(string $status): string
{
    $map = [
        'active'    => 'success',
        'success'   => 'success',
        'pending'   => 'warning',
        'expired'   => 'secondary',
        'cancelled' => 'secondary',
        'failed'    => 'danger',
        'refunded'  => 'info',
        'draft'     => 'secondary',
        'published' => 'success',
        'archived'  => 'dark',
        'inactive'  => 'secondary',
        'banned'    => 'danger',
    ];
    $class = $map[strtolower($status)] ?? 'secondary';
    return '<span class="badge bg-' . $class . '">' . e(ucfirst($status)) . '</span>';
}

// ══════════════════════════════════════════════════════════════════════════════
// SERVING SIZE CALCULATOR
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Scale a recipe ingredient quantity to a new serving count.
 * Handles numeric quantities (including fractions like "1/2") and
 * passes through non-numeric values unchanged (e.g. "to taste", "a pinch").
 *
 * @param  string $quantity      Original quantity string (e.g. "2", "1/2", "250")
 * @param  int    $originalServings  Recipe's default serving count
 * @param  int    $newServings       Desired serving count
 * @return string                Scaled quantity string
 */
function scale_quantity(string $quantity, int $originalServings, int $newServings): string
{
    if ($originalServings <= 0 || $newServings <= 0) {
        return $quantity;
    }

    $factor = $newServings / $originalServings;

    // Handle fraction strings like "1/2", "3/4"
    if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', trim($quantity), $m)) {
        $numeric = (float) $m[1] / (float) $m[2];
        return format_quantity($numeric * $factor);
    }

    // Handle mixed numbers like "1 1/2"
    if (preg_match('/^(\d+)\s+(\d+)\s*\/\s*(\d+)$/', trim($quantity), $m)) {
        $numeric = (float) $m[1] + (float) $m[2] / (float) $m[3];
        return format_quantity($numeric * $factor);
    }

    // Handle plain numerics (integers and decimals)
    if (is_numeric(trim($quantity))) {
        return format_quantity((float) $quantity * $factor);
    }

    // Non-numeric (e.g. "to taste", "a pinch") – return unchanged
    return $quantity;
}

/**
 * Format a scaled numeric quantity back to a readable string.
 * Converts common decimals back to fractions for culinary readability.
 *
 * @param  float  $value
 * @return string
 */
function format_quantity(float $value): string
{
    // Common fraction map - use string keys to avoid float to int conversion
    $fractions = [
        '0.125' => '1/8', '0.25' => '1/4', '0.333' => '1/3',
        '0.375' => '3/8', '0.5'  => '1/2', '0.625' => '5/8',
        '0.667' => '2/3', '0.75' => '3/4', '0.875' => '7/8',
    ];

    $whole    = (int) floor($value);
    $decimal  = round($value - $whole, 3);
    $decimalStr = (string) $decimal;

    // Find closest fraction within 0.01 tolerance
    $fracStr = '';
    foreach ($fractions as $fracStrKey => $label) {
        $fracVal = (float) $fracStrKey;
        if (abs($decimal - $fracVal) < 0.01) {
            $fracStr = $label;
            break;
        }
    }

    if ($whole > 0 && $fracStr !== '') {
        return $whole . ' ' . $fracStr;
    }
    if ($whole === 0 && $fracStr !== '') {
        return $fracStr;
    }
    if ($decimal === 0.0 || abs($decimal) < 0.01) {
        return (string) $whole;
    }
    // Fall back to one decimal place
    return rtrim(rtrim(number_format($value, 2), '0'), '.');
}

// ══════════════════════════════════════════════════════════════════════════════
// SLUG GENERATION (unique, database-aware)
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Generate a URL slug that is unique in the given database table/column.
 * Appends an incrementing suffix (-2, -3 …) if collisions are found.
 *
 * @param  string  $title      Source title string
 * @param  string  $table      Database table name (e.g. 'recipes')
 * @param  string  $column     Slug column name (default 'slug')
 * @param  int     $excludeId  Exclude this row ID (for update operations)
 * @return string
 */
function generate_unique_slug(string $title, string $table, string $column = 'slug', int $excludeId = 0): string
{
    $db      = Database::getInstance();
    $base    = slugify($title);
    $slug    = $base;
    $counter = 2;

    while (true) {
        $sql    = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?";
        $params = [$slug];

        if ($excludeId > 0) {
            $sql      .= ' AND `id` != ?';
            $params[]  = $excludeId;
        }

        $count = (int) $db->fetchColumn($sql, $params);
        if ($count === 0) {
            break;
        }
        $slug = $base . '-' . $counter++;
    }

    return $slug;
}

// ══════════════════════════════════════════════════════════════════════════════
// VIEW RENDERER
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Include a view file from the views/ directory, extracting
 * an associative array of data into local variables.
 *
 * @param  string $viewPath  Relative to views/ (e.g. 'admin/recipes/index')
 * @param  array  $data      Variables to expose inside the view
 */
function render_view(string $viewPath, array $data = []): void
{
    $file = VIEWS_PATH . DS . str_replace('/', DS, $viewPath) . '.php';
    if (!file_exists($file)) {
        if (APP_DEBUG) {
            die("View not found: {$file}");
        }
        http_response_code(404);
        include VIEWS_PATH . DS . 'layouts' . DS . '404.php';
        exit;
    }
    extract($data, EXTR_SKIP);   // EXTR_SKIP: never overwrite existing variables
    require $file;
}

// ══════════════════════════════════════════════════════════════════════════════
// DEBUG
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Dump one or more variables and terminate execution.
 * Only active when APP_DEBUG is true.
 *
 * @param mixed ...$vars
 */
function dd(mixed ...$vars): never
{
    if (APP_DEBUG) {
        echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:1rem;border-radius:6px;font-size:.85rem;">';
        foreach ($vars as $var) {
            var_dump($var);
        }
        echo '</pre>';
    }
    exit;
}

/**
 * Dump one or more variables without terminating.
 * Only active when APP_DEBUG is true.
 *
 * @param mixed ...$vars
 */
function dump(mixed ...$vars): void
{
    if (!APP_DEBUG) {
        return;
    }
    echo '<pre style="background:#1e1e1e;color:#d4d4d4;padding:1rem;border-radius:6px;font-size:.85rem;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
}

