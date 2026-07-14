<?php

/**
 * PaymentModel – Payment Operations
 * ===================================
 * Manages the `payments` table and processes card payments.
 *
 * Payment Flow:
 *   1. Controller calls initiate()     → creates PENDING payment record
 *   2. Controller calls processSandbox() → simulates payment response
 *   3. On success: activate subscription, mark payment SUCCESS
 *   4. On failure: mark payment FAILED, notify user
 */

declare(strict_types=1);

class PaymentModel extends BaseModel
{
    protected string $table      = 'payments';
    protected string $primaryKey = 'id';

    // ══════════════════════════════════════════════════════════════════════════
    // LOOKUP
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Find a payment by transaction reference.
     */
    public function findByRef(string $transactionRef): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM `payments` WHERE `transaction_ref` = ? LIMIT 1",
            [$transactionRef]
        );
    }

    /**
     * Return paginated payment history for a specific customer.
     */
    public function getByUser(int $userId, int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `payments` WHERE `user_id` = ?",
            [$userId]
        );

        $pager  = paginate($total, $perPage, $page, url('payments'));
        $offset = $pager['offset'];

        $rows = $this->db->fetchAll(
            "SELECT p.*, sp.name AS plan_name
             FROM `payments` p
             LEFT JOIN `subscriptions` s  ON s.id  = p.subscription_id
             LEFT JOIN `subscription_plans` sp ON sp.id = s.plan_id
             WHERE p.user_id = ?
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            [$userId, $perPage, $offset]
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    /**
     * Return paginated payment list for the admin panel with optional filters.
     *
     * @param int    $page
     * @param int    $perPage
     * @param string $status    '' | 'pending' | 'success' | 'failed' | 'refunded'
     * @param string $provider  '' | 'MTN' | 'Vodafone' | 'AirtelTigo'
     */
    public function getPaginatedAdmin(
        int    $page     = 1,
        int    $perPage  = ADMIN_ITEMS_PER_PAGE,
        string $status   = '',
        string $provider = ''
    ): array {
        $where  = [];
        $params = [];

        if ($status !== '') {
            $where[]  = "p.status = ?";
            $params[] = $status;
        }
        if ($provider !== '') {
            $where[]  = "p.provider = ?";
            $params[] = $provider;
        }

        $whereSQL = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `payments` p" . $whereSQL,
            $params
        );

        $pager  = paginate($total, $perPage, $page, url('admin/payments'));
        $offset = $pager['offset'];

        $rows = $this->db->fetchAll(
            "SELECT p.*,
                    CONCAT(u.first_name,' ',u.last_name) AS user_name,
                    u.email   AS user_email,
                    sp.name   AS plan_name
             FROM `payments` p
             JOIN `users` u ON u.id = p.user_id
             LEFT JOIN `subscriptions` s   ON s.id  = p.subscription_id
             LEFT JOIN `subscription_plans` sp ON sp.id = s.plan_id
             {$whereSQL}
             ORDER BY p.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return ['rows' => $rows, 'pager' => $pager];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // INITIATE PAYMENT
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create a PENDING payment record and return the transaction reference.
     *
     * @param array $data {
     *   user_id, subscription_id, amount, provider, phone_number
     * }
     * @return array {id: int, transaction_ref: string}
     */
    public function initiate(array $data): array
    {
        $ref = Security::generateTransactionRef();

        $paymentData = [
            'user_id'         => (int) $data['user_id'],
            'subscription_id' => (int) $data['subscription_id'],
            'transaction_ref' => $ref,
            'amount'          => (float) $data['amount'],
            'currency'        => MOMO_CURRENCY,
            'provider'        => Security::cleanString($data['provider']),
            'status'          => 'pending',
        ];

        // Handle payment method specific fields
        if (isset($data['phone_number'])) {
            $paymentData['payment_method'] = 'mobile_money';
            $paymentData['phone_number'] = Security::cleanPhone($data['phone_number']);
        } elseif (isset($data['card_data'])) {
            $paymentData['payment_method'] = 'card';
            $paymentData['card_data'] = $data['card_data'];
        } else {
            $paymentData['payment_method'] = 'mobile_money';
        }

        $id = $this->create($paymentData);

        return ['id' => $id, 'transaction_ref' => $ref];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PAYMENT SANDBOX SIMULATION
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Simulate a payment processing response.
     *
     * In a real integration this would call the payment gateway's API.
     * Here we use configurable probability to return success/failure,
     * plus realistic response payloads.
     *
     * @param string $transactionRef
     * @return array {
     *   success: bool,
     *   status:  'success' | 'failed',
     *   message: string,
     *   gateway_response: array
     * }
     */
    public function processSandbox(string $transactionRef): array
    {
        $payment = $this->findByRef($transactionRef);

        if ($payment === null) {
            return [
                'success'          => false,
                'status'           => 'failed',
                'message'          => 'Transaction reference not found.',
                'gateway_response' => [],
            ];
        }

        if ($payment['status'] !== 'pending') {
            return [
                'success'          => $payment['status'] === 'success',
                'status'           => $payment['status'],
                'message'          => 'Transaction already processed.',
                'gateway_response' => json_decode($payment['gateway_response'] ?? '{}', true),
            ];
        }

        // Determine success based on sandbox success rate
        $isSuccess = (mt_rand(1, 100) / 100) <= MOMO_SANDBOX_SUCCESS_RATE;

        $provider  = $payment['provider'];
        $amount    = number_format((float) $payment['amount'], 2);
        $phone     = $payment['phone_number'];
        $timestamp = date('c');

        // Build a realistic-looking provider response
        if ($isSuccess) {
            $gatewayResponse = $this->buildSuccessResponse($provider, $transactionRef, $amount, $phone, $timestamp);
            $this->markSuccess((int) $payment['id'], $gatewayResponse);

            return [
                'success'          => true,
                'status'           => 'success',
                'message'          => "Payment of {$amount} GHS via {$provider} was successful.",
                'gateway_response' => $gatewayResponse,
                'payment_id'       => (int) $payment['id'],
            ];
        } else {
            $gatewayResponse = $this->buildFailureResponse($provider, $transactionRef, $amount, $phone, $timestamp);
            $this->markFailed((int) $payment['id'], $gatewayResponse);

            return [
                'success'          => false,
                'status'           => 'failed',
                'message'          => "Payment failed. {$gatewayResponse['message']}",
                'gateway_response' => $gatewayResponse,
                'payment_id'       => (int) $payment['id'],
            ];
        }
    }

    /**
     * Mark a payment as SUCCESS.
     */
    public function markSuccess(int $id, array $gatewayResponse = []): bool
    {
        return $this->update($id, [
            'status'           => 'success',
            'gateway_response' => json_encode($gatewayResponse),
            'paid_at'          => $this->now(),
            'updated_at'       => $this->now(),
        ]);
    }

    /**
     * Mark a payment as FAILED.
     */
    public function markFailed(int $id, array $gatewayResponse = []): bool
    {
        return $this->update($id, [
            'status'           => 'failed',
            'gateway_response' => json_encode($gatewayResponse),
            'updated_at'       => $this->now(),
        ]);
    }

    /**
     * Mark a payment as REFUNDED.
     */
    public function markRefunded(int $id): bool
    {
        return $this->update($id, [
            'status'     => 'refunded',
            'updated_at' => $this->now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN STATISTICS
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Return aggregate payment stats for the admin dashboard.
     */
    public function getStats(): array
    {
        $revenue = $this->db->fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN status='success' THEN amount ELSE 0 END), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN status='success'
                                   AND MONTH(paid_at) = MONTH(NOW())
                                   AND YEAR(paid_at)  = YEAR(NOW())
                             THEN amount ELSE 0 END), 0)                             AS monthly_revenue
             FROM `payments`"
        );

        return [
            'total'           => $this->count(),
            'success'         => $this->count(['status' => 'success']),
            'pending'         => $this->count(['status' => 'pending']),
            'failed'          => $this->count(['status' => 'failed']),
            'total_revenue'   => (float) ($revenue['total_revenue']   ?? 0),
            'monthly_revenue' => (float) ($revenue['monthly_revenue'] ?? 0),
        ];
    }

    /**
     * Return monthly revenue totals for the current year (for chart data).
     * Returns an array of 12 items, one per month.
     */
    public function getMonthlyRevenue(int $year = 0): array
    {
        if ($year === 0) {
            $year = (int) date('Y');
        }

        $rows = $this->db->fetchAll(
            "SELECT MONTH(paid_at) AS month, COALESCE(SUM(amount), 0) AS revenue
             FROM `payments`
             WHERE status = 'success'
               AND YEAR(paid_at) = ?
             GROUP BY MONTH(paid_at)
             ORDER BY month ASC",
            [$year]
        );

        // Build a 12-month array (fill gaps with 0)
        $monthly = array_fill(1, 12, 0.0);
        foreach ($rows as $row) {
            $monthly[(int) $row['month']] = (float) $row['revenue'];
        }

        return $monthly;
    }

    /**
     * Return payment totals grouped by provider for pie/donut charts.
     */
    public function getRevenueByProvider(): array
    {
        return $this->db->fetchAll(
            "SELECT provider, COUNT(*) AS tx_count, SUM(amount) AS total
             FROM `payments`
             WHERE status = 'success'
             GROUP BY provider
             ORDER BY total DESC"
        );
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SANDBOX RESPONSE BUILDERS  (private)
    // ══════════════════════════════════════════════════════════════════════════

    private function buildSuccessResponse(
        string $provider,
        string $ref,
        string $amount,
        string $phone,
        string $ts
    ): array {
        $providerCodes = [
            'MTN'        => ['code' => 'MTN_MM_SUCCESS', 'name' => 'MTN Mobile Money'],
            'Vodafone'   => ['code' => 'VOD_CASH_SUCCESS', 'name' => 'Vodafone Cash'],
            'AirtelTigo' => ['code' => 'AT_MONEY_SUCCESS', 'name' => 'AirtelTigo Money'],
        ];

        $info = $providerCodes[$provider] ?? ['code' => 'MOMO_SUCCESS', 'name' => $provider];

        return [
            'status'              => 'success',
            'code'                => $info['code'],
            'message'             => 'Transaction completed successfully.',
            'provider'            => $info['name'],
            'transaction_ref'     => $ref,
            'provider_ref'        => 'PRV-' . strtoupper(bin2hex(random_bytes(4))),
            'amount'              => $amount,
            'currency'            => CURRENCY_CODE,
            'phone_number'        => $phone,
            'merchant_id'         => 'MEALKIT_MERCHANT_001',
            'timestamp'           => $ts,
            'sandbox'             => true,
        ];
    }

    private function buildFailureResponse(
        string $provider,
        string $ref,
        string $amount,
        string $phone,
        string $ts
    ): array {
        $reasons = [
            'Insufficient funds in wallet.',
            'Transaction declined by network. Please try again.',
            'Phone number not registered for Mobile Money.',
            'Daily transaction limit exceeded.',
            'Network timeout. Please retry.',
        ];

        return [
            'status'          => 'failed',
            'code'            => 'MOMO_FAILED',
            'message'         => $reasons[array_rand($reasons)],
            'provider'        => $provider,
            'transaction_ref' => $ref,
            'amount'          => $amount,
            'currency'        => MOMO_CURRENCY,
            'phone_number'    => $phone,
            'timestamp'       => $ts,
            'sandbox'         => true,
        ];
    }
}
