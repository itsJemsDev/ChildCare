# Build progress

Phase 1 was completed before this continuation. The core workflows are implemented, with runtime and clinical acceptance still open. This workspace has no reachable MySQL service and lacks SQLite; GD and ZIP are available in the current PHP runtime.

| Phase | Status | Included |
|---|---|---|
| 1. Scaffold and UI foundation | Complete | CI4 4.7, local Tailwind pipeline and vendor assets, layouts, theme, reusable components, development UI guide. |
| 2. Authentication | Implemented; runtime validation pending | Shield groups, role-aware password login, SMS OTP login/2FA with resend, SMS reset and expiring email reset links, forced password setup, idle timeout and throttling. |
| 3. Settings and users | Implemented; runtime validation pending | Validated center settings and optional logo upload, puroks, editable/activatable vaccine and dose catalog, user creation and activation, audit log, staff low-credit/failure alerts, parent SMS preferences. |
| 4. Child profiles | Implemented; runtime validation pending | Search/age/sex/purok/category/immunization/registration filters, create/edit including primary guardian, private document and photo changes, consent, duplicate warning, Child ID, pending verification/rejection, filtered Excel export. |
| 5. Immunization | Implemented; clinical review pending | Data-driven catalog, service-level administration timing checks, recording/edit/undo with override reason, next-dose recomputation, schedule list/calendar/pending/completed views, filters and exports, next-dose display, selected-child reminder queueing. Pure schedule rules have unit coverage. Confirm seeded schedule against current DOH guidance before pilot use. |
| 6. Growth | Implemented; official WHO data required | LMS import command, z-score/classification services, preview endpoint, history and chart. No WHO values are bundled. |
| 7. Medical history | Implemented; runtime validation pending | Check-up create/edit/soft-delete, outcomes, follow-up date, latest check-up summary recomputation, parent read-only history. |
| 8. SMS | Implemented; provider setup pending | Log/Semaphore gateways, hashed OTPs, queue, compose/count estimate, CSV import, explicit opt-in, template CRUD/archive, failed-message retry, dispatch/status/reminder commands, quiet hours and idempotency keys. |
| 9. Dashboard and reports | Implemented; runtime validation pending | Staff dashboard, date/Purok-filtered report JSON endpoints and KPIs, charts, chart-bearing PDF exports, filtered XLSX exports and a multi-sheet workbook, masterlist and overdue lists. |
| 10. Parent portal | Implemented; runtime validation pending | Linked-child dashboard with child switcher, upcoming counts, next-dose reminder, dual-axis growth chart and recent notifications; ownership checks; pending child registration; appointments; documents; notifications; profile; records PDF; and optional extra dose reminders. |
| 11. Hardening and docs | Implemented; acceptance pending | CSRF, CSP/security headers without `unsafe-eval`, production HTTPS/Secure-cookie defaults, private uploads, backups, privacy notice, setup/operations/user docs, optional uploaded center logo, and production runbook. MySQL-backed and live deployment acceptance still require the target environment. |

## Local verification

- Full PHP syntax checks, route listing, local JavaScript syntax checks, and Tailwind production build pass.
- `vendor/bin/phpunit --no-coverage`: 15 tests pass with 36 assertions; the SQLite-dependent starter database test class is skipped because this PHP install has no SQLite extension.
- XLSX export smoke checks produced readable workbooks with the available ZIP extension.
- The WHO import command correctly returns a clear nonzero error until official WHO files are present.
- `php spark migrate --all` cannot connect to MySQL in this sandbox (`Operation not permitted`). Migrations/seeders, DB-backed feature tests and real web flows remain unverified here.
- GD and ZIP are available. SQLite and a reachable MySQL server are unavailable. No WHO tables or Semaphore credentials are present.

## Implemented in the latest continuation

- Completed report exports with filtered aggregate/detail rows, a multi-sheet workbook, and chart-bearing PDF support; completed filtered schedule exports and selected-child queued reminders.
- Added a parent dashboard child switcher, pending-dose count, next-dose reminder control, dual-axis height/weight chart, recent notifications, and profile summary.
- Made child profile edits persist primary guardian changes, replacement photos, and documents; added guardian SMS opt-out editing.
- Enforced clinical timing/date constraints in the immunization and growth services, and added unit coverage for schedule dates/statuses.
- Added staff notifications for terminal SMS failures/low credits and parent notifications for new clinical records.
- Added the missing reusable form components, SMS recipient preview and queue validation, settings/catalog validation, and email-link password reset.

- Added filtered child-list Excel export and export audit logging; added the missing age-group filter and preserved filter selections.
- Added medical check-ups to the parent child-record PDF.
- Completed SMS contact CSV import, safe opt-in defaults, live recipient/segment estimate, template update/archive, and failed-message retry.
- Added parent-controlled extra dose reminders with an independent idempotency key.
- Added immunization status and recent notifications to the staff dashboard.
- Fixed schema primary/unique keys, deferred FK processing, added user-related foreign keys, and aligned audit/medicine columns to JSON.
- Added immunization edit with interval/age checks, completed-dose undo ordering and downstream schedule recomputation; added check-up edit and latest check-up recomputation.
- Changed SMS consent defaults to opt-out and added parent-controlled SMS preferences; auth OTP remains a separate security message.
- Enabled CSP with local asset policy and Alpine compatibility; added admin-managed local logo upload and a neutral login fallback.
- Growth calculations no longer request WHO weight-for-age values beyond the 10-year reference range.
- Staff child registration now saves the child, guardian, documents, photo, schedule, optional first measurement, and Pink Card back-entry in one database transaction.
- Replaced the parent Documents raw data table with a per-child screen for review statuses, authorized file downloads, and document uploads; upload failures now return safe user-facing errors and remove the stored file if the database insert fails.
- Marked the starter SQLite database tests as extension-dependent and documented the no-coverage test command for environments without Xdebug/PCOV.
- Aligned the staff dashboard due-immunization KPI count with its child-based percentage, retained dose count as detail, excluded deleted doses, and invalidated older dashboard cache entries.
- Added distinct inline view and attachment download behavior for parent and staff document routes, with ownership checks and audit events.
- Replaced nonexistent Query Builder `paginate()` calls with a shared Pager-backed query paginator across child, check-up, appointment, SMS log, notification, user, growth, immunization, and schedule lists; added a database test for filtered pagination.
- Completed dashboard record-entry quick actions with a verified-child chooser, and added schedule status filters, pagination, and percentage KPIs.
- Added paginated audit-log filters for user, entity, action, and date range, with actor names shown in results.
- Hardened `db:backup` process handling so a full `mysqldump` stderr pipe cannot deadlock the stdout compression loop; temporary credential and error files are permission-restricted and cleaned up on handled failures.
- Backup output now restricts the backup directory and archive permissions on Unix, while leaving Windows ACLs to the host configuration.
- Corrected growth indicator transitions at the 2-, 5-, and 10-year boundaries to use calendar anniversaries, and added unit coverage for birthday boundaries. Reminder generation now excludes deleted or unverified children from follow-up and appointment SMS.
- Completed production defaults and runbook guidance: HTTPS redirects and Secure cookies default on in production, HTTP remains available in development, and the README now covers proxy trust, private paths, restore drills, clinical sign-off, and scheduled-job monitoring.
- Replaced the standard Alpine runtime with its local CSP build, removed `unsafe-eval` from the script policy, and simplified the growth preview expressions for CSP compatibility.
- Updated npm dependencies to resolve all four reported high-severity advisories; the follow-up `npm audit` reports zero vulnerabilities.
- Added a shared submit guard to disable duplicate form submissions and expose busy state across authentication, staff, and parent pages.

## Latest verification (2026-10-21)

- `php -l` across `app` and `tests`: pass.
- `php spark routes`: pass.
- `node --check` across `public/assets/js`: pass.
- `npm run build`: pass.
- `vendor/bin/phpunit --no-coverage`: 16 passing tests, 41 assertions, and one skipped SQLite-dependent test class.
- `php spark routes`, full PHP lint, JavaScript syntax checks, and `npm run build`: pass after the list/pagination fixes.
- `php -l app/Commands/DbBackup.php` and `php spark routes`: pass after backup process hardening; route list contains 125 routes.
- `vendor/bin/phpunit --no-coverage`: 16 passing tests, 41 assertions, one skipped SQLite-dependent class.
- Full PHP syntax checks across `app` and `tests`, all local JavaScript syntax checks, and route compilation: pass. `php spark who:import` reports the required official input files clearly and does not generate values.
- Production-mode configuration check confirms HTTPS redirect and Secure session-cookie defaults; production routes compile. Tailwind build copies Alpine's CSP build locally, and npm audit reports zero vulnerabilities.
- Database migrations and MySQL-backed feature flows remain unverified because this environment has no reachable MySQL service.

## Deployment gates

- Qualified local NIP lead: reconcile and approve the vaccine catalog and FIC definition against the center's current DOH schedule. The current prompt seed differs from the DOH NIP manual's listed rotavirus and JE doses; see docs/DECISIONS.md.
- Import official WHO 2006/2007 reference tables and validate the imported data.
- Configure Semaphore credentials in .env and schedule worker commands.
- Provision MySQL 8 or MariaDB 10.6+ and mysqldump; enable SQLite for the starter test suite or configure the project MySQL test database.
- Run migrations, seeders, automated tests, authorization/IDOR checks, and a privacy/security review.
