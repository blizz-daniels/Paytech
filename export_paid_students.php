<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

$user = require_role('lecturer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=exports');
    exit;
}

verify_csrf();

$paymentId = (int) ($_POST['payment_id'] ?? 0);
if ($paymentId <= 0) {
    header('Location: dashboard.php?section=exports&message=' . rawurlencode('Select a valid payment item to export.'));
    exit;
}

$payment = db_one(
    'SELECT id, payment_code, title, amount, department, level
     FROM payments
     WHERE id = ? AND lecturer_id = ?
     LIMIT 1',
    [$paymentId, (int) $user['id']]
);

if (!$payment) {
    header('Location: dashboard.php?section=exports&message=' . rawurlencode('Payment item not found for this lecturer.'));
    exit;
}

$rows = db_all(
    "SELECT t.receipt_code,
            t.paystack_reference,
            CONCAT(s.name, ' ', s.surname) AS student_name,
            s.matricnumber,
            s.department,
            s.level,
            t.amount,
            t.method,
            t.channel,
            t.payer_email,
            t.paid_at
     FROM transactions t
     INNER JOIN students s ON s.id = t.student_id
     WHERE t.payment_id = ?
     ORDER BY t.paid_at DESC",
    [$paymentId]
);

if (!$rows) {
    header('Location: dashboard.php?section=exports&message=' . rawurlencode('No paid student record exists for that payment item yet.'));
    exit;
}

$safeCode = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $payment['payment_code']) ?: ('payment-' . $paymentId);
$filename = 'paid-students-' . $safeCode . '-' . date('Ymd-His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, [
    'receipt',
    'paystack_reference',
    'student',
    'matricnumber',
    'student_department',
    'student_level',
    'payment_code',
    'payment_title',
    'item_amount',
    'amount_paid',
    'method',
    'channel',
    'payer_email',
    'date',
]);

foreach ($rows as $row) {
    fputcsv($output, [
        $row['receipt_code'],
        $row['paystack_reference'],
        $row['student_name'],
        $row['matricnumber'],
        $row['department'],
        $row['level'],
        $payment['payment_code'],
        $payment['title'],
        $payment['amount'],
        $row['amount'],
        $row['method'],
        $row['channel'],
        $row['payer_email'],
        $row['paid_at'],
    ]);
}

fclose($output);
exit;
