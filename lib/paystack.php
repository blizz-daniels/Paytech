<?php

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function paystack_is_configured(): bool
{
    if (PAYSTACK_SECRET_KEY === '') {
        return false;
    }

    return !str_contains(PAYSTACK_SECRET_KEY, 'replace_with_your_key');
}

function paystack_request(string $method, string $path, array $payload = []): array
{
    if (!paystack_is_configured()) {
        throw new RuntimeException('Paystack secret key is not configured.');
    }

    $url = 'https://api.paystack.co' . $path;
    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ];

    $body = $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ]);

        if ($body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Paystack request failed: ' . $error);
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        $statusCode = 0;

        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
            $statusCode = (int) $match[1];
        }

        if ($response === false) {
            throw new RuntimeException('Paystack request failed.');
        }
    }

    $decoded = json_decode($response, true);

    if (!is_array($decoded)) {
        throw new RuntimeException('Paystack returned an invalid response.');
    }

    if ($statusCode >= 400 || empty($decoded['status'])) {
        $message = isset($decoded['message']) ? (string) $decoded['message'] : 'Paystack request was not accepted.';
        throw new RuntimeException($message);
    }

    return $decoded;
}

function paystack_initialize_transaction(array $data): array
{
    return paystack_request('POST', '/transaction/initialize', $data);
}

function paystack_verify_transaction(string $reference): array
{
    return paystack_request('GET', '/transaction/verify/' . rawurlencode($reference));
}

function paystack_list_banks(string $currency = 'NGN'): array
{
    $query = http_build_query([
        'currency' => strtoupper($currency),
        'enabled_for_verification' => 'true',
    ]);

    return paystack_request('GET', '/bank?' . $query);
}

function paystack_resolve_account_number(string $accountNumber, string $bankCode): array
{
    $query = http_build_query([
        'account_number' => $accountNumber,
        'bank_code' => $bankCode,
    ]);

    return paystack_request('GET', '/bank/resolve?' . $query);
}

function paystack_create_subaccount(array $data): array
{
    return paystack_request('POST', '/subaccount', $data);
}

function paystack_update_subaccount(string $idOrCode, array $data): array
{
    return paystack_request('PUT', '/subaccount/' . rawurlencode($idOrCode), $data);
}
