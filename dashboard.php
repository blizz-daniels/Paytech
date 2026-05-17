<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/helpers.php';

$user = require_login();
$role = $user['role'];

$sections = [
    'student' => [
        'overview' => 'Overview',
        'payments' => 'Payments',
        'receipts' => 'Receipts',
    ],
    'lecturer' => [
        'overview' => 'Overview',
        'create' => 'Create item',
        'items' => 'My items',
        'analysis' => 'Analysis',
    ],
    'admin' => [
        'overview' => 'Overview',
        'records' => 'Records',
        'analysis' => 'Analysis',
        'import' => 'CSV import',
        'database' => 'Database',
    ],
];

$section = $_GET['section'] ?? 'overview';
if (!array_key_exists($section, $sections[$role])) {
    $section = 'overview';
}

$flash = $_GET['message'] ?? '';
$roleName = ucfirst($role);
$welcomeTitle = '';
$welcomeMeta = '';
$cards = [];
$payments = [];
$transactions = [];
$students = [];
$lecturers = [];
$paidPaymentIds = [];
$departmentAnalysis = [];
$methodAnalysis = [];
$pendingAttempts = 0;

if ($role === 'student') {
    $welcomeTitle = 'Welcome, ' . $user['name'] . ' ' . $user['surname'];
    $welcomeMeta = $user['department'] . ' - ' . $user['level'] . ' level - ' . $user['matricnumber'];

    $payments = db_all(
        "SELECT p.*, CONCAT(l.name, ' ', l.surname) AS lecturer_name
         FROM payments p
         INNER JOIN lecturers l ON l.id = p.lecturer_id
         WHERE p.status = 'active'
           AND p.department = ?
           AND (p.level = ? OR p.level = 'All levels')
         ORDER BY p.due_date ASC, p.created_at DESC",
        [$user['department'], $user['level']]
    );

    $transactions = db_all(
        "SELECT t.*, p.title AS payment_title
         FROM transactions t
         INNER JOIN payments p ON p.id = t.payment_id
         WHERE t.student_id = ?
         ORDER BY t.paid_at DESC",
        [(int) $user['id']]
    );

    $paidPaymentIds = array_flip(array_map(static fn (array $item): int => (int) $item['payment_id'], $transactions));
    $outstandingTotal = 0.0;
    $outstandingCount = 0;

    foreach ($payments as $payment) {
        if (!isset($paidPaymentIds[(int) $payment['id']])) {
            $outstandingTotal += (float) $payment['amount'];
            $outstandingCount++;
        }
    }

    $paidTotal = array_sum(array_map(static fn (array $item): float => (float) $item['amount'], $transactions));

    $cards = [
        ['label' => 'Outstanding balance', 'value' => money($outstandingTotal), 'note' => $outstandingCount . ' item(s) awaiting payment'],
        ['label' => 'Paid this term', 'value' => money($paidTotal), 'note' => count($transactions) . ' receipt(s) generated'],
        ['label' => 'Department', 'value' => h($user['department']), 'note' => h($user['level'] . ' level')],
        ['label' => 'Student record', 'value' => h($user['matricnumber']), 'note' => 'SQL + CSV seeded profile'],
    ];
}

if ($role === 'lecturer') {
    $welcomeTitle = 'Welcome, ' . $user['name'] . ' ' . $user['surname'];
    $welcomeMeta = $user['department'] . ' - ' . $user['teachercode'];

    $payments = db_all(
        "SELECT p.*,
                (SELECT COUNT(*) FROM transactions t WHERE t.payment_id = p.id) AS paid_count,
                (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.payment_id = p.id) AS collected,
                (SELECT COUNT(*) FROM payment_attempts pa WHERE pa.payment_id = p.id AND pa.status = 'initialized') AS pending_count,
                (SELECT COUNT(*) FROM students s WHERE s.department = p.department AND (s.level = p.level OR p.level = 'All levels')) AS eligible_count
         FROM payments p
         WHERE p.lecturer_id = ?
         ORDER BY p.created_at DESC",
        [(int) $user['id']]
    );

    $students = db_all(
        'SELECT * FROM students WHERE department = ? ORDER BY level, surname, name',
        [$user['department']]
    );

    $collected = array_sum(array_map(static fn (array $item): float => (float) $item['collected'], $payments));
    $paidCount = array_sum(array_map(static fn (array $item): int => (int) $item['paid_count'], $payments));
    $expected = array_sum(array_map(static fn (array $item): float => (float) $item['amount'] * (int) $item['eligible_count'], $payments));
    $collectionRate = percent_value($collected, $expected);

    $cards = [
        ['label' => 'Created items', 'value' => h((string) count($payments)), 'note' => 'Active departmental payment items'],
        ['label' => 'Collected', 'value' => money($collected), 'note' => $paidCount . ' successful student payment(s)'],
        ['label' => 'Department', 'value' => h($user['department']), 'note' => h($user['teachercode'])],
        ['label' => 'Collection rate', 'value' => h($collectionRate . '%'), 'note' => 'Against assigned payment value'],
    ];
}

if ($role === 'admin') {
    $welcomeTitle = 'Welcome, Bursary Office';
    $welcomeMeta = 'Monitor departmental collections, students, lecturers, and receipts.';

    $payments = db_all(
        "SELECT p.*,
                CONCAT(l.name, ' ', l.surname) AS lecturer_name,
                l.teachercode,
                (SELECT COUNT(*) FROM transactions t WHERE t.payment_id = p.id) AS paid_count,
                (SELECT COALESCE(SUM(t.amount), 0) FROM transactions t WHERE t.payment_id = p.id) AS collected,
                (SELECT COUNT(*) FROM payment_attempts pa WHERE pa.payment_id = p.id AND pa.status = 'initialized') AS pending_count,
                (SELECT COUNT(*) FROM students s WHERE s.department = p.department AND (s.level = p.level OR p.level = 'All levels')) AS eligible_count
         FROM payments p
         INNER JOIN lecturers l ON l.id = p.lecturer_id
         ORDER BY p.created_at DESC"
    );

    $transactions = db_all(
        "SELECT t.*, p.title AS payment_title, s.name, s.surname, s.matricnumber, s.department, s.level
         FROM transactions t
         INNER JOIN payments p ON p.id = t.payment_id
         INNER JOIN students s ON s.id = t.student_id
         ORDER BY t.paid_at DESC"
    );

    $students = db_all('SELECT * FROM students ORDER BY department, level, surname');
    $lecturers = db_all('SELECT * FROM lecturers ORDER BY department, surname');
    $revenue = array_sum(array_map(static fn (array $item): float => (float) $item['amount'], $transactions));
    $expected = array_sum(array_map(static fn (array $item): float => (float) $item['amount'] * (int) $item['eligible_count'], $payments));
    $pendingAttempts = (int) db_value("SELECT COUNT(*) FROM payment_attempts WHERE status = 'initialized'");
    $departments = (int) db_value('SELECT COUNT(DISTINCT department) FROM students');
    $departmentAnalysis = db_all(
        "SELECT s.department,
                COUNT(DISTINCT s.id) AS student_count,
                COUNT(DISTINCT p.id) AS payment_count,
                COALESCE(SUM(CASE WHEN p.id IS NULL THEN 0 ELSE p.amount END), 0) AS expected,
                COALESCE(SUM(t.amount), 0) AS collected
         FROM students s
         LEFT JOIN payments p ON p.department = s.department AND p.status = 'active' AND (p.level = s.level OR p.level = 'All levels')
         LEFT JOIN transactions t ON t.payment_id = p.id AND t.student_id = s.id
         GROUP BY s.department
         ORDER BY collected DESC, s.department ASC"
    );
    $methodAnalysis = db_all(
        "SELECT method, COALESCE(channel, 'manual') AS channel, COUNT(*) AS receipt_count, COALESCE(SUM(amount), 0) AS collected
         FROM transactions
         GROUP BY method, channel
         ORDER BY collected DESC"
    );

    $cards = [
        ['label' => 'Revenue collected', 'value' => money($revenue), 'note' => count($transactions) . ' successful payment(s)'],
        ['label' => 'Outstanding value', 'value' => money(max(0, $expected - $revenue)), 'note' => percent_value($revenue, $expected) . '% collected'],
        ['label' => 'Students', 'value' => h((string) count($students)), 'note' => 'SQL + CSV records'],
        ['label' => 'Pending Paystack', 'value' => h((string) $pendingAttempts), 'note' => $departments . ' department(s) configured'],
    ];
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($roleName) ?> Dashboard - <?= h(APP_NAME) ?></title>
    <link rel="icon" type="image/png" sizes="16x16" href="assets/lion-logo-16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/lion-logo-32.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/lion-logo-180.png">
    <link rel="stylesheet" href="styles.css">
  </head>
  <body>
    <div class="app-shell">
      <section class="dashboard-shell" aria-live="polite">
        <aside class="sidebar">
          <div class="sidebar-brand">
            <img class="sidebar-logo" src="assets/lion-logo-192.png" alt="Da4lions logo">
            <div>
              <strong><?= h(APP_NAME) ?></strong>
              <span><?= h(COMPANY_NAME) ?></span>
            </div>
          </div>

          <nav class="section-nav" aria-label="Dashboard sections">
            <?php foreach ($sections[$role] as $sectionKey => $label): ?>
              <a class="<?= h(active_link($section, $sectionKey)) ?>" href="dashboard.php?section=<?= h($sectionKey) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
          </nav>
        </aside>

        <main class="workspace">
          <header class="workspace-header">
            <div>
              <p class="eyebrow"><?= h($roleName) ?> Dashboard</p>
              <h2><?= h($welcomeTitle) ?></h2>
              <p class="muted-text"><?= h($welcomeMeta) ?></p>
            </div>
            <form method="post" action="logout.php">
              <?= csrf_field() ?>
              <button class="ghost-button" type="submit">Log out</button>
            </form>
          </header>

          <?php if ($flash): ?>
            <p class="success-banner"><?= h($flash) ?></p>
          <?php endif; ?>

          <section class="overview-grid" aria-label="Overview">
            <?php foreach ($cards as $card): ?>
              <article class="stat-card">
                <span><?= h($card['label']) ?></span>
                <strong><?= $card['value'] ?></strong>
                <small><?= $card['note'] ?></small>
              </article>
            <?php endforeach; ?>
          </section>

          <div class="content-grid">
            <?php if ($role === 'student' && $section === 'receipts'): ?>
              <section class="panel-card span-all">
                <header>
                  <div>
                    <p class="eyebrow">Receipts</p>
                    <h3>Payment history</h3>
                  </div>
                </header>

                <?php if (!$transactions): ?>
                  <div class="empty-state">No receipt has been generated for this student yet.</div>
                <?php else: ?>
                  <div class="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          <th>Receipt</th>
                          <th>Reference</th>
                          <th>Payment</th>
                          <th>Amount</th>
                          <th>Method</th>
                          <th>Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                          <tr>
                            <td><?= h($transaction['receipt_code']) ?></td>
                            <td><?= h($transaction['paystack_reference'] ?: '-') ?></td>
                            <td><?= h($transaction['payment_title']) ?></td>
                            <td><?= money($transaction['amount']) ?></td>
                            <td><?= h($transaction['method'] . ($transaction['channel'] ? ' / ' . $transaction['channel'] : '')) ?></td>
                            <td><?= h(date_label($transaction['paid_at'])) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>

            <?php if ($role === 'student' && $section !== 'receipts'): ?>
              <section class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow"><?= $section === 'payments' ? 'Payments' : 'Student overview' ?></p>
                    <h3>Available payment items</h3>
                  </div>
                  <span class="badge gold"><?= h((string) count($payments)) ?> item(s)</span>
                </header>

                <div class="stack">
                  <?php if (!$payments): ?>
                    <div class="empty-state">No payment item has been assigned to your department and level yet.</div>
                  <?php endif; ?>

                  <?php foreach ($payments as $payment): ?>
                    <?php $paid = isset($paidPaymentIds[(int) $payment['id']]); ?>
                    <article class="list-row">
                      <div class="list-main">
                        <strong><?= h($payment['title']) ?></strong>
                        <span class="list-meta"><?= h($payment['description']) ?></span>
                        <span class="list-meta"><?= h($payment['department']) ?> - <?= h($payment['level']) ?> - Due <?= h(date_label($payment['due_date'])) ?></span>
                      </div>
                      <div class="action-row">
                        <span class="amount-text"><?= money($payment['amount']) ?></span>
                        <span class="badge <?= $paid ? '' : 'gold' ?>"><?= $paid ? 'Paid' : 'Pending' ?></span>
                        <?php if ($paid): ?>
                          <a class="ghost-button" href="dashboard.php?section=receipts">View receipt</a>
                        <?php else: ?>
                          <form class="inline-pay-form" method="post" action="pay.php">
                            <?= csrf_field() ?>
                            <input type="hidden" name="payment_id" value="<?= h((string) $payment['id']) ?>">
                            <input name="payer_email" type="email" aria-label="Email for Paystack receipt" placeholder="Email for receipt" required>
                            <button class="primary-button" type="submit">Pay with Paystack</button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </section>

              <aside class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Profile</p>
                    <h3>Student data</h3>
                  </div>
                </header>
                <div class="stack">
                  <?= profile_row('Name', $user['name'] . ' ' . $user['surname']) ?>
                  <?= profile_row('Matric number', $user['matricnumber']) ?>
                  <?= profile_row('Department', $user['department']) ?>
                  <?= profile_row('Level', $user['level'] . ' level') ?>
                </div>
              </aside>
            <?php endif; ?>

            <?php if ($role === 'lecturer' && $section === 'create'): ?>
              <section class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Create item</p>
                    <h3>Publish a new departmental payment</h3>
                  </div>
                </header>

                <form class="form-grid" method="post" action="create_payment.php">
                  <?= csrf_field() ?>
                  <div class="field-group wide">
                    <label for="paymentTitle">Payment title</label>
                    <input id="paymentTitle" name="title" type="text" placeholder="Departmental Textbook Pack" required>
                  </div>

                  <div class="field-group">
                    <label for="paymentAmount">Amount</label>
                    <input id="paymentAmount" name="amount" type="number" min="1" step="1" placeholder="18500" required>
                  </div>

                  <div class="field-group">
                    <label for="paymentLevel">Student level</label>
                    <select id="paymentLevel" name="level" required>
                      <option value="100">100 level</option>
                      <option value="200">200 level</option>
                      <option value="300">300 level</option>
                      <option value="400">400 level</option>
                      <option value="500">500 level</option>
                      <option value="All levels">All levels</option>
                    </select>
                  </div>

                  <div class="field-group">
                    <label for="paymentDueDate">Due date</label>
                    <input id="paymentDueDate" name="due_date" type="date" required>
                  </div>

                  <div class="field-group">
                    <label for="paymentDepartment">Department</label>
                    <input id="paymentDepartment" type="text" value="<?= h($user['department']) ?>" readonly>
                  </div>

                  <div class="field-group wide">
                    <label for="paymentDescription">Description</label>
                    <textarea id="paymentDescription" name="description" placeholder="Short note shown to students" required></textarea>
                  </div>

                  <div class="wide action-row">
                    <button class="primary-button" type="submit">Publish payment item</button>
                  </div>
                </form>
              </section>

              <aside class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Lecturer</p>
                    <h3>Payment owner</h3>
                  </div>
                </header>
                <div class="stack">
                  <?= profile_row('Name', $user['name'] . ' ' . $user['surname']) ?>
                  <?= profile_row('Teacher code', $user['teachercode']) ?>
                  <?= profile_row('Department', $user['department']) ?>
                </div>
              </aside>
            <?php endif; ?>

            <?php if ($role === 'lecturer' && $section === 'analysis'): ?>
              <section class="panel-card span-all">
                <header>
                  <div>
                    <p class="eyebrow">Analysis</p>
                    <h3>Student payment performance</h3>
                  </div>
                </header>

                <?php if (!$payments): ?>
                  <div class="empty-state">No payment items are available for analysis yet.</div>
                <?php else: ?>
                  <div class="analysis-grid">
                    <?php foreach ($payments as $payment): ?>
                      <?php
                        $eligible = (int) $payment['eligible_count'];
                        $paid = (int) $payment['paid_count'];
                        $unpaid = max(0, $eligible - $paid);
                        $expectedAmount = (float) $payment['amount'] * $eligible;
                        $progress = percent_value((float) $payment['collected'], $expectedAmount);
                      ?>
                      <article class="analysis-card">
                        <div>
                          <span class="eyebrow"><?= h($payment['level']) ?></span>
                          <h3><?= h($payment['title']) ?></h3>
                          <p class="muted-text"><?= h($payment['department']) ?> - due <?= h(date_label($payment['due_date'])) ?></p>
                        </div>
                        <div class="progress-track" aria-label="<?= h($progress . '% collected') ?>">
                          <span style="width: <?= h((string) min(100, $progress)) ?>%"></span>
                        </div>
                        <div class="metric-row">
                          <span>Collected <strong><?= money($payment['collected']) ?></strong></span>
                          <span>Expected <strong><?= money($expectedAmount) ?></strong></span>
                          <span>Paid <strong><?= h((string) $paid) ?>/<?= h((string) $eligible) ?></strong></span>
                          <span>Unpaid <strong><?= h((string) $unpaid) ?></strong></span>
                          <span>Pending checkout <strong><?= h((string) $payment['pending_count']) ?></strong></span>
                        </div>
                      </article>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>

            <?php if ($role === 'lecturer' && $section !== 'create' && $section !== 'analysis'): ?>
              <section class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow"><?= $section === 'items' ? 'My items' : 'Lecturer overview' ?></p>
                    <h3>Payment items</h3>
                  </div>
                  <a class="secondary-button" href="dashboard.php?section=create">New item</a>
                </header>

                <div class="stack">
                  <?php if (!$payments): ?>
                    <div class="empty-state">Create a payment item for your students to begin collecting departmental payments.</div>
                  <?php endif; ?>

                  <?php foreach ($payments as $payment): ?>
                    <article class="list-row">
                      <div class="list-main">
                        <strong><?= h($payment['title']) ?></strong>
                        <span class="list-meta"><?= h($payment['description']) ?></span>
                        <span class="list-meta"><?= h($payment['department']) ?> - <?= h($payment['level']) ?> - Due <?= h(date_label($payment['due_date'])) ?></span>
                      </div>
                      <div class="action-row">
                        <span class="amount-text"><?= money($payment['amount']) ?></span>
                        <span class="badge"><?= h((string) $payment['paid_count']) ?> paid</span>
                        <span class="badge gold"><?= money($payment['collected']) ?></span>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </section>

              <aside class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Student reach</p>
                    <h3><?= h($user['department']) ?></h3>
                  </div>
                </header>
                <div class="stack">
                  <?php foreach ($students as $student): ?>
                    <?= profile_row($student['surname'] . ', ' . $student['name'], $student['matricnumber'] . ' - ' . $student['level'] . ' level') ?>
                  <?php endforeach; ?>
                </div>
              </aside>
            <?php endif; ?>

            <?php if ($role === 'admin' && $section === 'records'): ?>
              <section class="panel-card span-all">
                <header>
                  <div>
                    <p class="eyebrow">Records</p>
                    <h3>All receipts</h3>
                  </div>
                  <form method="post" action="export_transactions.php">
                    <?= csrf_field() ?>
                    <button class="secondary-button" type="submit">Export CSV</button>
                  </form>
                </header>

                <?php if (!$transactions): ?>
                  <div class="empty-state">No student payment has been completed yet.</div>
                <?php else: ?>
                  <div class="table-wrap">
                    <table>
                      <thead>
                        <tr>
                          <th>Receipt</th>
                          <th>Reference</th>
                          <th>Student</th>
                          <th>Matric</th>
                          <th>Payment</th>
                          <th>Amount</th>
                          <th>Method</th>
                          <th>Date</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                          <tr>
                            <td><?= h($transaction['receipt_code']) ?></td>
                            <td><?= h($transaction['paystack_reference'] ?: '-') ?></td>
                            <td><?= h($transaction['name'] . ' ' . $transaction['surname']) ?></td>
                            <td><?= h($transaction['matricnumber']) ?></td>
                            <td><?= h($transaction['payment_title']) ?></td>
                            <td><?= money($transaction['amount']) ?></td>
                            <td><?= h($transaction['method'] . ($transaction['channel'] ? ' / ' . $transaction['channel'] : '')) ?></td>
                            <td><?= h(date_label($transaction['paid_at'])) ?></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </section>
            <?php endif; ?>

            <?php if ($role === 'admin' && $section === 'analysis'): ?>
              <section class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Analysis</p>
                    <h3>Department collection performance</h3>
                  </div>
                </header>

                <div class="stack">
                  <?php foreach ($departmentAnalysis as $department): ?>
                    <?php
                      $expectedAmount = (float) $department['expected'];
                      $collectedAmount = (float) $department['collected'];
                      $progress = percent_value($collectedAmount, $expectedAmount);
                    ?>
                    <article class="analysis-card compact">
                      <div class="analysis-head">
                        <div>
                          <h3><?= h($department['department']) ?></h3>
                          <p class="muted-text"><?= h((string) $department['student_count']) ?> students - <?= h((string) $department['payment_count']) ?> payment item(s)</p>
                        </div>
                        <strong><?= h($progress . '%') ?></strong>
                      </div>
                      <div class="progress-track" aria-label="<?= h($progress . '% collected') ?>">
                        <span style="width: <?= h((string) min(100, $progress)) ?>%"></span>
                      </div>
                      <div class="metric-row">
                        <span>Collected <strong><?= money($collectedAmount) ?></strong></span>
                        <span>Expected <strong><?= money($expectedAmount) ?></strong></span>
                        <span>Outstanding <strong><?= money(max(0, $expectedAmount - $collectedAmount)) ?></strong></span>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              </section>

              <aside class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Channels</p>
                    <h3>Payment methods</h3>
                  </div>
                </header>
                <div class="stack">
                  <?php if (!$methodAnalysis): ?>
                    <div class="empty-state">No payment channel data yet.</div>
                  <?php endif; ?>
                  <?php foreach ($methodAnalysis as $method): ?>
                    <?= profile_row($method['method'] . ' / ' . $method['channel'], $method['receipt_count'] . ' receipt(s) - NGN ' . number_format((float) $method['collected'], 0)) ?>
                  <?php endforeach; ?>
                  <?= profile_row('Pending Paystack checkout', (string) $pendingAttempts) ?>
                </div>
              </aside>
            <?php endif; ?>

            <?php if ($role === 'admin' && $section === 'import'): ?>
              <section class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">CSV import</p>
                    <h3>Bulk add students or lecturers</h3>
                  </div>
                </header>

                <form class="form-grid" method="post" action="import_people.php" enctype="multipart/form-data">
                  <?= csrf_field() ?>
                  <div class="field-group">
                    <label for="importType">Record type</label>
                    <select id="importType" name="import_type" required>
                      <option value="students">Students</option>
                      <option value="lecturers">Lecturers</option>
                    </select>
                  </div>

                  <div class="field-group">
                    <label for="csvFile">CSV file</label>
                    <input id="csvFile" name="csv_file" type="file" accept=".csv,text/csv" required>
                  </div>

                  <div class="wide action-row">
                    <button class="primary-button" type="submit">Import CSV</button>
                  </div>
                </form>
              </section>

              <aside class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Format</p>
                    <h3>Required headers</h3>
                  </div>
                </header>
                <div class="stack">
                  <?= profile_row('Students', 'name,surname,matricnumber,department,level') ?>
                  <?= profile_row('Lecturers', 'name,surname,teachercode,department') ?>
                  <div class="empty-state">Existing matric numbers and teacher codes are updated instead of duplicated.</div>
                </div>
              </aside>
            <?php endif; ?>

            <?php if ($role === 'admin' && $section === 'database'): ?>
              <section class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Database</p>
                    <h3>Seeded data structure</h3>
                  </div>
                </header>
                <div class="stack">
                  <?= profile_row('Student CSV', 'data/students.csv - name, surname, matricnumber, department, level') ?>
                  <?= profile_row('Lecturer CSV', 'data/lecturers.csv - name, surname, teachercode, department') ?>
                  <?= profile_row('SQL schema', 'data/schema.sql - admins, students, lecturers, payments, payment_attempts, transactions') ?>
                  <?= profile_row('Paystack callback', current_app_url() . '/paystack_callback.php') ?>
                  <?= profile_row('Database engine', 'MySQL with PDO prepared statements') ?>
                </div>
              </section>

              <aside class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Admin access</p>
                    <h3>Finance office</h3>
                  </div>
                </header>
                <div class="stack">
                  <?= profile_row('Admin ID', $user['surname']) ?>
                  <?= profile_row('Admin code', $user['admin_code']) ?>
                  <?= profile_row('Office', $user['department']) ?>
                </div>
              </aside>
            <?php endif; ?>

            <?php if ($role === 'admin' && $section === 'overview'): ?>
              <section class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">Admin overview</p>
                    <h3>Departmental payment items</h3>
                  </div>
                </header>
                <div class="table-wrap">
                  <table>
                    <thead>
                      <tr>
                        <th>Payment</th>
                        <th>Department</th>
                        <th>Lecturer</th>
                        <th>Amount</th>
                        <th>Due</th>
                        <th>Paid</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($payments as $payment): ?>
                        <tr>
                          <td><?= h($payment['title']) ?></td>
                          <td><?= h($payment['department'] . ' / ' . $payment['level']) ?></td>
                          <td><?= h($payment['lecturer_name']) ?></td>
                          <td><?= money($payment['amount']) ?></td>
                          <td><?= h(date_label($payment['due_date'])) ?></td>
                          <td><?= h((string) $payment['paid_count']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </section>

              <aside class="panel-card">
                <header>
                  <div>
                    <p class="eyebrow">People</p>
                    <h3>Records</h3>
                  </div>
                </header>
                <div class="stack">
                  <?= profile_row('Students', count($students) . ' student records') ?>
                  <?= profile_row('Lecturers', count($lecturers) . ' lecturer records') ?>
                  <?= profile_row('Payment owners', count(array_unique(array_column($payments, 'lecturer_id'))) . ' lecturer(s)') ?>
                </div>
              </aside>
            <?php endif; ?>
          </div>
        </main>
      </section>
    </div>
  </body>
</html>
