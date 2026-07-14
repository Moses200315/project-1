<?php

/**
 * AdminModel – Back-Office Administrator Operations
 * ===================================================
 * Manages the `admins` table: authentication, profile updates,
 * password changes, and last-login tracking.
 */

declare(strict_types=1);

class AdminModel extends BaseModel
{
    protected string $table      = 'admins';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // AUTHENTICATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Find an admin by email address (case-insensitive).
     */
    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `admins` WHERE LOWER(`email`) = LOWER(?) LIMIT 1",
            [trim($email)]
        );
    }

    /**
     * Verify admin login credentials.
     * Returns the admin row on success, null on failure.
     *
     * @param string $email
     * @param string $password  Plain-text attempt
     */
    public function verifyCredentials(string $email, string $password): ?array
    {
        $admin = $this->findByEmail($email);

        if ($admin === null)                          return null;
        if ($admin['status'] !== 'active')            return null;
        if (!Security::verifyPassword($password, $admin['password'])) return null;

        // Upgrade hash cost if needed
        if (Security::needsRehash($admin['password'])) {
            $this->update($admin['id'], [
                'password' => Security::hashPassword($password)
            ]);
        }

        return $admin;
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PROFILE MANAGEMENT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Update editable admin profile fields.
     *
     * @param int   $id
     * @param array $data {first_name, last_name}
     */
    public function updateProfile(int $id, array $data): bool
    {
        return $this->update($id, [
            'first_name' => Security::cleanString($data['first_name']),
            'last_name'  => Security::cleanString($data['last_name']),
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * Update the admin's avatar filename.
     */
    public function updateAvatar(int $id, string $filename): bool
    {
        return $this->update($id, [
            'avatar'     => $filename,
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * Change the admin's password.
     */
    public function updatePassword(int $id, string $newPassword): bool
    {
        return $this->update($id, [
            'password'   => Security::hashPassword($newPassword),
            'updated_at' => $this->now(),
        ]);
    }

    /**
     * Record the last successful login timestamp.
     */
    public function updateLastLogin(int $id): void
    {
        $this->update($id, ['last_login' => $this->now()]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // LISTING (super-admin use)
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return all admin accounts ordered by role then name.
     */
    public function getAllAdmins(): array
    {
        return $this->db->fetchAll(
            "SELECT id, first_name, last_name, email, avatar, role, status, last_login, created_at
             FROM `admins`
             ORDER BY FIELD(role, 'super_admin', 'admin'), first_name ASC"
        );
    }

    /**
     * Change an admin account's status (active / inactive).
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            return false;
        }
        return $this->update($id, ['status' => $status, 'updated_at' => $this->now()]);
    }
}
