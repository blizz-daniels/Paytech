USE departmentpay;

ALTER TABLE lecturers
  ADD COLUMN bank_code VARCHAR(20) DEFAULT NULL AFTER department,
  ADD COLUMN bank_name VARCHAR(160) DEFAULT NULL AFTER bank_code,
  ADD COLUMN account_number VARCHAR(20) DEFAULT NULL AFTER bank_name,
  ADD COLUMN account_name VARCHAR(160) DEFAULT NULL AFTER account_number,
  ADD COLUMN paystack_subaccount_code VARCHAR(80) DEFAULT NULL AFTER account_name,
  ADD COLUMN paystack_subaccount_id BIGINT UNSIGNED DEFAULT NULL AFTER paystack_subaccount_code,
  ADD COLUMN paystack_subaccount_active TINYINT(1) NOT NULL DEFAULT 0 AFTER paystack_subaccount_id,
  ADD COLUMN paystack_last_error VARCHAR(255) DEFAULT NULL AFTER paystack_subaccount_active,
  ADD COLUMN paystack_synced_at DATETIME DEFAULT NULL AFTER paystack_last_error,
  ADD UNIQUE KEY uniq_lecturer_subaccount_code (paystack_subaccount_code);
