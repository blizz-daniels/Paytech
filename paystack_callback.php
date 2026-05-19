<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/paystack.php';

$reference = trim((string) ($_GET['reference'] ?? ''));

if ($reference === '') {
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Missing Paystack payment reference.'));
    exit;
}

$attempt = db_one(
    "SELECT pa.*, p.title AS payment_title, p.amount AS payment_amount
     FROM payment_attempts pa
     INNER JOIN payments p ON p.id = pa.payment_id
     WHERE pa.reference = ?
     LIMIT 1",
    [$reference]
);

if (!$attempt) {
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Payment attempt was not found.'));
    exit;
}

if (!paystack_is_configured()) {
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Paystack is not configured yet. Verification cannot continue.'));
    exit;
}

try {
    $response = paystack_verify_transaction($reference);
    $data = $response['data'] ?? [];
    $status = (string) ($data['status'] ?? '');
    $verifiedReference = (string) ($data['reference'] ?? '');
    $amountKobo = (int) ($data['amount'] ?? 0);
    $expectedKobo = (int) round(((float) $attempt['amount']) * 100);
    $gatewayResponse = (string) ($data['gateway_response'] ?? '');
    $channel = (string) ($data['channel'] ?? 'paystack');
    $currency = (string) ($data['currency'] ?? PAYSTACK_CURRENCY);
    $paidAt = (string) ($data['paid_at'] ?? '');
    $paystackTransactionId = isset($data['id']) ? (string) $data['id'] : null;

    if ($status !== 'success' || $verifiedReference !== $reference || $amountKobo !== $expectedKobo) {
        $stmt = db()->prepare(
            'UPDATE payment_attempts
             SET status = ?, gateway_response = ?, raw_response = ?, verified_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            'failed',
            $gatewayResponse ?: 'Verification failed',
            json_encode($response, JSON_THROW_ON_ERROR),
            (int) $attempt['id'],
        ]);

        header('Location: dashboard.php?section=payments&message=' . rawurlencode('Paystack payment was not successful or the amount did not match.'));
        exit;
    }

    db()->beginTransaction();

    $existing = db_one(
        'SELECT id FROM transactions WHERE payment_id = ? AND student_id = ? LIMIT 1',
        [(int) $attempt['payment_id'], (int) $attempt['student_id']]
    );

    if (!$existing) {
        $stmt = db()->prepare(
            'INSERT INTO transactions
              (receipt_code, payment_id, student_id, amount, method, payer_email, paystack_reference, paystack_transaction_id, channel, currency, gateway_response, raw_response, paid_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            receipt_code(),
            (int) $attempt['payment_id'],
            (int) $attempt['student_id'],
            (float) $attempt['amount'],
            'Paystack',
            $attempt['payer_email'],
            $reference,
            $paystackTransactionId,
            $channel,
            $currency,
            $gatewayResponse,
            json_encode($response, JSON_THROW_ON_ERROR),
            $paidAt !== '' ? (new DateTimeImmutable($paidAt))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'),
        ]);
    }

    $stmt = db()->prepare(
        'UPDATE payment_attempts
         SET status = ?, gateway_response = ?, raw_response = ?, verified_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([
        'verified',
        $gatewayResponse ?: 'Successful',
        json_encode($response, JSON_THROW_ON_ERROR),
        (int) $attempt['id'],
    ]);

    db()->commit();

    header('Location: dashboard.php?section=receipts&message=' . rawurlencode('Paystack payment verified and receipt generated.'));
    exit;
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('Paystack callback verification failed for reference ' . $reference . ': ' . $exception->getMessage());
    header('Location: dashboard.php?section=payments&message=' . rawurlencode('Paystack verification failed. Please contact support if the charge succeeded.'));
    exit;
}
