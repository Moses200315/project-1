<?php

/**
 * UserModel – Customer Account Operations
 * =========================================
 * Handles all database interactions for the `users` table:
 *   - Registration & authentication
 *   - Profile management
 *   - Password reset token lifecycle
 *   - Status management (active / inactive / banned)
 *   - Admin-facing user listing with search & pagination
 */

declare(strict_types=1);

class UserModel extends BaseModel
{
    protected string $table      = 'users';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Find a user by email address (case-insensitive).
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `users` WHERE LOWER(`email`) = LOWER(?) LIMIT 1",
            [trim($email)]
        );
    }

    /**
     * Find a user by their password-reset token (only if not yet expired).
     */
    public function findByResetToken(string $token): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `users`
             WHERE `reset_token` = ?
               AND `reset_token_expires` > NOW()
               AND `status` = 'active'
             LIMIT 1",
            [$token]
        );
    }

    /**
     * Find a user by the "remember me" token stored in their browser cookie.
     */
    public function findByRememberToken(string $token): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `users` WHERE `remember_token` = ? LIMIT 1",
            [$token]
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // REGISTRATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Register a new customer.
     * Hashes the password before insertion.
     *
     * @param array $data {first_name, last_name, email, password, phone?}
     * @return int  New user ID
     */
    public function register(array $data): int
    {
        return $this->create([
            'first_name'        => Security::cleanString($data['first_name']),
            'last_name'         => Security::cleanString($data['last_name']),
            'email'             => strtolower(trim($data['email'])),
            'password'          => Security::hashPassword($data['password']),
            'phone'             => isset($data['phone']) ? Security::cleanPhone($data['phone']) : null,
            'avatar'            => DEFAULT_AVATAR,
            'status'            => 'active',
            'email_verified_at' => $this->now(), // auto-verify for now (no email sending)
        ]);
    }

    /**
     * Verify login credentials.
     * Returns the full user row on success, null on failure.
     * Enforces that the account is active.
     *
     * @param string $email
     * @param string $password  Plain-text attempt
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);

        if ($user === null) {
            return null; // Unknown email
        }
        if ($user['status'] !== 'active') {
            return null; // Inactive or banned
        }
        if (!Security::verifyPassword($password, $user['password'])) {
            return null; // Wrong password
        }

        // Rehash password if the stored hash is outdated (cost change etc.)
        if (Security::needsRehash($user['password'])) {
            $this->update($user['id'], ['password' => Security::hashPassword($password)]);
        }

        return $user;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PROFILE MANAGEMENT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Update editable profile fields (name, phone, bio).
     *
     * @param int   $id
     * @param array $data {first_name, last_name, phone?, bio?}
     */
    public function updateProfile(int $id, array $data): bool
    {
        return $this->update($id, [
            'first_name' => Security::cleanString($data['first_name']),
            'last_name'  => Security::cleanString($data['last_name']),
            'phone'      => isset($data['phone'])  ? Security::cleanPhone($data['phone'])       : null,
            'bio'        => isset($data['bio'])     ? Security::cleanTextarea($data['bio'])      : null,
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * Update the user's avatar filename.
     */
    public function updateAvatar(int $id, string $filename): bool
    {
        return $this->update($id, [
            'avatar'     => $filename,
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * Change the user's password (hash before storing).
     */
    public function updatePassword(int $id, string $newPassword): bool
    {
        return $this->update($id, [
            'password'   => Security::hashPassword($newPassword),
            'updated_at' => $this->now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PASSWORD RESET
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Store a password-reset token against the user's email address.
     *
     * @param string $email
     * @param string $token      SHA-256 / random hex token
     * @param string $expiresAt  MySQL datetime string (Y-m-d H:i:s)
     * @return bool  false when the email is not found
     */
    public function setResetToken(string $email, string $token, string $expiresAt): bool
    {
        $affected = $this->db->update('users', [
            'reset_token'         => $token,
            'reset_token_expires' => $expiresAt,
            'updated_at'          => $this->now(),
        ], 'email = ?', [strtolower(trim($email))]);

        return $affected > 0;
    }

    /**
     * Clear the reset token after successful password change.
     */
    public function clearResetToken(int $id): bool
    {
        return $this->update($id, [
            'reset_token'         => null,
            'reset_token_expires' => null,
            'updated_at'          => $this->now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SESSION / LOGIN TRACKING
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Record the timestamp of the most recent successful login.
     */
    public function updateLastLogin(int $id): void
    {
        $this->update($id, ['last_login' => $this->now()]);
    }

    /**
     * Store or clear the "remember me" token.
     */
    public function setRememberToken(int $id, ?string $token): void
    {
        $this->update($id, ['remember_token' => $token, 'updated_at' => $this->now()]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // STATUS MANAGEMENT  (admin use)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Change the account status: 'active' | 'inactive' | 'banned'.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['active', 'inactive', 'banned'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        return $this->update($id, ['status' => $status, 'updated_at' => $this->now()]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN LISTING
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return a paginated, searchable list of all customers for the admin panel.
     * Includes the user's active subscription plan name.
     *
     * @param int    $page
     * @param int    $perPage
     * @param string $search   Filter by name or email
     * @param string $status   Filter by status ('', 'active', 'inactive', 'banned')
     */
    public function getPaginatedAdmin(int $page = 1, int $perPage = ADMIN_ITEMS_PER_PAGE, string $search = '', string $status = ''): array
    {
        $where  = [];
        $params = [];

        if ($search !== '') {
            $where[]  = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $like     = '%' . $search . '%';
            $params   = array_merge($params, [$like, $like, $like]);
        }
        if ($status !== '') {
            $where[]  = "u.status = ?";
            $params[] = $status;
        }

        $whereSQL = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `users` u" . $whereSQL,
            $params
        );

        $pager  = paginate($total, $perPage, $page, url('admin/users'));
        $offset = $pager['offset'];

        // Data query – join with subscriptions to show current plan
        $rows = $this->db->fetchAll(
            "SELECT u.*,
                    sp.name  AS plan_name,
                    s.status AS sub_status,
                    s.ends_at AS sub_ends_at
             FROM `users` u
             LEFT JOIN `subscriptions` s
                    ON s.user_id = u.id AND s.status = 'active'
             LEFT JOIN `subscription_plans` sp ON sp.id = s.plan_id
             {$whereSQL}
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // DASHBOARD STATISTICS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return aggregate customer statistics for the admin dashboard.
     */
    public function getStats(): array
    {
        return [
            'total'    => $this->count(),
            'active'   => $this->count(['status' => 'active']),
            'inactive' => $this->count(['status' => 'inactive']),
            'banned'   => $this->count(['status' => 'banned']),
            'new_this_month' => (int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM `users`
                 WHERE MONTH(created_at) = MONTH(NOW())
                   AND YEAR(created_at)  = YEAR(NOW())"
            ),
        ];
    }
}
