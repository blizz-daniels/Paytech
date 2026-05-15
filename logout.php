<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    logout_user();
}

header('Location: index.php');
exit;
