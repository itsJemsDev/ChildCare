# Barangay ChildCare

A CodeIgniter 4 web application for child health records, immunization scheduling, growth monitoring, reminders, reports, and a parent portal for Barangay Kalawag 1 Health Center-B.

## Requirements

- PHP 8.2+ with MySQLi, intl, mbstring, fileinfo, cURL, GD, and ZIP enabled.
- MySQL 8.0+ or MariaDB 10.6+, InnoDB, utf8mb4.
- Composer 2, Node.js 20+, npm.
- A writable PHP runtime folder and HTTPS in production.

GD is needed to resize uploaded child photos. ZIP is needed for XLSX export. Dompdf creates PDF reports and child record summaries. No clinical growth reference data is bundled.

## Local install (Linux)

1. Install PHP, MySQL, Composer, Node/npm, and the PHP extensions above.
2. Create a MySQL database and account. Use utf8mb4_unicode_ci; grant the app account access to it.
3. Copy .env.example to .env. Set CI_ENVIRONMENT, app.baseURL, a strong encryption.key (generate with php spark key:generate), and database.default hostname/database/username/password.
4. Install PHP and JS dependencies:

       composer install
       npm install
       npm run build

5. Run Shield and application migrations and seed initial values:

       php spark migrate --all
       php spark db:seed DatabaseSeeder

6. The seeder prints a random initial admin password once. Sign in as username admin, choose Administrator, and change the password at first sign-in. Store the password securely.
7. Serve locally with php spark serve. For production, point the web server document root at public/.

## Local demo data

After migrations and the regular `DatabaseSeeder` have completed, add synthetic child profiles and related growth, immunization, and check-up entries with:

       php spark db:seed DemoDataSeeder

This seeder only runs when `CI_ENVIRONMENT = development`. Its six clearly labeled demo profiles use non-deliverable `.invalid` email addresses, synthetic phone numbers, and SMS opt-out. Health values and vaccine history are mock UI data, not clinical records. The seeder is idempotent; rerunning it will not add duplicate demo profiles. Do not use it with production data.

## XAMPP / Laragon (Windows)

1. Install PHP 8.2 or newer and enable mysqli, intl, mbstring, fileinfo, curl, gd, and zip in the active php.ini. Restart Apache after editing.
2. Start MySQL and create a database named childcare with utf8mb4_unicode_ci; create an app-specific database user.
3. Open a terminal in the project. Copy .env.example to .env and set database credentials and app.baseURL, for example http://localhost/ChildCare/public/. Generate an encryption key with php spark key:generate.
4. Run composer install, npm install, npm run build, php spark migrate --all, and php spark db:seed DatabaseSeeder.
5. Configure Apache's document root to public/ for a production-like setup. Keep writable/ outside the publicly served folder and grant PHP write access to it.

## WHO growth reference tables

Obtain official WHO Child Growth Standards expanded tables and WHO 2007 reference tables from the WHO source publications. Prepare CSV or tab-delimited TXT files under writable/who/ with columns indicator, sex, x_value, l, m, s. Indicators are wfa, lhfa, wfl, wfh, or bfa; sex is male or female. x_value uses age in days for 0–5y age standards, age in months for the older reference, or length/height in cm for WFL/WFH. Do not alter LMS values. Run:

       php spark who:import

The command reports missing or invalid data and does not generate placeholder values. Verify row counts and units against the official source before entering clinical measurements.

## SMS setup

The development driver is log; it never sends an external message. Set these values in .env to use Semaphore:

       sms.driver = semaphore
       sms.semaphore.apiKey = YOUR_SEMAPHORE_API_KEY
       sms.semaphore.senderName = YOUR_APPROVED_SENDER

Keep API credentials private. Configure an approved sender name with Semaphore. The system queues one personalized message per guardian and the worker sends messages outside the request cycle. OTPs are hashed in the database. Use the log driver to review local development logs.

To enable password-reset email links, configure the SMTP settings (`email.protocol`, `email.SMTPHost`, `email.SMTPUser`, `email.SMTPPass`, `email.SMTPPort`, `email.SMTPCrypto`, `email.fromEmail`, and `email.fromName`) in `.env`. Email tokens are single-use and expire after 30 minutes; SMS reset remains available.

## Scheduled jobs

Run these with the same PHP executable and environment as the web app:

       * * * * * cd /path/to/ChildCare && php spark sms:dispatch
       */15 * * * * cd /path/to/ChildCare && php spark sms:sync-status
       0 7 * * * cd /path/to/ChildCare && php spark reminders:generate
       0 2 * * * cd /path/to/ChildCare && php spark db:backup

On Windows Task Scheduler, create separate repeating tasks with Program set to php.exe, Arguments set to spark sms:dispatch (repeat every minute), spark sms:sync-status (15 minutes), spark reminders:generate (7:00 AM), and spark db:backup (daily). Set Start in to the project root.

Backups are gzip files under writable/backups/; retain and copy backups to a separate protected location according to the center's retention policy.

## Tests and checks

Configure a separate test database before running the suite. Then run:

       vendor/bin/phpunit --no-coverage

   The starter database tests require the PHP SQLite extension. They are skipped automatically when `sqlite3` is unavailable. Install Xdebug or PCOV to collect coverage; remove `--no-coverage` when a coverage driver is enabled.

Also confirm the web server uses public/, no secrets are committed, HTTPS is enabled, cron jobs run, and uploads/backups are private. Do not load WHO data or real child data into development.

## Production checklist

- Set `CI_ENVIRONMENT = production` and `app.baseURL` to the canonical `https://` address. Production defaults redirect HTTP to HTTPS and set the session cookie's Secure flag; do not override `app.forceGlobalSecureRequests` or `cookie.secure` to `false`.
- If TLS terminates at a reverse proxy, configure `app.proxyIPs` with only the proxy addresses so CI4 can identify the original HTTPS request safely. Do not trust forwarded headers from arbitrary clients.
- Point the web server document root to `public/`. Keep `.env`, `writable/`, `vendor/`, and project source inaccessible from HTTP. Grant PHP write access only to required `writable/` subdirectories.
- Use a dedicated least-privilege MySQL account, strong encryption key, private SMTP/Semaphore credentials, and a non-default admin password. Do not copy production data into development or test databases.
- Run migrations and seeders against the intended new database, then verify staff/parent authorization, cross-family IDOR denial, uploads, exports, and the full reminder queue/worker flow before pilot use.
- Configure and test daily backups, verify a gzip backup, perform a restore drill into a separate empty database, and copy backups to a separate protected location. Keep at most 14 local copies; apply the center's approved retention policy to off-site copies.
- Obtain local clinical approval for the vaccine catalog and FIC rule, import official WHO reference files, validate row coverage and units, and document the review date before recording real measurements.
- Complete the center's privacy review, set staff idle timeout and OTP controls, and confirm logging does not expose OTPs, API keys, or full phone numbers.
- Schedule SMS dispatch (1 minute), provider status sync (15 minutes), reminder generation (daily at 07:00 Asia/Manila), and backup (daily at 02:00 Asia/Manila). Monitor command logs and alert staff to failed sends and backup failures.

## Clinical and privacy notes

- The seeded vaccine schedule follows the project specification and needs review against current DOH guidance by a qualified local health professional before pilot use.
- Growth calculations return a clear missing-reference error until official WHO data is imported.
- Child and guardian health information is sensitive personal information. Limit accounts to authorized staff and linked guardians, protect backups, and follow the center's Data Privacy Act procedures.
- Review the production gates in docs/PROGRESS.md and implementation choices in docs/DECISIONS.md.

See docs/ERD.md and docs/USER-GUIDE.md.
