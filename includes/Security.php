<?php

declare(strict_types=1);

defined('ROOT_PATH') or die('Direct access not permitted.');


/**
 * MealKit – Security Class
 * =========================
 *
 * Centralises all security-sensitive operations:
 *
 * - CSRF token generation and validation
 * - Input sanitisation
 * - Output escaping
 * - Password hashing and verification
 * - File upload validation
 * - Password strength enforcement
 * - Login attempt rate-limiting
 * - Random token generation
 * - Email / phone validation
 *
 * Every method is static for convenient access.
 */


class Security
{

    // ============================================================
    // CSRF TOKEN
    // ============================================================


    public static function generateCSRFToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {

            $_SESSION['csrf_token'] = bin2hex(
                random_bytes(CSRF_TOKEN_LENGTH)
            );

        }

        return $_SESSION['csrf_token'];
    }



    public static function validateCSRFToken(string $submittedToken): bool
    {

        if (
            empty($_SESSION['csrf_token']) ||
            empty($submittedToken)
        ) {

            return false;

        }


        return hash_equals(
            $_SESSION['csrf_token'],
            $submittedToken
        );

    }



    /**
     * Use inside forms:
     *
     * <?= Security::csrfField(); ?>
     *
     */

    public static function csrfField(): string
    {

        $token = self::generateCSRFToken();


        return sprintf(

            '<input type="hidden" name="csrf_token" value="%s">',

            htmlspecialchars(
                $token,
                ENT_QUOTES,
                'UTF-8'
            )

        );

    }



    public static function verifyCSRF(): void
    {

        $token = $_POST['csrf_token'] ?? '';


        if (!self::validateCSRFToken($token)) {


            http_response_code(419);


            unset($_SESSION['csrf_token']);


            die(
                'Security token mismatch. Please go back and submit again.'
            );

        }

    }



    // ============================================================
    // OUTPUT ESCAPING
    // ============================================================


    public static function escape(mixed $value): string
    {

        return htmlspecialchars(

            (string)($value ?? ''),

            ENT_QUOTES | ENT_SUBSTITUTE,

            'UTF-8'

        );

    }



    // ============================================================
    // PASSWORD HASHING
    // ============================================================


    public static function hashPassword(string $password): string
    {

        return password_hash(

            $password,

            PASSWORD_BCRYPT,

            ['cost'=>BCRYPT_COST]

        );

    }



    public static function verifyPassword(
        string $password,
        string $hash
    ): bool {

        return password_verify(
            $password,
            $hash
        );

    }



    // ============================================================
    // TOKEN GENERATION
    // ============================================================


    public static function generateToken(
        int $bytes = 32
    ): string {


        return bin2hex(
            random_bytes($bytes)
        );

    }



    // ============================================================
    // SECURITY HEADERS
    // ============================================================


    public static function sendSecurityHeaders(): void
    {

        if(headers_sent()){

            return;

        }


        header(
            'X-Content-Type-Options: nosniff'
        );


        header(
            'X-Frame-Options: SAMEORIGIN'
        );


        header(
            'X-XSS-Protection: 1; mode=block'
        );


        header(
            'Referrer-Policy: strict-origin-when-cross-origin'
        );

    }



    // ============================================================
    // INPUT SANITISATION
    // ============================================================



    public static function cleanString(string $input): string
    {

        return trim(
            htmlspecialchars(
                strip_tags($input),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
        );

    }



    public static function cleanEmail(string $email): string
    {

        return strtolower(
            trim(
                filter_var($email, FILTER_SANITIZE_EMAIL)
            )
        );

    }



    public static function cleanPhone(string $phone): string
    {

        return preg_replace('/[^0-9+]/', '', trim($phone));

    }



    public static function cleanTextarea(string $text): string
    {

        return trim(
            htmlspecialchars(
                strip_tags($text, '<p><br><strong><em><ul><ol><li>'),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            )
        );

    }



    // ============================================================
    // VALIDATION
    // ============================================================



    public static function isValidEmail(string $email): bool
    {

        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);

    }



    public static function validatePasswordStrength(string $password): array
    {

        $errors = [];



        if (strlen($password) < PASSWORD_MIN_LENGTH) {

            $errors[] = "Password must be at least " . PASSWORD_MIN_LENGTH . " characters long.";

        }



        if (!preg_match('/[A-Z]/', $password)) {

            $errors[] = "Password must contain at least one uppercase letter.";

        }



        if (!preg_match('/[a-z]/', $password)) {

            $errors[] = "Password must contain at least one lowercase letter.";

        }



        if (!preg_match('/[0-9]/', $password)) {

            $errors[] = "Password must contain at least one number.";

        }



        if (!preg_match('/[^A-Za-z0-9]/', $password)) {

            $errors[] = "Password must contain at least one special character.";

        }



        return [

            'valid' => empty($errors),

            'errors' => $errors

        ];

    }



    public static function validateImageUpload(array $file): array
    {

        if ($file['error'] !== UPLOAD_ERR_OK) {

            return ['valid' => false, 'error' => 'File upload error.'];

        }



        if ($file['size'] > MAX_FILE_SIZE) {

            return ['valid' => false, 'error' => 'File size exceeds maximum limit.'];

        }



        $allowedMimes = unserialize(ALLOWED_IMAGE_MIMES);

        $allowedExts = unserialize(ALLOWED_IMAGE_EXTS);



        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mimeType = finfo_file($finfo, $file['tmp_name']);

        finfo_close($finfo);



        if (!in_array($mimeType, $allowedMimes, true)) {

            return ['valid' => false, 'error' => 'Invalid file type.'];

        }



        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts, true)) {

            return ['valid' => false, 'error' => 'Invalid file extension.'];

        }



        return ['valid' => true, 'error' => ''];

    }



    // ============================================================
    // PASSWORD REHASH CHECK
    // ============================================================



    public static function needsRehash(string $hash): bool
    {

        $info = password_get_info($hash);



        return $info['algo'] !== PASSWORD_BCRYPT ||

               ($info['options']['cost'] ?? 0) < BCRYPT_COST;

    }



    // ============================================================
    // RATE LIMITING
    // ============================================================



    public static function isRateLimited(string $key): bool
    {

        if (!isset($_SESSION['_rate_limits'][$key])) {

            return false;

        }



        $data = $_SESSION['_rate_limits'][$key];



        if ($data['attempts'] >= MAX_LOGIN_ATTEMPTS &&

            (time() - $data['first_attempt']) < LOGIN_LOCKOUT_SECS) {

            return true;

        }



        return false;

    }



    public static function rateLimitSecondsRemaining(string $key): int
    {

        if (!isset($_SESSION['_rate_limits'][$key])) {

            return 0;

        }



        $data = $_SESSION['_rate_limits'][$key];

        $elapsed = time() - $data['first_attempt'];

        $remaining = LOGIN_LOCKOUT_SECS - $elapsed;



        return max(0, $remaining);

    }



    public static function recordFailedAttempt(string $key): void
    {

        if (!isset($_SESSION['_rate_limits'][$key])) {

            $_SESSION['_rate_limits'][$key] = [

                'attempts' => 0,

                'first_attempt' => time()

            ];

        }



        $_SESSION['_rate_limits'][$key]['attempts']++;

    }



    public static function clearRateLimit(string $key): void
    {

        unset($_SESSION['_rate_limits'][$key]);

    }



    // ============================================================
    // PASSWORD RESET TOKEN
    // ============================================================



    public static function generateResetToken(int $minutes = 60): array
    {

        $token = bin2hex(random_bytes(32));

        $expiresAt = date('Y-m-d H:i:s', time() + ($minutes * 60));



        return [

            'token' => $token,

            'expires_at' => $expiresAt

        ];

    }



    // ============================================================
    // TRANSACTION REFERENCE GENERATION
    // ============================================================



    public static function generateTransactionRef(): string
    {

        return 'TXN' . date('YmdHis') . strtoupper(bin2hex(random_bytes(4)));

    }


}