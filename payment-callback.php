<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/models.php';
require_once __DIR__ . '/includes/Payment.php';

Auth::startSession();
Auth::requireLogin();

$status = $_GET['status'] ?? '';
$txId   = $_GET['transaction_id'] ?? ($_GET['tx_id'] ?? '');

if ($status === 'cancelled') {
    flash('error', 'Payment was cancelled.');
    redirect('my-subscriptions.php');
}

if ($txId === '') {
    flash('error', 'Missing transaction reference.');
    redirect('my-subscriptions.php');
}

$result = Payment::verifyFlutterwave($txId);

if (!$result['success']) {
    flash('error', $result['message']);
    redirect('my-subscriptions.php');
}

$db = Database::getConnection();
$stmt = $db->prepare('SELECT * FROM subscriptions WHERE payment_ref = ? AND user_id = ?');
$stmt->execute([$result['payment_ref'], Auth::user()['id']]);
$subscription = $stmt->fetch();

if (!$subscription) {
    $stmt = $db->prepare('SELECT * FROM subscriptions WHERE user_id = ? AND payment_status = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute([Auth::user()['id'], 'unpaid']);
    $subscription = $stmt->fetch();
}

if (!$subscription) {
    flash('error', 'Subscription not found for this payment.');
    redirect('my-subscriptions.php');
}

if (($subscription['payment_status'] ?? '') === 'paid') {
    flash('success', 'Payment verified! Your subscription is confirmed.');
    redirect('my-subscriptions.php?id=' . $subscription['id']);
}

$paid = Payment::markPaid(
    (int) $subscription['id'],
    $result['method'],
    $result['transaction_id'],
    $result['payment_ref'] ?: Payment::generateRef((int) $subscription['id'])
);

if ($paid) {
    flash('success', 'Payment verified! Your subscription is confirmed.');
    redirect('my-subscriptions.php?id=' . $subscription['id']);
}

flash('error', 'Payment verification failed or already processed.');
redirect('my-subscriptions.php?id=' . $subscription['id']);
