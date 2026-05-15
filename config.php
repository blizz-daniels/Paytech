<?php

declare(strict_types=1);

const APP_NAME = 'DepartmentPay Portal';

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'departmentpay');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Africa/Lagos');

