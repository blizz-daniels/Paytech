<?php

declare(strict_types=1);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(float|int|string|null $value): string
{
    return '&#8358;' . number_format((float) $value, 0);
}

function date_label(?string $value): string
{
    if (!$value) {
        return 'Not set';
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y');
    } catch (Throwable) {
        return (string) $value;
    }
}

function receipt_code(): string
{
    return 'RCP-' . strtoupper(bin2hex(random_bytes(4)));
}

function paystack_reference(): string
{
    return 'DPT-' . strtoupper(bin2hex(random_bytes(8)));
}

function payment_code(): string
{
    return 'PAY-' . strtoupper(bin2hex(random_bytes(3)));
}

function current_app_url(): string
{
    if (defined('APP_URL') && APP_URL !== '') {
        return APP_URL;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
    $path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    return $scheme . '://' . $host . ($path === '' ? '' : $path);
}

function percent_value(float|int $part, float|int $whole): int
{
    if ((float) $whole <= 0.0) {
        return 0;
    }

    return (int) round(((float) $part / (float) $whole) * 100);
}

function active_link(string $current, string $section): string
{
    return $current === $section ? ' nav-button is-active' : ' nav-button';
}

function profile_row(string $label, string $value): string
{
    return '<div class="receipt-row"><span>' . h($label) . '</span><strong>' . h($value) . '</strong></div>';
}
