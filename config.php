<?php

declare(strict_types=1);

const APP_NAME = 'DepartmentPay Portal';
const APP_ENV = 'local';

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'departmentpay');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://127.0.0.1/Paytech', '/'));
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: 'sk_test_replace_with_your_key');
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: 'pk_test_replace_with_your_key');
define('PAYSTACK_CURRENCY', getenv('PAYSTACK_CURRENCY') ?: 'NGN');
define('PAYSTACK_CALLBACK_URL', rtrim(getenv('PAYSTACK_CALLBACK_URL') ?: APP_URL . '/paystack_callback.php', '/'));

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');
