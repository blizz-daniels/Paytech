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

function payment_code(): string
{
    return 'PAY-' . strtoupper(bin2hex(random_bytes(3)));
}

function active_link(string $current, string $section): string
{
    return $current === $section ? ' nav-button is-active' : ' nav-button';
}

function profile_row(string $label, string $value): string
{
    return '<div class="receipt-row"><span>' . h($label) . '</span><strong>' . h($value) . '</strong></div>';
}
