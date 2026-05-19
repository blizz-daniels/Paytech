<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/database.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/paystack.php';

function webhook_response(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_THROW_ON_ERROR);
    exit;
}

function paystack_signature_header(): string
{
    if (isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) {
        return trim((string) $_SERVER['HTTP_X_PAYSTACK_SIGNATURE']);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'x-paystack-signature') {
                return trim((string) $value);
            }
        }
    }

    return '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhook_response(405, ['status' => 'error', 'message' => 'Method not allowed']);
}

if (!paystack_is_configured()) {
    webhook_response(500, ['status' => 'error', 'message' => 'Paystack is not configured']);
}

$payload = file_get_contents('php://input');
if ($payload === false || trim($payload) === '') {
    webhook_response(400, ['status' => 'error', 'message' => 'Empty payload']);
}

$signature = paystack_signature_header();
$expectedSignature = hash_hmac('sha512', $payload, PAYSTACK_SECRET_KEY);
if ($signature === '' || !hash_equals($expectedSignature, $signature)) {
    webhook_response(401, ['status' => 'error', 'message' => 'Invalid signature']);
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    webhook_response(400, ['status' => 'error', 'message' => 'Invalid JSON payload']);
}

$eventType = (string) ($event['event'] ?? '');
if ($eventType !== 'charge.success') {
    webhook_response(200, ['status' => 'ok', 'message' => 'Event ignored']);
}

$data = $event['data'] ?? [];
$reference = trim((string) ($data['reference'] ?? ''));
if ($reference === '') {
    webhook_response(400, ['status' => 'error', 'message' => 'Missing transaction reference']);
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
    webhook_response(200, ['status' => 'ok', 'message' => 'Reference not found in payment attempts']);
}

try {
    // Always verify with Paystack API before writing a receipt.
    $response = paystack_verify_transaction($reference);
    $verifiedData = $response['data'] ?? [];
    $status = (string) ($verifiedData['status'] ?? '');
    $verifiedReference = (string) ($verifiedData['reference'] ?? '');
    $amountKobo = (int) ($verifiedData['amount'] ?? 0);
    $expectedKobo = (int) round(((float) $attempt['amount']) * 100);
    $gatewayResponse = (string) ($verifiedData['gateway_response'] ?? '');
    $channel = (string) ($verifiedData['channel'] ?? 'paystack');
    $currency = (string) ($verifiedData['currency'] ?? PAYSTACK_CURRENCY);
    $paidAt = (string) ($verifiedData['paid_at'] ?? '');
    $paystackTransactionId = isset($verifiedData['id']) ? (string) $verifiedData['id'] : null;

    if ($status !== 'success' || $verifiedReference !== $reference || $amountKobo !== $expectedKobo) {
        $stmt = db()->prepare(
            'UPDATE payment_attempts
             SET status = ?, gateway_response = ?, raw_response = ?, verified_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            'failed',
            $gatewayResponse !== '' ? $gatewayResponse : 'Verification failed',
            json_encode($response, JSON_THROW_ON_ERROR),
            (int) $attempt['id'],
        ]);

        webhook_response(200, ['status' => 'ok', 'message' => 'Verification failed']);
    }

    db()->beginTransaction();

    $existing = db_one(
        'SELECT id FROM transactions WHERE paystack_reference = ? OR (payment_id = ? AND student_id = ?) LIMIT 1',
        [$reference, (int) $attempt['payment_id'], (int) $attempt['student_id']]
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
        $gatewayResponse !== '' ? $gatewayResponse : 'Successful',
        json_encode($response, JSON_THROW_ON_ERROR),
        (int) $attempt['id'],
    ]);

    db()->commit();

    webhook_response(200, ['status' => 'ok', 'message' => 'Processed']);
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }

    error_log('Paystack webhook processing failed for reference ' . $reference . ': ' . $exception->getMessage());
    webhook_response(500, ['status' => 'error', 'message' => 'Webhook processing failed']);
}
