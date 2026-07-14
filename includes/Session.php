<?php

/**
 * MealKit – Session Manager
 * ==========================
 * Centralises all session operations:
 *   - Secure session initialisation
 *   - Login / logout lifecycle
 *   - Role-based auth checks
 *   - Flash messages
 *   - Old form input persistence (for validation repopulation)
 *   - Arbitrary key-value storage
 *   - Periodic session ID regeneration (anti-fixation)
 *
 * Every method is static so it can be called without instantiation:
 *   Session::start();
 *   Session::login($userData, 'customer');
 *   Session::isLoggedIn();
 */

declare(strict_types=1);

class Session
{
    /** Tracks whether session_start() has been called this request */
    private static bool $started = false;

    // ══════════════════════════════════════════════════════════════════════════
    // INITIALISATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Start the session with security-hardened settings.
     * Safe to call multiple times – subsequent calls are no-ops.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        // ── Harden PHP session settings ───────────────────────────────────────
        ini_set('session.use_strict_mode',   '1'); // reject unrecognised session IDs
        ini_set('session.use_only_cookies',  '1'); // never pass session ID in URL
        ini_set('session.cookie_httponly',   '1'); // block JS access to cookie
        ini_set('session.cookie_samesite',  'Strict');

        // Use HTTPS-only cookie in production
        $secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        ini_set('session.cookie_secure', $secureCookie ? '1' : '0');

        session_name(SESSION_NAME);

        session_set_cookie_params([
            'lifetime' => 0,                    // cookie lasts until browser close
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secureCookie,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
        self::$started = true;

        // ── Periodic session ID regeneration (anti-fixation) ──────────────────
        if (!isset($_SESSION['_initiated'])) {
            // Brand-new session
            session_regenerate_id(true);
            $_SESSION['_initiated'] = time();
            $_SESSION['_last_regen'] = time();
        } elseif ((time() - ($_SESSION['_last_regen'] ?? 0)) > SESSION_REGEN_AFTER) {
            // Regenerate every SESSION_REGEN_AFTER seconds
            session_regenerate_id(true);
            $_SESSION['_last_regen'] = time();
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Persist authenticated user data into the session.
     * Regenerates the session ID on every login (prevents fixation).
     *
     * @param array  $userData  Associative array of user fields to store
     * @param string $role      'customer' | 'admin' | 'super_admin'
     */
    public static function login(array $userData, string $role): void
    {
        // Regenerate ID to prevent session fixation
        session_regenerate_id(true);

        $_SESSION['auth'] = [
            'logged_in'  => true,
            'role'       => $role,
            'id'         => $userData['id'],
            'first_name' => $userData['first_name'],
            'last_name'  => $userData['last_name'],
            'email'      => $userData['email'],
            'avatar'     => $userData['avatar'] ?? DEFAULT_AVATAR,
        ];

        $_SESSION['_initiated']  = time();
        $_SESSION['_last_regen'] = time();
    }

    /**
     * Destroy the session and clear all data (logout).
     */
    public static function logout(): void
    {
        // Clear all session data
        $_SESSION = [];

        // Expire the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Check whether any user (customer OR admin) is logged in.
     */
    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['auth']['logged_in']);
    }

    /**
     * Check whether the logged-in user is a customer.
     */
    public static function isCustomer(): bool
    {
        return self::isLoggedIn() && $_SESSION['auth']['role'] === 'customer';
    }

    /**
     * Check whether the logged-in user has any admin role.
     */
    public static function isAdmin(): bool
    {
        return self::isLoggedIn() &&
               in_array($_SESSION['auth']['role'], ['admin', 'super_admin'], true);
    }

    /**
     * Check whether the logged-in user is specifically a super admin.
     */
    public static function isSuperAdmin(): bool
    {
        return self::isLoggedIn() && $_SESSION['auth']['role'] === 'super_admin';
    }

    /**
     * Return the current authenticated user's data array, or null if guest.
     */
    public static function user(): ?array
    {
        return self::isLoggedIn() ? ($_SESSION['auth'] ?? null) : null;
    }

    /**
     * Return a single field of the authenticated user, with an optional default.
     *
     * @param string $field   e.g. 'id', 'first_name', 'email', 'role'
     * @param mixed  $default Returned when the field does not exist
     */
    public static function userField(string $field, mixed $default = null): mixed
    {
        return $_SESSION['auth'][$field] ?? $default;
    }

    /**
     * Return the authenticated user's ID, or null for guests.
     */
    public static function userId(): ?int
    {
        return isset($_SESSION['auth']['id']) ? (int) $_SESSION['auth']['id'] : null;
    }

    /**
     * Return the authenticated user's display name.
     */
    public static function userName(): string
    {
        if (!self::isLoggedIn()) {
            return 'Guest';
        }
        return trim(($_SESSION['auth']['first_name'] ?? '') . ' ' . ($_SESSION['auth']['last_name'] ?? ''));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ACCESS CONTROL GUARDS
    // Used inside controllers to enforce role requirements.
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Abort with a redirect to login if the user is not logged in.
     *
     * @param string $loginUrl URL of the login page
     */
    public static function requireLogin(string $loginUrl = '/mealkit/auth/login'): void
    {
        if (!self::isLoggedIn()) {
            self::setFlash('error', 'Please log in to access that page.');
            redirect($loginUrl);
        }
    }

    /**
     * Abort with a 403 redirect unless the user has a customer role.
     */
    public static function requireCustomer(): void
    {
        self::requireLogin();
        if (!self::isCustomer()) {
            self::setFlash('error', 'Access restricted to customers.');
            redirect(APP_URL . '/admin/dashboard');
        }
    }

    /**
     * Abort with a 403 redirect unless the user has an admin role.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin(APP_URL . '/auth/login');
        if (!self::isAdmin()) {
            self::setFlash('error', 'You do not have permission to access the admin area.');
            redirect(APP_URL . '/customer/dashboard');
        }
    }

    /**
     * Abort unless the user is a super admin.
     */
    public static function requireSuperAdmin(): void
    {
        self::requireAdmin();
        if (!self::isSuperAdmin()) {
            self::setFlash('error', 'Super Admin privileges required.');
            redirect(APP_URL . '/admin/dashboard');
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FLASH MESSAGES
    // One-time messages shown on the next page load then discarded.
    // Types: 'success' | 'error' | 'warning' | 'info'
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Store a flash message for display on the next request.
     *
     * @param string $type    'success' | 'error' | 'warning' | 'info'
     * @param string $message Human-readable message text
     */
    public static function setFlash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    /**
     * Retrieve and clear all flash messages of a given type.
     * Returns an empty array when no messages exist.
     *
     * @param string $type 'success' | 'error' | 'warning' | 'info'
     */
    public static function getFlash(string $type): array
    {
        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }

    /**
     * Retrieve and clear ALL flash messages (all types).
     * Returns an associative array keyed by type.
     */
    public static function getAllFlash(): array
    {
        $all = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $all;
    }

    /**
     * Check whether there are any unread flash messages of a given type.
     */
    public static function hasFlash(string $type): bool
    {
        return !empty($_SESSION['_flash'][$type]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // OLD FORM INPUT  (repopulate forms after validation failure)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Persist the current POST data for retrieval on the next request.
     * Call this before redirecting back after a validation failure.
     *
     * @param array $data  Usually $_POST (strip passwords before storing!)
     */
    public static function setOldInput(array $data): void
    {
        // Never persist password fields
        unset($data['password'], $data['password_confirm'], $data['csrf_token']);
        $_SESSION['_old_input'] = $data;
    }

    /**
     * Retrieve an old form field value (and clear the entire old-input store).
     *
     * @param string $key     Form field name
     * @param string $default Value to return if the field is absent
     */
    public static function getOldInput(string $key, string $default = ''): string
    {
        $value = $_SESSION['_old_input'][$key] ?? $default;
        // Do NOT unset here – views may call old() multiple times per render
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Clear the stored old-input data.
     * Call this after a view has finished rendering (or on successful form submit).
     */
    public static function clearOldInput(): void
    {
        unset($_SESSION['_old_input']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // GENERIC KEY–VALUE STORE
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Store an arbitrary value in the session under a namespaced key.
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve a value from the session, with an optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check whether a key exists in the session (and is non-null).
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a key from the session.
     */
    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Update a specific field inside the auth payload (e.g. after profile update).
     */
    public static function updateAuthField(string $field, mixed $value): void
    {
        if (isset($_SESSION['auth'])) {
            $_SESSION['auth'][$field] = $value;
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CSRF  (thin wrapper – full logic lives in Security.php)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return the active CSRF token, generating one if absent.
     */
    public static function csrfToken(): string
    {
        return Security::generateCSRFToken();
    }
}
