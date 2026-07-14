<?php

/**
 * AuthController – Authentication Lifecycle
 * ===========================================
 * Handles:
 *   - Customer registration
 *   - Admin & Customer login with rate-limiting
 *   - Logout (session destruction)
 *   - Forgot-password token generation
 *   - Reset-password with token verification
 */

declare(strict_types=1);

class AuthController extends BaseController
{
    private UserModel         $userModel;
    private AdminModel        $adminModel;
    private NotificationModel $notifModel;

    public function __construct()
    {
        $this->userModel  = new UserModel();
        $this->adminModel = new AdminModel();
        $this->notifModel = new NotificationModel();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LOGIN
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET  /auth/login   → show login form
     * POST /auth/login   → process credentials
     */
    public function login(): void
    {
        // Already logged in → redirect to appropriate dashboard
        if (Session::isLoggedIn()) {
            $this->redirectTo(Session::isAdmin() ? url('admin/dashboard') : url('customer/dashboard'));
        }

        if ($this->isPost()) {
            $this->handleLogin();
            return;
        }

        $this->view('auth/login', [
            'pageTitle' => 'Login – ' . APP_NAME,
        ]);
    }

    private function handleLogin(): void
    {
        $this->verifyCsrf();

        $email    = Security::cleanEmail($this->post('email'));
        $password = $_POST['password'] ?? '';      // raw – will only be passed to verifyPassword()
        $role     = $this->post('role', 'customer'); // 'customer' | 'admin'

        // ── Input validation ──────────────────────────────────────────────────
        if ($email === '' || $password === '') {
            $this->error('Email and password are required.');
            Session::setOldInput(['email' => $email, 'role' => $role]);
            $this->redirectTo(url('auth/login'));
        }

        // ── Rate limiting ─────────────────────────────────────────────────────
        $rateLimitKey = 'login_' . md5($email);
        if (Security::isRateLimited($rateLimitKey)) {
            $seconds = Security::rateLimitSecondsRemaining($rateLimitKey);
            $minutes = ceil($seconds / 60);
            $this->error("Too many failed attempts. Please wait {$minutes} minute(s) before trying again.");
            $this->redirectTo(url('auth/login'));
        }

        // ── Authenticate ──────────────────────────────────────────────────────
        if ($role === 'admin') {
            $user = $this->adminModel->verifyCredentials($email, $password);
            $sessionRole = $user['role'] ?? 'admin'; // 'admin' or 'super_admin'
        } else {
            $user = $this->userModel->verifyCredentials($email, $password);
            $sessionRole = 'customer';
        }

        if ($user === null) {
            Security::recordFailedAttempt($rateLimitKey);
            $this->error('Invalid email or password. Please try again.');
            Session::setOldInput(['email' => $email, 'role' => $role]);
            $this->redirectTo(url('auth/login'));
        }

        // ── Success: start authenticated session ─────────────────────────────
        Security::clearRateLimit($rateLimitKey);
        Session::login($user, $sessionRole);
        Session::clearOldInput();

        // Update last_login timestamp
        if ($role === 'admin') {
            $this->adminModel->updateLastLogin((int) $user['id']);
            $this->success('Welcome back, ' . e($user['first_name']) . '!');
            $this->redirectTo(url('admin/dashboard'));
        } else {
            $this->userModel->updateLastLogin((int) $user['id']);
            $this->success('Welcome back, ' . e($user['first_name']) . '!');
            $this->redirectTo(url('customer/dashboard'));
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REGISTER
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET  /auth/register  → show registration form
     * POST /auth/register  → process new account
     */
    public function register(): void
    {
        if (Session::isLoggedIn()) {
            $this->redirectTo(url('customer/dashboard'));
        }

        if ($this->isPost()) {
            $this->handleRegister();
            return;
        }

        $this->view('auth/register', [
            'pageTitle' => 'Create Account – ' . APP_NAME,
        ]);
    }

    private function handleRegister(): void
    {
        $this->verifyCsrf();

        $firstName = Security::cleanString($this->post('first_name'));
        $lastName  = Security::cleanString($this->post('last_name'));
        $email     = Security::cleanEmail($this->post('email'));
        $phone     = Security::cleanPhone($this->post('phone'));
        $password  = $_POST['password']         ?? '';
        $passConf  = $_POST['password_confirm'] ?? '';

        $errors = [];

        // ── Field validation ──────────────────────────────────────────────────
        if (empty($firstName))  $errors[] = 'First name is required.';
        if (empty($lastName))   $errors[] = 'Last name is required.';
        if (empty($email))      $errors[] = 'A valid email address is required.';
        if (!Security::isValidEmail($email)) $errors[] = 'Email format is invalid.';

        // Password strength
        $pwCheck = Security::validatePasswordStrength($password);
        if (!$pwCheck['valid']) {
            $errors = array_merge($errors, $pwCheck['errors']);
        }
        if ($password !== $passConf) {
            $errors[] = 'Passwords do not match.';
        }

        // Email uniqueness
        if ($email !== '' && $this->userModel->findByEmail($email) !== null) {
            $errors[] = 'An account with this email already exists.';
        }

        if (!empty($errors)) {
            foreach ($errors as $err) {
                $this->error($err);
            }
            Session::setOldInput([
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'phone'      => $phone,
            ]);
            $this->redirectTo(url('auth/register'));
        }

        // ── Create account ────────────────────────────────────────────────────
        $userId = $this->userModel->register([
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'password'   => $password,
            'phone'      => $phone,
        ]);

        // Auto-login after registration
        $newUser = $this->userModel->findById($userId);
        Session::login($newUser, 'customer');

        // Welcome notification
        $this->notifModel->notify([
            'user_id'    => $userId,
            'title'      => 'Welcome to ' . APP_NAME . '! 🎉',
            'message'    => "Hi {$firstName}! Your account is ready. Explore recipes, build meal plans, and enjoy a seamless cooking experience.",
            'type'       => 'success',
            'category'   => 'general',
            'action_url' => url('recipes'),
        ]);

        $this->success("Account created! Welcome, {$firstName}.");
        $this->redirectTo(url('customer/dashboard'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LOGOUT
    // ══════════════════════════════════════════════════════════════════════════

    /** POST /auth/logout */
    public function logout(): void
    {
        if ($this->isPost()) {
            $this->verifyCsrf();
        }
        Session::logout();
        $this->success('You have been logged out successfully.');
        $this->redirectTo(url('auth/login'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FORGOT PASSWORD
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET  /auth/forgot   → show forgot-password form
     * POST /auth/forgot   → generate reset token
     */
    public function forgot(): void
    {
        if ($this->isPost()) {
            $this->handleForgot();
            return;
        }

        $this->view('auth/forgot', [
            'pageTitle' => 'Reset Password – ' . APP_NAME,
        ]);
    }

    private function handleForgot(): void
    {
        $this->verifyCsrf();
        $email = Security::cleanEmail($this->post('email'));

        if (empty($email)) {
            $this->error('Please enter your email address.');
            $this->redirectTo(url('auth/forgot'));
        }

        $user = $this->userModel->findByEmail($email);

        // Always show the same message to prevent email enumeration
        $this->success('If an account exists for that email, a reset link has been generated.');

        if ($user !== null && $user['status'] === 'active') {
            $tokenData = Security::generateResetToken(60);
            $this->userModel->setResetToken($email, $tokenData['token'], $tokenData['expires_at']);

            // In production: send email with the link below.
            // On XAMPP sandbox: store it in session so user can see it
            Session::set('_debug_reset_link', url('auth/reset/' . $tokenData['token']));
        }

        $this->redirectTo(url('auth/forgot'));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // RESET PASSWORD
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * GET  /auth/reset/{token}  → show new-password form
     * POST /auth/reset/{token}  → save new password
     */
    public function reset(string $token = ''): void
    {
        $token = Security::cleanString($token);

        if ($token === '') {
            $this->abort404();
        }

        $user = $this->userModel->findByResetToken($token);
        if ($user === null) {
            $this->error('This password reset link is invalid or has expired.');
            $this->redirectTo(url('auth/forgot'));
        }

        if ($this->isPost()) {
            $this->handleReset($token, (int) $user['id']);
            return;
        }

        $this->view('auth/reset', [
            'pageTitle' => 'Set New Password – ' . APP_NAME,
            'token'     => $token,
        ]);
    }

    private function handleReset(string $token, int $userId): void
    {
        $this->verifyCsrf();

        $password = $_POST['password']         ?? '';
        $passConf = $_POST['password_confirm'] ?? '';

        $pwCheck = Security::validatePasswordStrength($password);
        if (!$pwCheck['valid']) {
            foreach ($pwCheck['errors'] as $err) {
                $this->error($err);
            }
            $this->redirectTo(url('auth/reset/' . $token));
        }

        if ($password !== $passConf) {
            $this->error('Passwords do not match.');
            $this->redirectTo(url('auth/reset/' . $token));
        }

        $this->userModel->updatePassword($userId, $password);
        $this->userModel->clearResetToken($userId);

        $this->success('Your password has been reset. Please log in.');
        $this->redirectTo(url('auth/login'));
    }
}
