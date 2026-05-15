<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=records');
    exit;
}

verify_csrf();

$transactions = db_all(
    "SELECT t.receipt_code,
            CONCAT(s.name, ' ', s.surname) AS student_name,
            s.matricnumber,
            s.department,
            s.level,
            p.title AS payment_title,
            t.amount,
            t.method,
            t.paid_at
     FROM transactions t
     INNER JOIN payments p ON p.id = t.payment_id
     INNER JOIN students s ON s.id = t.student_id
     ORDER BY t.paid_at DESC"
);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="departmentpay-transactions.csv"');

$output = fopen('php://output', 'w');
fputcsv($output, ['receipt', 'student', 'matricnumber', 'department', 'level', 'payment', 'amount', 'method', 'date']);

foreach ($transactions as $transaction) {
    fputcsv($output, [
        $transaction['receipt_code'],
        $transaction['student_name'],
        $transaction['matricnumber'],
        $transaction['department'],
        $transaction['level'],
        $transaction['payment_title'],
        $transaction['amount'],
        $transaction['method'],
        $transaction['paid_at'],
    ]);
}

fclose($output);
exit;
