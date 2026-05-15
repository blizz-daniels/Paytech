USE departmentpay;

CREATE TABLE IF NOT EXISTS payment_attempts (
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

ALTER TABLE transactions
  ADD COLUMN payer_email VARCHAR(180) DEFAULT NULL AFTER method,
  ADD COLUMN paystack_reference VARCHAR(80) DEFAULT NULL AFTER payer_email,
  ADD COLUMN paystack_transaction_id BIGINT UNSIGNED DEFAULT NULL AFTER paystack_reference,
  ADD COLUMN channel VARCHAR(40) DEFAULT NULL AFTER paystack_transaction_id,
  ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT 'NGN' AFTER channel,
  ADD COLUMN gateway_response VARCHAR(160) DEFAULT NULL AFTER currency,
  ADD COLUMN raw_response LONGTEXT DEFAULT NULL AFTER gateway_response,
  ADD UNIQUE KEY uniq_paystack_reference (paystack_reference);

