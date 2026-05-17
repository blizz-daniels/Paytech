<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/paystack.php';

$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=payments');
    exit;
}

verify_csrf();

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$payerEmail = trim((string) ($_POST['payer_email'] ?? ''));

if (!filter_var($payerEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Enter a valid email address before paying with Paystack.'));
    exit;
}

if (!paystack_is_configured()) {
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Paystack is not configured yet. Add PAYSTACK_SECRET_KEY in config.php or your environment.'));
    exit;
}

$payment = db_one(
    "SELECT *
     FROM payments
     WHERE id = ?
       AND status = 'active'
       AND department = ?
       AND (level = ? OR level = 'All levels')
     LIMIT 1",
    [$paymentId, $user['department'], $user['level']]
);

if (!$payment) {
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Payment item is not available for your profile.'));
    exit;
}

$existing = db_one(
    'SELECT id FROM transactions WHERE payment_id = ? AND student_id = ? LIMIT 1',
    [$paymentId, (int) $user['id']]
);

if ($existing) {
    header('Location: dashboard.php?section=receipts&message=' . rawurlencode('Receipt already exists for that payment.'));
    exit;
}

$reference = paystack_reference();
$amountKobo = (int) round(((float) $payment['amount']) * 100);
$callbackUrl = PAYSTACK_CALLBACK_URL !== '' ? PAYSTACK_CALLBACK_URL : current_app_url() . '/paystack_callback.php';

try {
    $response = paystack_initialize_transaction([
        'email' => $payerEmail,
        'amount' => (string) $amountKobo,
        'currency' => PAYSTACK_CURRENCY,
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'metadata' => [
            'student_id' => (int) $user['id'],
            'matricnumber' => $user['matricnumber'],
            'student_name' => $user['name'] . ' ' . $user['surname'],
            'payment_id' => (int) $payment['id'],
            'payment_title' => $payment['title'],
            'department' => $user['department'],
            'level' => $user['level'],
        ],
    ]);

    $data = $response['data'] ?? [];
    $authorizationUrl = (string) ($data['authorization_url'] ?? '');
    $accessCode = (string) ($data['access_code'] ?? '');

    if ($authorizationUrl === '') {
        throw new RuntimeException('Paystack did not return a checkout URL.');
    }

    $stmt = db()->prepare(
        'INSERT INTO payment_attempts (reference, payment_id, student_id, amount, payer_email, authorization_url, access_code, status, raw_response)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $reference,
        (int) $payment['id'],
        (int) $user['id'],
        (float) $payment['amount'],
        $payerEmail,
        $authorizationUrl,
        $accessCode,
        'initialized',
        json_encode($response, JSON_THROW_ON_ERROR),
    ]);

    header('Location: ' . $authorizationUrl);
    exit;
} catch (Throwable $exception) {
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Paystack checkout could not start: ' . $exception->getMessage()));
    exit;
}
