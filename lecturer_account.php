<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/paystack.php';

$user = require_role('lecturer');

function lecturer_columns(): array
{
    static $columns = null;

    if (is_array($columns)) {
        return $columns;
    }

    $columns = [];
    $rows = db_all('SHOW COLUMNS FROM lecturers');
    foreach ($rows as $row) {
        $field = (string) ($row['Field'] ?? '');
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    return $columns;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=account');
    exit;
}

verify_csrf();

$bankCode = trim((string) ($_POST['bank_code'] ?? ''));
$bankName = trim((string) ($_POST['bank_name'] ?? ''));
$accountNumber = preg_replace('/\D+/', '', (string) ($_POST['account_number'] ?? '')) ?? '';

if ($bankCode === '' || $accountNumber === '' || strlen($accountNumber) !== 10) {
    header('Location: dashboard.php?section=account&message=' . rawurlencode('Provide a valid 10-digit account number and bank.'));
    exit;
}

if (!paystack_is_configured()) {
    header('Location: dashboard.php?section=account&message=' . rawurlencode('Paystack is not configured yet. Add your secret key first.'));
    exit;
}

$lecturer = db_one('SELECT * FROM lecturers WHERE id = ? LIMIT 1', [(int) $user['id']]);
if (!$lecturer) {
    header('Location: dashboard.php?section=account&message=' . rawurlencode('Lecturer profile was not found.'));
    exit;
}

$columns = lecturer_columns();
$requiredColumns = [
    'bank_code',
    'bank_name',
    'account_number',
    'account_name',
    'paystack_subaccount_code',
    'paystack_subaccount_id',
    'paystack_subaccount_active',
];

foreach ($requiredColumns as $requiredColumn) {
    if (!isset($columns[$requiredColumn])) {
        header('Location: dashboard.php?section=account&message=' . rawurlencode('Database update required. Run data/migration_paystack_subaccounts.sql, then try again.'));
        exit;
    }
}

$businessName = trim($lecturer['name'] . ' ' . $lecturer['surname']) . ' (' . $lecturer['department'] . ')';
$platformPercentage = max(0.0, min(100.0, (float) PAYSTACK_PLATFORM_PERCENTAGE));

try {
    $resolved = paystack_resolve_account_number($accountNumber, $bankCode);
    $resolvedData = $resolved['data'] ?? [];
    $accountName = trim((string) ($resolvedData['account_name'] ?? ''));

    if ($accountName === '') {
        throw new RuntimeException('Paystack could not resolve the account name.');
    }

    $payload = [
        'business_name' => $businessName,
        'settlement_bank' => $bankCode,
        'account_number' => $accountNumber,
        'percentage_charge' => $platformPercentage,
        'description' => 'Lecturer split account for ' . $lecturer['teachercode'],
    ];

    $subaccountCode = trim((string) ($lecturer['paystack_subaccount_code'] ?? ''));
    if ($subaccountCode !== '') {
        $subaccountResponse = paystack_update_subaccount($subaccountCode, $payload);
    } else {
        $subaccountResponse = paystack_create_subaccount($payload);
    }

    $subaccountData = $subaccountResponse['data'] ?? [];
    $savedCode = trim((string) ($subaccountData['subaccount_code'] ?? $subaccountCode));

    if ($savedCode === '') {
        throw new RuntimeException('Paystack did not return a valid subaccount code.');
    }

    $savedId = isset($subaccountData['id']) ? (int) $subaccountData['id'] : null;
    $savedBank = trim((string) ($subaccountData['settlement_bank'] ?? $bankName));
    $isActive = (int) (($subaccountData['active'] ?? true) ? 1 : 0);

    $setParts = [
        'bank_code = ?',
        'bank_name = ?',
        'account_number = ?',
        'account_name = ?',
        'paystack_subaccount_code = ?',
        'paystack_subaccount_id = ?',
        'paystack_subaccount_active = ?',
    ];

    if (isset($columns['paystack_last_error'])) {
        $setParts[] = 'paystack_last_error = NULL';
    }

    if (isset($columns['paystack_synced_at'])) {
        $setParts[] = 'paystack_synced_at = NOW()';
    }

    $params = [
        $bankCode,
        $savedBank !== '' ? $savedBank : $bankName,
        $accountNumber,
        $accountName,
        $savedCode,
        $savedId,
        $isActive,
    ];

    $stmt = db()->prepare(
        'UPDATE lecturers
         SET ' . implode(', ', $setParts) . '
         WHERE id = ?'
    );
    $params[] = (int) $lecturer['id'];
    $stmt->execute($params);

    header('Location: dashboard.php?section=account&message=' . rawurlencode('Settlement account saved and linked to Paystack split payouts.'));
    exit;
} catch (Throwable $exception) {
    try {
        $errorSetParts = [];
        $errorParams = [];

        if (isset($columns['paystack_last_error'])) {
            $errorSetParts[] = 'paystack_last_error = ?';
            $errorParams[] = substr($exception->getMessage(), 0, 255);
        }

        if (isset($columns['paystack_synced_at'])) {
            $errorSetParts[] = 'paystack_synced_at = NOW()';
        }

        if ($errorSetParts) {
            $stmt = db()->prepare(
                'UPDATE lecturers
                 SET ' . implode(', ', $errorSetParts) . '
                 WHERE id = ?'
            );
            $errorParams[] = (int) $lecturer['id'];
            $stmt->execute($errorParams);
        }
    } catch (Throwable) {
        // Ignore logging failures to avoid masking the original error.
    }

    header('Location: dashboard.php?section=account&message=' . rawurlencode('Could not save account: ' . $exception->getMessage()));
    exit;
}
