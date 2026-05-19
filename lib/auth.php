<?php

declare(strict_types=1);

require_once __DIR__ . '/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = dirname(__DIR__) . '/storage/sessions';
    $httpsEnabled = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $secureCookie = SESSION_SECURE_COOKIE || $httpsEnabled;

    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }

    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    if ($secureCookie) {
        ini_set('session.cookie_secure', '1');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf(): void
{
    $posted = (string) ($_POST['csrf_token'] ?? '');
    $session = (string) ($_SESSION['csrf_token'] ?? '');

    if ($posted === '' || $session === '' || !hash_equals($session, $posted)) {
        http_response_code(419);
        exit('Security token expired. Please go back and try again.');
    }
}

function login_user(string $role, int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'role' => $role,
        'id' => $id,
    ];
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}

function session_user(): ?array
{
    if (empty($_SESSION['user']['role']) || empty($_SESSION['user']['id'])) {
        return null;
    }

    $role = (string) $_SESSION['user']['role'];
    $id = (int) $_SESSION['user']['id'];

    $tables = [
        'admin' => 'admins',
        'lecturer' => 'lecturers',
        'student' => 'students',
    ];

    if (!isset($tables[$role])) {
        return null;
    }

    $stmt = db()->prepare("SELECT * FROM {$tables[$role]} WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    if (!$user) {
        return null;
    }

    $user['role'] = $role;
    return $user;
}

function require_login(): array
{
    $user = session_user();

    if (!$user) {
        header('Location: index.php');
        exit;
    }

    return $user;
}

function require_role(string $role): array
{
    $user = require_login();

    if ($user['role'] !== $role) {
        header('Location: dashboard.php');
        exit;
    }

    return $user;
}

function authenticate(string $identity, string $accessCode): ?array
{
    $identity = trim($identity);
    $accessCode = trim($accessCode);

    if ($identity === '' || $accessCode === '') {
        return null;
    }

    $lookups = [
        [
            'role' => 'admin',
            'sql' => 'SELECT id FROM admins WHERE LOWER(surname) = LOWER(?) AND LOWER(admin_code) = LOWER(?) LIMIT 1',
        ],
        [
            'role' => 'lecturer',
            'sql' => 'SELECT id FROM lecturers WHERE LOWER(surname) = LOWER(?) AND LOWER(teachercode) = LOWER(?) LIMIT 1',
        ],
        [
            'role' => 'student',
            'sql' => 'SELECT id FROM students WHERE LOWER(surname) = LOWER(?) AND LOWER(matricnumber) = LOWER(?) LIMIT 1',
        ],
    ];

    foreach ($lookups as $lookup) {
        $stmt = db()->prepare($lookup['sql']);
        $stmt->execute([$identity, $accessCode]);
        $row = $stmt->fetch();

        if ($row) {
            return [
                'role' => $lookup['role'],
                'id' => (int) $row['id'],
            ];
        }
    }

    return null;
}
