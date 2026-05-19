# Paytec

A PHP and MySQL school payment web app by Da4lions for departmental collections. The first page is a shared login screen, then users are routed into the correct dashboard for admin, lecturer, or student.

## Demo Logins (Local Only)

- Student: `Okafor` / `DPT/CSC/24/001`
- Lecturer: `Mensah` / `TCH-CS-104`
- Admin: `admin` / `ADMIN-001`

## What It Does

- Students log in with surname and matric number.
- Lecturers log in with surname and teacher code.
- Admin logs in with admin ID and admin code.
- Lecturers create payment items for their department and selected level.
- Students see matching payment items and pay through Paystack checkout.
- Paystack payments are verified server-side before receipts are created.
- Lecturer split settlement accounts can be linked in **My account**.
- Student checkout uses Paystack subaccount split routing per payment owner.
- Lecturers and admins have analysis sections for collection progress.
- Lecturers can export paid student CSV per payment item from the **Exports** section.
- Admin can bulk import students or lecturers from one CSV file.
- Receipts are stored in MySQL and can be exported by admin as CSV.
- Student and lecturer seed data are provided as CSV files.

## Setup

1. Create/import the database using `data/schema.sql`.
2. Set environment variables (copy `.env.example` and fill real values).
3. Put the folder inside your PHP server root, for example `htdocs/Paytech` in XAMPP.
4. Visit `http://localhost/Paytech/index.php`.

If env vars are not set locally, defaults are:

```php
DB_HOST = 127.0.0.1
DB_PORT = 3306
DB_NAME = departmentpay
DB_USER = root
DB_PASS = ''
```

## Paystack Setup

Set these as environment variables:

```php
APP_ENV = production
APP_DEBUG = false
PAYSTACK_SECRET_KEY = sk_test_xxxxx
PAYSTACK_PUBLIC_KEY = pk_test_xxxxx
PAYSTACK_CURRENCY = NGN
PAYSTACK_PLATFORM_PERCENTAGE = 10
PAYSTACK_SPLIT_BEARER = account
APP_URL = https://your-public-domain-or-ngrok/Paytech
PAYSTACK_CALLBACK_URL = https://your-public-domain-or-ngrok/Paytech/paystack_callback.php
```

If your app runs in a subfolder (for example `/Paytech`), include that path in both URLs.
If `APP_URL` and `PAYSTACK_CALLBACK_URL` are left empty, the app auto-detects the base URL from the current request.

Set Paystack webhook URL in your Paystack dashboard to:

```text
https://your-public-domain-or-ngrok/Paytech/paystack_webhook.php
```

If you already imported an older version of the database, run:

```sql
SOURCE C:/Users/da4li/Documents/Paytech/data/migration_paystack_import_analysis.sql
SOURCE C:/Users/da4li/Documents/Paytech/data/migration_paystack_subaccounts.sql
```

## Production Launch Checklist

1. Rotate Paystack keys and use `sk_live_...` / `pk_live_...` in env vars only.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, and `SESSION_SECURE_COOKIE=true`.
3. Remove demo records and replace seeded admin/student/lecturer credentials.
4. Use a non-root database user with a strong password.
5. Enable HTTPS on your domain before opening login/payment pages.
6. Set webhook URL to `https://your-domain/paystack_webhook.php`.
7. Confirm `data/` and `storage/` are not publicly accessible.
8. Run a real payment test end-to-end (checkout, callback, webhook, receipt).
9. Configure daily database backups.

## Deploy on Railway (Recommended)

1. Push this repository to GitHub.
2. In Railway, create a new project from the repo (Dockerfile is included).
3. Add a MySQL service in the same Railway project.
4. Set app environment variables from `.env.example` values.
5. Point `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` to Railway MySQL credentials.
6. Import `data/schema.sql` into the Railway MySQL database.
7. Add your custom domain and enable HTTPS.
8. Set Paystack callback and webhook URLs to your live domain.

## Files

- `index.php` - shared login page
- `dashboard.php` - admin, lecturer, and student dashboards
- `create_payment.php` - lecturer payment item handler
- `lecturer_account.php` - lecturer settlement account and subaccount setup handler
- `export_paid_students.php` - lecturer CSV export per payment item
- `pay.php` - Paystack checkout initialization
- `paystack_callback.php` - Paystack transaction verification and receipt generation
- `import_people.php` - admin CSV import handler
- `export_transactions.php` - admin CSV export
- `lib/` - session auth, PDO database helpers, formatting helpers, Paystack client
- `data/students.csv` - student seed records
- `data/lecturers.csv` - lecturer seed records
- `data/schema.sql` - MySQL schema and demo seed data
- `data/migration_paystack_import_analysis.sql` - non-destructive upgrade for existing databases
- `data/migration_paystack_subaccounts.sql` - adds lecturer bank/subaccount fields for split settlement
- `styles.css` - responsive fintech-style UI using the logo colors
