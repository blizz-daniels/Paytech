<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

$user = require_role('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=payments');
    exit;
}

verify_csrf();

$paymentId = (int) ($_POST['payment_id'] ?? 0);
$method = trim((string) ($_POST['method'] ?? 'Card'));
$allowedMethods = ['Card', 'Bank transfer', 'Cash office'];

if (!in_array($method, $allowedMethods, true)) {
    $method = 'Card';
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

db()->beginTransaction();

try {
    $stmt = db()->prepare(
        'INSERT INTO transactions (receipt_code, payment_id, student_id, amount, method, paid_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        receipt_code(),
        (int) $payment['id'],
        (int) $user['id'],
        (float) $payment['amount'],
        $method,
    ]);

    db()->commit();
    header('Location: dashboard.php?section=receipts&message=' . rawurlencode('Payment recorded and receipt generated.'));
    exit;
} catch (Throwable $exception) {
    db()->rollBack();
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Could not record payment. Try again.'));
    exit;
}
