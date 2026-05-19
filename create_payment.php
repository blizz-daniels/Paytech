<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

$user = require_role('lecturer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=create');
    exit;
}

verify_csrf();

$lecturer = db_one('SELECT paystack_subaccount_code, paystack_subaccount_active FROM lecturers WHERE id = ? LIMIT 1', [(int) $user['id']]);
$subaccountCode = trim((string) ($lecturer['paystack_subaccount_code'] ?? ''));
$subaccountActive = (int) ($lecturer['paystack_subaccount_active'] ?? 0) === 1;

if ($subaccountCode === '' || !$subaccountActive) {
    header('Location: dashboard.php?section=account&message=' . rawurlencode('Set up your settlement account in My account before publishing payment items.'));
    exit;
}

$title = trim((string) ($_POST['title'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$amount = (float) ($_POST['amount'] ?? 0);
$level = trim((string) ($_POST['level'] ?? ''));
$dueDate = trim((string) ($_POST['due_date'] ?? ''));
$allowedLevels = ['100', '200', '300', '400', '500', 'All levels'];

if ($title === '' || $description === '' || $amount <= 0 || !in_array($level, $allowedLevels, true) || $dueDate === '') {
    header('Location: dashboard.php?section=create&message=' . rawurlencode('Please complete the payment item form correctly.'));
    exit;
}

db()->beginTransaction();

try {
    $stmt = db()->prepare(
        'INSERT INTO payments (payment_code, title, description, amount, department, level, lecturer_id, due_date, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        payment_code(),
        $title,
        $description,
        $amount,
        $user['department'],
        $level,
        (int) $user['id'],
        $dueDate,
        'active',
    ]);

    db()->commit();
    header('Location: dashboard.php?section=items&message=' . rawurlencode('Payment item published for students.'));
    exit;
} catch (Throwable $exception) {
    db()->rollBack();
    header('Location: dashboard.php?section=create&message=' . rawurlencode('Could not create payment item. Try again.'));
    exit;
}
