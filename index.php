<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

$error = '';
$notice = '';
$metrics = [
    'settled' => 0,
    'receipts' => 0,
    'departments' => 0,
];

try {
    if (session_user()) {
        header('Location: dashboard.php');
        exit;
    }
} catch (Throwable) {
    $notice = 'Connect MySQL and import data/schema.sql to enable login.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $auth = authenticate($_POST['identity'] ?? '', $_POST['access_code'] ?? '');

        if ($auth === null) {
            $error = 'Those details do not match a student, lecturer, or admin record.';
        } else {
            login_user($auth['role'], $auth['id']);
            header('Location: dashboard.php');
            exit;
        }
    } catch (Throwable $exception) {
        $error = 'Database connection failed. Check config.php and import data/schema.sql.';
    }
}

try {
    $metrics['settled'] = (float) db()->query('SELECT COALESCE(SUM(amount), 0) FROM transactions')->fetchColumn();
    $metrics['receipts'] = (int) db()->query('SELECT COUNT(*) FROM transactions')->fetchColumn();
    $metrics['departments'] = (int) db()->query('SELECT COUNT(DISTINCT department) FROM students')->fetchColumn();
} catch (Throwable) {
    $notice = $notice ?: 'Connect MySQL and import data/schema.sql to enable live records.';
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/lion-logo-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/lion-logo-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/lion-logo-180.png">
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <div class="app-shell">
      <main class="login-layout">
        <section class="login-panel" aria-labelledby="loginTitle">
          <div class="brand-row">
            <img class="school-logo" src="assets/da4lions-logo.jpeg" alt="Da4lions logo">
            <div>
              <p class="eyebrow"><?= h(COMPANY_NAME) ?></p>
              <h1 id="loginTitle">Secure payments with Paytec.</h1>
            </div>
          </div>

          <?php if ($notice): ?>
            <p class="notice-text"><?= h($notice) ?></p>
          <?php endif; ?>

          <form class="login-form" method="post" action="index.php">
            <?= csrf_field() ?>
            <div class="field-group">
              <label for="identity">Surname or admin ID</label>
              <input id="identity" name="identity" type="text" autocomplete="username" placeholder="Okafor" required>
            </div>

            <div class="field-group">
              <label for="accessCode">Matric number, teacher code, or admin code</label>
              <input id="accessCode" name="access_code" type="text" autocomplete="current-password" placeholder="DPT/CSC/24/001" required>
            </div>

            <p class="form-error" role="alert"><?= h($error) ?></p>

            <button class="primary-button full-width" type="submit">Sign in</button>
          </form>

          <?php if (APP_ENV === 'local'): ?>
            <div class="demo-strip" aria-label="Demo access examples">
              <span>Student: Okafor / DPT/CSC/24/001</span>
              <span>Lecturer: Mensah / TCH-CS-104</span>
              <span>Admin: admin / ADMIN-001</span>
            </div>
          <?php endif; ?>
        </section>

        <aside class="status-panel" aria-label="Payment summary">
          <div class="glass-card">
            <p class="panel-label">Current session</p>
            <strong>Paytec collections</strong>
            <div class="metric-grid">
              <div>
                <span>Settled</span>
                <strong><?= money($metrics['settled']) ?></strong>
              </div>
              <div>
                <span>Receipts</span>
                <strong><?= h((string) $metrics['receipts']) ?></strong>
              </div>
              <div>
                <span>Departments</span>
                <strong><?= h((string) $metrics['departments']) ?></strong>
              </div>
            </div>
          </div>
        </aside>
      </main>
    </div>
  </body>
</html>
