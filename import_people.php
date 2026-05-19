<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';

require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?section=import');
    exit;
}

verify_csrf();

$type = (string) ($_POST['import_type'] ?? '');
$file = $_FILES['csv_file'] ?? null;

if (!in_array($type, ['students', 'lecturers'], true) || !$file || (int) $file['error'] !== UPLOAD_ERR_OK) {
    header('Location: dashboard.php?section=import&message=' . rawurlencode('Choose a valid CSV file and import type.'));
    exit;
}

$handle = fopen((string) $file['tmp_name'], 'r');

if (!$handle) {
    header('Location: dashboard.php?section=import&message=' . rawurlencode('Could not read the uploaded CSV file.'));
    exit;
}

$headers = fgetcsv($handle);

if (!$headers) {
    fclose($handle);
    header('Location: dashboard.php?section=import&message=' . rawurlencode('The CSV file is empty.'));
    exit;
}

$headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
$headers = array_map(static fn ($header): string => strtolower(trim((string) $header)), $headers);
$required = $type === 'students'
    ? ['name', 'surname', 'matricnumber', 'department', 'level']
    : ['name', 'surname', 'teachercode', 'department'];
$missing = array_diff($required, $headers);

if ($missing) {
    fclose($handle);
    header('Location: dashboard.php?section=import&message=' . rawurlencode('Missing CSV column(s): ' . implode(', ', $missing)));
    exit;
}

$imported = 0;
$skipped = 0;

db()->beginTransaction();

try {
    while (($row = fgetcsv($handle)) !== false) {
        if (count(array_filter($row, static fn ($value): bool => trim((string) $value) !== '')) === 0) {
            continue;
        }

        $record = [];
        foreach ($headers as $index => $header) {
            $record[$header] = trim((string) ($row[$index] ?? ''));
        }

        foreach ($required as $field) {
            if (($record[$field] ?? '') === '') {
                $skipped++;
                continue 2;
            }
        }

        if ($type === 'students') {
            $stmt = db()->prepare(
                'INSERT INTO students (name, surname, matricnumber, department, level)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   name = VALUES(name),
                   surname = VALUES(surname),
                   department = VALUES(department),
                   level = VALUES(level)'
            );
            $stmt->execute([
                $record['name'],
                $record['surname'],
                $record['matricnumber'],
                $record['department'],
                $record['level'],
            ]);
        } else {
            $stmt = db()->prepare(
                'INSERT INTO lecturers (name, surname, teachercode, department)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   name = VALUES(name),
                   surname = VALUES(surname),
                   department = VALUES(department)'
            );
            $stmt->execute([
                $record['name'],
                $record['surname'],
                $record['teachercode'],
                $record['department'],
            ]);
        }

        $imported++;
    }

    fclose($handle);
    db()->commit();

    header('Location: dashboard.php?section=import&message=' . rawurlencode("CSV import complete: {$imported} row(s) imported, {$skipped} skipped."));
    exit;
} catch (Throwable $exception) {
    fclose($handle);
    db()->rollBack();
    error_log('CSV import failed: ' . $exception->getMessage());
    header('Location: dashboard.php?section=import&message=' . rawurlencode('CSV import failed. Please verify the file format and try again.'));
    exit;
}
