# Decisions

## Phase 1

- Use the current CodeIgniter app starter (4.7.5) as the application base.
- Keep UI assets local; npm packages are build-time inputs and generated assets live under `public/assets/`.
- `/dev/ui` is available in development and test environments, and returns 404 in production.
- Prototype images are provided as references only; supplied assets are not modified or copied into the runtime app in this phase.

## Remaining phases

- Follow the prompt assumptions: staff provision parent accounts, children do not log in, and parent-submitted children stay pending until staff verification.
- Enforce parent ownership through the Guardian-to-Child link and return 404 for unlinked child resources.
- Do not bundle guessed WHO references. Import official LMS tables from writable/who.
- Keep SMS sending in scheduled workers; HTTP requests only write queued message rows.
- Reports read persisted records and return aggregate data from database queries.
- Treat the supplied vaccine schedule as editable starting data. A qualified local health professional must confirm it before clinical use.
- The current runtime has GD and ZIP, so image resizing and XLSX generation are available. A reachable MySQL service and SQLite extension are unavailable here, so migrations, seed execution, and DB-backed flow tests need a database-enabled environment.
- Parent mobile-number changes must use OTP confirmation before updating the normalized number.
- Guardian SMS reminders and health-center messages require explicit opt-in; account creation and contact imports never opt guardians in implicitly. OTP and account-security codes are sent for authentication regardless of campaign preferences.
- Allow a parent who opts into an extra dose reminder to receive that separately keyed reminder; repeated reminder jobs use stable keys to avoid re-queueing it.
- The center logo is an optional locally uploaded PNG/JPG. Until one is uploaded, render a neutral text mark instead of a broken or fabricated logo.

- Schedule bulk reminders queue one earliest pending dose per selected child for opted-in primary guardians; only the SMS worker delivers them.
- Report PDF exports may include a PNG snapshot submitted by the report chart; the controller accepts only bounded, validated PNG data. Multi-report Excel exports use one worksheet per report.

## Phase 11 clinical verification findings

- The seeded catalog remains an unapproved starting schedule. The DOH National Immunization Program manual available at [Conducting Immunization Sessions](https://doh.gov.ph/wp-content/uploads/2023/08/Booklet-4-Conducting-Immunization-Sessions.pdf) lists rotavirus doses at 6 and 10 weeks and Japanese encephalitis at 9 months, which are absent from the prompt's seed list; it also shows an IPV dose at 14 weeks. The seed list has a second IPV dose at 9 months. A qualified local NIP lead must reconcile the center's current schedule and FIC reporting definition before activating the catalog. No catalog values were changed based on an unreviewed source.
- WHO's official [Child Growth Standards expanded tables](https://www.who.int/tools/child-growth-standards/standards) provide the source spreadsheets for 0–5-year references. The application still requires preparation and import of the official tables; no LMS values are bundled or synthesized.
- Use Alpine's local CSP build and simple template expressions to keep the script policy free of `unsafe-eval`.
- Default HTTPS enforcement and Secure session cookies on when `CI_ENVIRONMENT=production`; local development remains HTTP-capable. Deployments behind a TLS-terminating proxy must trust only the configured proxy addresses.
