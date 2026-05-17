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

define('APP_URL', rtrim(getenv('APP_URL') ?: 'https://embellish-liftoff-ranked.ngrok-free.dev', '/'));
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: 'sk_test_290109990b79e97254a84bb9e196bace328eb5ef');
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_test_e25eb5eb773c1b8725d1f8640c9db4f4c18b6634');
define('PAYSTACK_CURRENCY', getenv('PAYSTACK_CURRENCY') ?: 'NGN');
define('PAYSTACK_CALLBACK_URL', rtrim(getenv('PAYSTACK_CALLBACK_URL') ?: APP_URL . '/paystack_callback.php', '/'));

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');
