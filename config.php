<?php

declare(strict_types=1);

const APP_NAME = 'Paytec';
const COMPANY_NAME = 'Da4lions';

define('APP_ENV', strtolower(trim((string) (getenv('APP_ENV') ?: 'production'))));
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: (APP_ENV === 'local' ? '1' : '0'), FILTER_VALIDATE_BOOL));
define('SESSION_SECURE_COOKIE', filter_var(getenv('SESSION_SECURE_COOKIE') ?: (APP_ENV === 'local' ? '0' : '1'), FILTER_VALIDATE_BOOL));

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'departmentpay');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('APP_URL', rtrim((string) (getenv('APP_URL') ?: ''), '/'));
define('PAYSTACK_SECRET_KEY', (string) (getenv('PAYSTACK_SECRET_KEY') ?: ''));
define('PAYSTACK_PUBLIC_KEY', (string) (getenv('PAYSTACK_PUBLIC_KEY') ?: ''));
define('PAYSTACK_CURRENCY', getenv('PAYSTACK_CURRENCY') ?: 'NGN');
$configuredCallbackUrl = getenv('PAYSTACK_CALLBACK_URL');
$defaultCallbackUrl = APP_URL !== '' ? APP_URL . '/paystack_callback.php' : '';
define('PAYSTACK_CALLBACK_URL', rtrim((string) ($configuredCallbackUrl !== false && $configuredCallbackUrl !== '' ? $configuredCallbackUrl : $defaultCallbackUrl), '/'));
define('PAYSTACK_PLATFORM_PERCENTAGE', (float) (getenv('PAYSTACK_PLATFORM_PERCENTAGE') ?: '10'));
define('PAYSTACK_SPLIT_BEARER', strtolower(trim((string) (getenv('PAYSTACK_SPLIT_BEARER') ?: 'account'))));

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');
