# DepartmentPay Portal

A PHP and MySQL school payment web app for departmental collections. The first page is a shared login screen, then users are routed into the correct dashboard for admin, lecturer, or student.

## Demo Logins

- Student: `Okafor` / `DPT/CSC/24/001`
- Lecturer: `Mensah` / `TCH-CS-104`
- Admin: `admin` / `ADMIN-001`

## What It Does

- Students log in with surname and matric number.
- Lecturers log in with surname and teacher code.
- Admin logs in with admin ID and admin code.
- Lecturers create payment items for their department and selected level.
- Students see matching payment items on their dashboard and can record a payment.
- Receipts are stored in MySQL and can be exported by admin as CSV.
- Student and lecturer seed data are provided as CSV files.

## Setup

1. Create/import the database using `data/schema.sql`.
2. Update database settings in `config.php` if needed.
3. Put the folder inside your PHP server root, for example `htdocs/Paytech` in XAMPP.
4. Visit `http://localhost/Paytech/index.php`.

Default MySQL settings in `config.php` are:

```php
DB_HOST = 127.0.0.1
DB_PORT = 3306
DB_NAME = departmentpay
DB_USER = root
DB_PASS = ''
```

## Files

- `index.php` - shared login page
- `dashboard.php` - admin, lecturer, and student dashboards
- `create_payment.php` - lecturer payment item handler
- `pay.php` - student payment/receipt handler
- `export_transactions.php` - admin CSV export
- `lib/` - session auth, PDO database helpers, formatting helpers
- `data/students.csv` - student seed records
- `data/lecturers.csv` - lecturer seed records
- `data/schema.sql` - MySQL schema and demo seed data
- `styles.css` - responsive fintech-style UI using the logo colors

