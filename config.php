<?php

declare(strict_types=1);

const APP_NAME = 'Paytec';
const COMPANY_NAME = 'Da4lions';
const APP_ENV = 'local';

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'departmentpay');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://paytech.test', '/'));
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: 'sk_test_b9e73f334209dff5272982f08c02b7f152886040');
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_test_3a803e45b54f216c60a63f9de9cc50e43bd6cde2');
define('PAYSTACK_CURRENCY', getenv('PAYSTACK_CURRENCY') ?: 'NGN');
define('PAYSTACK_CALLBACK_URL', rtrim(getenv('PAYSTACK_CALLBACK_URL') ?: APP_URL . '/paystack_callback.php', '/'));
define('PAYSTACK_PLATFORM_PERCENTAGE', (float) (getenv('PAYSTACK_PLATFORM_PERCENTAGE') ?: '10'));
define('PAYSTACK_SPLIT_BEARER', strtolower(trim((string) (getenv('PAYSTACK_SPLIT_BEARER') ?: 'account'))));

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');
