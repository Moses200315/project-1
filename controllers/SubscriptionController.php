<?php

/**
 * SubscriptionController – Plan Selection & Mobile Money Payment
 * ===============================================================
 * index           – public pricing / plans page
 * checkout/{id}   – GET: Mobile Money payment form
 * processPayment  – POST: sandbox payment processing
 * success         – payment success confirmation
 * history         – customer subscription history
 * cancel/{id}     – POST: cancel active subscription
 * adminIndex      – admin subscription management list
 */

declare(strict_types=1);

class SubscriptionController extends BaseController
{
    private SubscriptionModel $subModel;
    private PaymentModel $paymentModel;
    private NotificationModel $notifModel;

    public function __construct()
    {
        $this->subModel = new SubscriptionModel();
        $this->paymentModel = new PaymentModel();
        $this->notifModel = new NotificationModel();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PLANS PAGE  (public)
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /subscriptions/index */
    public function index(): void
    {
        $userId = $this->userId();
        $activeSub = $userId
            ? $this->subModel->getActiveSubscription($userId)
            : null;

        $this->view("customer/subscriptions/index", [
            "pageTitle" => "Subscription Plans – " . APP_NAME,
            "plans" => $this->subModel->getAllPlans(),
            "activeSub" => $activeSub,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // CHECKOUT
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /subscriptions/checkout/{planId} */
    public function checkout(string $planId = "0"): void
    {
        $this->requireCustomer();
        $pid = $this->resolveId($planId);
        $plan = $this->subModel->findPlanById($pid);

        if (!$plan || $plan["status"] !== "active") {
            $this->error("That subscription plan is not available.");
            $this->redirectTo(url("subscriptions"));
        }

        // Free plan: activate immediately without payment
        if ((float) $plan["price"] == 0.0) {
            $this->activateFreePlan((int) $plan["id"], $plan);
        }

        // Decode JSON features for the summary card
        $features = json_decode($plan["features"] ?? "[]", true) ?: [];

        $this->view("customer/subscriptions/checkout", [
            "pageTitle" => "Subscribe to " . e($plan["name"]),
            "plan" => $plan,
            "features" => $features,
            "paymentMethods" => unserialize(PAYMENT_METHODS),
            "providers" => unserialize(MOMO_PROVIDERS),
            "userPhone" => Session::userField("phone", ""),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // PROCESS PAYMENT
    // ══════════════════════════════════════════════════════════════════════════

    /** POST /subscriptions/processPayment */
    public function processPayment(): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();

        $planId = $this->intInput("plan_id");
        $paymentMethod = $this->post("payment_method");

        // ── Validation ────────────────────────────────────────────────────────
        $plan = $this->subModel->findPlanById($planId);
        if (!$plan || $plan["status"] !== "active") {
            $this->error("Invalid subscription plan.");
            $this->redirectTo(url("subscriptions"));
        }

        $validMethods = array_keys(unserialize(PAYMENT_METHODS));
        if (!in_array($paymentMethod, $validMethods, true)) {
            $this->error("Please select a valid payment method.");
            $this->redirectTo(url("subscriptions/checkout/" . $planId));
        }

        $userId = $this->userId();
        $provider = null;
        $phone = null;
        $cardData = null;

        // Process based on payment method
        if ($paymentMethod === 'card') {
            $cardNumber = $this->post("card_number");
            $cardExpiry = $this->post("card_expiry");
            $cardCvv = $this->post("card_cvv");

            if (empty($cardNumber) || empty($cardExpiry) || empty($cardCvv)) {
                $this->error("Please fill in all card details.");
                $this->redirectTo(url("subscriptions/checkout/" . $planId));
            }

            // Store last 4 digits and expiry for reference
            $cardData = json_encode([
                'last4' => substr($cardNumber, -4),
                'expiry' => $cardExpiry
            ]);
            $provider = 'Card';
        } elseif ($paymentMethod === 'mobile') {
            $provider = $this->post("provider");
            $phone = Security::cleanPhone($this->post("phone_number"));

            $validProviders = array_keys(unserialize(MOMO_PROVIDERS));
            if (!in_array($provider, $validProviders, true)) {
                $this->error("Please select a valid Mobile Money provider.");
                $this->redirectTo(url("subscriptions/checkout/" . $planId));
            }

            if (empty($phone) || strlen(preg_replace("/\D/", "", $phone)) < 9) {
                $this->error("Please enter a valid phone number.");
                $this->redirectTo(url("subscriptions/checkout/" . $planId));
            }
        }

        // ── Create pending subscription + payment ─────────────────────────────
        $subId = $this->subModel->createPending($userId, $planId);
        $payData = $this->paymentModel->initiate([
            "user_id" => $userId,
            "subscription_id" => $subId,
            "amount" => $plan["price"],
            "provider" => $provider,
            "phone_number" => $phone,
            "card_data" => $cardData ? json_encode($cardData) : null,
        ]);

        // ── Run sandbox simulation ────────────────────────────────────────────
        $result = $this->paymentModel->processSandbox(
            $payData["transaction_ref"],
        );

        if ($result["success"]) {
            // Activate the subscription
            $this->subModel->activate($subId, (int) $plan["duration_days"]);

            // Notify user
            $activeSub = $this->subModel->findById($subId);
            $this->notifModel->notifyPaymentSuccess(
                $userId,
                (float) $plan["price"],
                $provider,
            );
            $this->notifModel->notifySubscriptionActivated(
                $userId,
                $plan["name"],
                $activeSub["ends_at"],
            );

            // Store success data for the confirmation page
            Session::set("_payment_success", [
                "plan_name" => $plan["name"],
                "amount" => $plan["price"],
                "provider" => $provider,
                "ref" => $payData["transaction_ref"],
                "ends_at" => $activeSub["ends_at"],
            ]);

            $this->redirectTo(url("subscriptions/confirmed"));
        } else {
            $this->notifModel->notifyPaymentFailed(
                $userId,
                (float) $plan["price"],
                $result["message"],
            );
            $this->error(
                "Payment failed: " . $result["message"] . " Please try again.",
            );
            $this->redirectTo(url("subscriptions/checkout/" . $planId));
        }
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SUCCESS PAGE
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /subscriptions/confirmed */
    public function confirmed(): void
    {
        $this->requireCustomer();

        $data = Session::get("_payment_success");
        if (!$data) {
            $this->redirectTo(url("customer/dashboard"));
        }
        Session::forget("_payment_success");

        $this->view("customer/subscriptions/success", [
            "pageTitle" => "Payment Successful!",
            "payment" => $data,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HISTORY & CANCEL
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /subscriptions/history */
    public function history(): void
    {
        $this->requireCustomer();
        $userId = $this->userId();

        $this->view("customer/subscriptions/history", [
            "pageTitle" => "Subscription History",
            "history" => $this->subModel->getSubscriptionHistory($userId),
            "activeSub" => $this->subModel->getActiveSubscription($userId),
            "permissions" => $this->subModel->getUserPermissions($userId),
        ]);
    }

    /** POST /subscriptions/cancel/{id} */
    public function cancel(string $id = "0"): void
    {
        $this->requireCustomer();
        $this->verifyCsrf();
        $subId = $this->resolveId($id);

        $cancelled = $this->subModel->cancel($subId, $this->userId());

        $cancelled
            ? $this->success(
                "Your subscription has been cancelled. You retain access until the end of the billing period.",
            )
            : $this->error("Subscription not found or already cancelled.");

        $this->redirectTo(url("subscriptions/history"));
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ADMIN
    // ══════════════════════════════════════════════════════════════════════════

    /** GET /subscriptions/adminIndex */
    public function adminIndex(): void
    {
        $this->requireAdmin();

        $result = $this->subModel->getPaginatedAdmin(
            $this->currentPage(),
            ADMIN_ITEMS_PER_PAGE,
            $this->query("status"),
        );

        $this->view("admin/subscriptions/index", [
            "pageTitle" => "Subscription Management",
            "rows" => $result["rows"],
            "pager" => $result["pager"],
            "stats" => $this->subModel->getStats(),
            "byPlan" => $this->subModel->getCountByPlan(),
            "status" => $this->query("status"),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════════════════════

    /** Activate a free plan immediately without a payment transaction */
    private function activateFreePlan(int $planId, array $plan): never
    {
        $userId = $this->userId();
        $subId = $this->subModel->createPending($userId, $planId);
        $this->subModel->activate($subId, (int) $plan["duration_days"]);

        $activeSub = $this->subModel->findById($subId);
        $this->notifModel->notifySubscriptionActivated(
            $userId,
            $plan["name"],
            $activeSub["ends_at"],
        );

        $this->success(
            "Your free plan has been activated. Enjoy " . APP_NAME . "!",
        );
        $this->redirectTo(url("customer/dashboard"));
    }
}
