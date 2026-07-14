<?php

/**
 * PaymentController – Payment History
 * =====================================
 * index       – customer payment history
 * view/{id}   – single payment receipt
 * adminIndex  – admin payment list with filters
 * adminView   – admin payment detail
 */

declare(strict_types=1);

class PaymentController extends BaseController
{
    private PaymentModel $paymentModel;
    private SubscriptionModel $subModel;

    public function __construct()
    {
        $this->paymentModel = new PaymentModel();
        $this->subModel = new SubscriptionModel();
    }

    /** GET /payments/index */
    public function index(): void
    {
        $this->requireCustomer();
        $userId = $this->userId();

        $result = $this->paymentModel->getByUser($userId, $this->currentPage());

        $this->view("customer/payments/index", [
            "pageTitle" => "Payment History",
            "rows" => $result["rows"],
            "pager" => $result["pager"],
            "permissions" => $this->subModel->getUserPermissions($userId),
        ]);
    }

    /** GET /payments/receipt/{id} */
    public function receipt(string $id = "0"): void
    {
        $this->requireCustomer();
        $payId = $this->resolveId($id);
        $payment = $this->paymentModel->findById($payId);

        if (!$payment || (int) $payment["user_id"] !== $this->userId()) {
            $this->abort404("Payment record not found.");
        }

        $this->view("customer/payments/view", [
            "pageTitle" => "Receipt – " . e($payment["transaction_ref"]),
            "payment" => $payment,
        ]);
    }

    /** GET /payments/view/{id} - Alias for receipt to avoid BaseController::view() conflict */
    public function viewPayment(string $id = "0"): void
    {
        $this->receipt($id);
    }

    /** GET /payments/adminIndex */
    public function adminIndex(): void
    {
        $this->requireAdmin();

        $result = $this->paymentModel->getPaginatedAdmin(
            $this->currentPage(),
            ADMIN_ITEMS_PER_PAGE,
            $this->query("status"),
            $this->query("provider"),
        );

        $this->view("admin/payments/index", [
            "pageTitle" => "Payment Records",
            "rows" => $result["rows"],
            "pager" => $result["pager"],
            "stats" => $this->paymentModel->getStats(),
            "byProvider" => $this->paymentModel->getRevenueByProvider(),
            "status" => $this->query("status"),
            "provider" => $this->query("provider"),
            "providers" => array_merge(['Card'], array_keys(unserialize(MOMO_PROVIDERS))),
        ]);
    }

    /** GET /payments/adminView/{id} */
    public function adminView(string $id = "0"): void
    {
        $this->requireAdmin();
        $payId = $this->resolveId($id);
        $payment = $this->paymentModel->findById($payId);

        if (!$payment) {
            $this->abort404("Payment not found.");
        }

        $this->view("admin/payments/view", [
            "pageTitle" => "Payment: " . e($payment["transaction_ref"]),
            "payment" => $payment,
            "gateway" => json_decode(
                $payment["gateway_response"] ?? "{}",
                true,
            ),
        ]);
    }

    /** POST /payments/refund/{id}  (admin) */
    public function refund(string $id = "0"): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();
        $payId = $this->resolveId($id);
        $payment = $this->paymentModel->findById($payId);

        if (!$payment || $payment["status"] !== "success") {
            $this->error("Only successful payments can be refunded.");
            $this->redirectTo(url("payments/adminIndex"));
        }

        $this->paymentModel->markRefunded($payId);
        $this->success("Payment marked as refunded.");
        $this->redirectTo(url("payments/adminView/" . $payId));
    }
}
