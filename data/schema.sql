CREATE DATABASE IF NOT EXISTS departmentpay
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE departmentpay;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS payment_attempts;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS lecturers;
DROP TABLE IF EXISTS students;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  surname VARCHAR(80) NOT NULL,
  admin_code VARCHAR(80) NOT NULL UNIQUE,
  department VARCHAR(120) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_admin_login (surname, admin_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  surname VARCHAR(80) NOT NULL,
  matricnumber VARCHAR(80) NOT NULL UNIQUE,
  department VARCHAR(120) NOT NULL,
  level VARCHAR(20) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_student_login (surname, matricnumber),
  INDEX idx_student_department_level (department, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lecturers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  surname VARCHAR(80) NOT NULL,
  teachercode VARCHAR(80) NOT NULL UNIQUE,
  department VARCHAR(120) NOT NULL,
  bank_code VARCHAR(20) DEFAULT NULL,
  bank_name VARCHAR(160) DEFAULT NULL,
  account_number VARCHAR(20) DEFAULT NULL,
  account_name VARCHAR(160) DEFAULT NULL,
  paystack_subaccount_code VARCHAR(80) DEFAULT NULL,
  paystack_subaccount_id BIGINT UNSIGNED DEFAULT NULL,
  paystack_subaccount_active TINYINT(1) NOT NULL DEFAULT 0,
  paystack_last_error VARCHAR(255) DEFAULT NULL,
  paystack_synced_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lecturer_login (surname, teachercode),
  INDEX idx_lecturer_department (department),
  UNIQUE KEY uniq_lecturer_subaccount_code (paystack_subaccount_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_code VARCHAR(40) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  description TEXT NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  department VARCHAR(120) NOT NULL,
  level VARCHAR(20) NOT NULL,
  lecturer_id INT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_lecturer
    FOREIGN KEY (lecturer_id) REFERENCES lecturers(id)
    ON DELETE CASCADE,
  INDEX idx_payment_audience (department, level, status),
  INDEX idx_payment_lecturer (lecturer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_code VARCHAR(40) NOT NULL UNIQUE,
  payment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  method VARCHAR(40) NOT NULL,
  payer_email VARCHAR(180) DEFAULT NULL,
  paystack_reference VARCHAR(80) DEFAULT NULL,
  paystack_transaction_id BIGINT UNSIGNED DEFAULT NULL,
  channel VARCHAR(40) DEFAULT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'NGN',
  gateway_response VARCHAR(160) DEFAULT NULL,
  raw_response LONGTEXT DEFAULT NULL,
  paid_at DATETIME NOT NULL,
  CONSTRAINT fk_transactions_payment
    FOREIGN KEY (payment_id) REFERENCES payments(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_transactions_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,
  UNIQUE KEY uniq_student_payment (payment_id, student_id),
  UNIQUE KEY uniq_paystack_reference (paystack_reference),
  INDEX idx_transaction_paid_at (paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference VARCHAR(80) NOT NULL UNIQUE,
  payment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12, 2) NOT NULL,
  payer_email VARCHAR(180) NOT NULL,
  authorization_url TEXT DEFAULT NULL,
  access_code VARCHAR(120) DEFAULT NULL,
  status ENUM('initialized', 'verified', 'failed') NOT NULL DEFAULT 'initialized',
  gateway_response VARCHAR(160) DEFAULT NULL,
  raw_response LONGTEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  verified_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_payment_attempts_payment
    FOREIGN KEY (payment_id) REFERENCES payments(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_payment_attempts_student
    FOREIGN KEY (student_id) REFERENCES students(id)
    ON DELETE CASCADE,
  INDEX idx_payment_attempts_student (student_id),
  INDEX idx_payment_attempts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO admins (name, surname, admin_code, department) VALUES
  ('Bursary', 'admin', 'ADMIN-001', 'Finance Office');

INSERT INTO students (name, surname, matricnumber, department, level) VALUES
  ('Amara', 'Okafor', 'DPT/CSC/24/001', 'Computer Science', '200'),
  ('Tobi', 'Adeyemi', 'DPT/CSC/24/002', 'Computer Science', '200'),
  ('Mariam', 'Bello', 'DPT/BUS/24/018', 'Business Administration', '100'),
  ('Samuel', 'Eze', 'DPT/ACC/23/041', 'Accounting', '300'),
  ('Ifedayo', 'Lawal', 'DPT/MKT/22/117', 'Marketing', '400');

INSERT INTO lecturers (name, surname, teachercode, department) VALUES
  ('Daniel', 'Mensah', 'TCH-CS-104', 'Computer Science'),
  ('Folake', 'Adebayo', 'TCH-BUS-207', 'Business Administration'),
  ('Grace', 'Ibrahim', 'TCH-ACC-318', 'Accounting'),
  ('Peter', 'Nwosu', 'TCH-MKT-422', 'Marketing');

INSERT INTO payments (payment_code, title, description, amount, department, level, lecturer_id, due_date, status) VALUES
  (
    'PAY-TXT-200',
    'Departmental Textbook Pack',
    'Core textbooks and departmental handbook for CSC 201.',
    18500,
    'Computer Science',
    '200',
    (SELECT id FROM lecturers WHERE teachercode = 'TCH-CS-104'),
    '2026-06-07',
    'active'
  ),
  (
    'PAY-LAB-200',
    'Software Laboratory Access',
    'Lab access, account setup, and semester practical support.',
    12000,
    'Computer Science',
    '200',
    (SELECT id FROM lecturers WHERE teachercode = 'TCH-CS-104'),
    '2026-06-14',
    'active'
  ),
  (
    'PAY-BUS-100',
    'Entrepreneurship Workbook',
    'Workbook and assessment booklet for first year students.',
    9500,
    'Business Administration',
    '100',
    (SELECT id FROM lecturers WHERE teachercode = 'TCH-BUS-207'),
    '2026-06-10',
    'active'
  );

INSERT INTO transactions (receipt_code, payment_id, student_id, amount, method, payer_email, channel, currency, gateway_response, paid_at) VALUES
  (
    'RCP-SEED-001',
    (SELECT id FROM payments WHERE payment_code = 'PAY-TXT-200'),
    (SELECT id FROM students WHERE matricnumber = 'DPT/CSC/24/001'),
    18500,
    'Manual seed',
    'amara.okafor@example.edu.ng',
    'seed',
    'NGN',
    'Seeded receipt',
    '2026-05-15 10:30:00'
  );
