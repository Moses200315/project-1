<?php

/**
 * BaseController – Shared Controller Foundation
 * ===============================================
 * Every controller extends this class to inherit:
 *   - View rendering
 *   - JSON response helper
 *   - Request method detection (GET/POST/AJAX)
 *   - Input retrieval with type-casting
 *   - Auth guard shortcuts
 *   - Flash message shortcuts
 */

declare(strict_types=1);

abstract class BaseController
{
    // ── Render a view file, passing data as local variables ──────────────────
    protected function renderView(string $viewPath, array $data = []): void
    {
        // Always inject shared UI data every view needs
        $data['currentUser']       = Session::user();
        $data['isLoggedIn']        = Session::isLoggedIn();
        $data['isAdmin']           = Session::isAdmin();
        $data['unreadNotifCount']  = 0;

        // Load unread notification count if a customer is logged in
        if (Session::isCustomer()) {
            $notifModel = new NotificationModel();
            $data['unreadNotifCount'] = $notifModel->countUnread(Session::userId());
        }

        render_view($viewPath, $data);
    }

    // ── Alias for backward compatibility ─────────────────────────────────────
    protected function view(string $viewPath, array $data = []): void
    {
        $this->renderView($viewPath, $data);
    }

    // ── Send a JSON response and halt execution ───────────────────────────────
    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    // ── Redirect helpers ──────────────────────────────────────────────────────
    protected function redirectTo(string $path): never
    {
        redirect($path);
    }

    protected function back(string $fallback = '/'): never
    {
        redirect_back($fallback);
    }

    // ── Request detection ─────────────────────────────────────────────────────
    protected function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function isGet(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
    }

    protected function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    // ── Input helpers ─────────────────────────────────────────────────────────

    /** Get from POST then GET, return sanitized string */
    protected function input(string $key, string $default = ''): string
    {
        $val = $_POST[$key] ?? $_GET[$key] ?? $default;
        return Security::cleanString((string) $val);
    }

    /** Get POST value as sanitized string */
    protected function post(string $key, string $default = ''): string
    {
        return Security::cleanString((string) ($_POST[$key] ?? $default));
    }

    /** Get GET value as sanitized string */
    protected function query(string $key, string $default = ''): string
    {
        return Security::cleanString((string) ($_GET[$key] ?? $default));
    }

    /** Get POST/GET value as positive integer, 0 if invalid */
    protected function intInput(string $key, int $default = 0): int
    {
        $val = $_POST[$key] ?? $_GET[$key] ?? $default;
        return max(0, (int) filter_var($val, FILTER_VALIDATE_INT));
    }

    /** Get the current page number from query string (minimum 1) */
    protected function currentPage(): int
    {
        return max(1, $this->intInput('page', 1));
    }

    // ── Auth shortcuts ────────────────────────────────────────────────────────
    protected function requireLogin(): void   { Session::requireLogin(); }
    protected function requireAdmin(): void   { Session::requireAdmin(); }
    protected function requireCustomer(): void{ Session::requireCustomer(); }

    protected function userId(): ?int   { return Session::userId(); }
    protected function user(): ?array   { return Session::user(); }

    // ── Flash shortcuts ───────────────────────────────────────────────────────
    protected function success(string $msg): void { Session::setFlash('success', $msg); }
    protected function error(string $msg): void   { Session::setFlash('error',   $msg); }
    protected function warning(string $msg): void { Session::setFlash('warning', $msg); }
    protected function info(string $msg): void    { Session::setFlash('info',    $msg); }

    // ── CSRF enforcement ──────────────────────────────────────────────────────
    protected function verifyCsrf(): void
    {
        Security::verifyCSRF();
    }

    // ── 404 helper ────────────────────────────────────────────────────────────
    protected function abort404(string $message = 'Page not found.'): never
    {
        http_response_code(404);
        $this->view('layouts/404', ['message' => $message]);
        exit;
    }

    // ── Sanitise ID from URL segment ─────────────────────────────────────────
    protected function resolveId(mixed $id): int
    {
        $id = (int) filter_var($id, FILTER_VALIDATE_INT);
        if ($id <= 0) {
            $this->abort404();
        }
        return $id;
    }
}
