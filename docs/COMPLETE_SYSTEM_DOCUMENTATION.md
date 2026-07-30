# IECEP-LSC MEMSYS - Complete System Documentation

## Overview

This document provides a complete technical manual for the `IECEP-LSC MEMSYS` project. It includes:
- full folder structure
- core architecture and component responsibilities
- key file-level documentation for major files and services
- authentication, API, database, and security descriptions
- feature summaries and system workflows
- developer guidance and maintenance notes

> Note: A complete file inventory is available in `docs/full_file_index.txt` and lists every project file.

---

## Project Folder Structure

The project is organized into the following primary folders:

- `/assets/` — static image and icon assets used by the web UI.
- `/cron/` — scheduled maintenance and background task scripts.
- `/database/` — SQL schema files, migrations, and database enhancement scripts.
- `/docs/` — project documentation, testing checklists, thesis helper notes, and the full file inventory.
- `/includes/` — shared PHP components, helper functions, middleware, layout fragments, and configuration.
- `/logs/` — runtime and error logs.
- `/migrations/` — additional schema migration SQL files.
- `/public/` — public web-accessible pages, API proxy, portal user interfaces, service worker, and frontend resources.
- `/src/` — application source code, core libraries, and configuration classes.
- `/tools/` — developer tooling including Supabase utility scripts.

### Folder Communication

- The browser requests pages under `public/`.
- Public pages include `includes/` files for shared logic, layout, and configuration.
- `bootstrap.php` and `includes/config.php` initialize environment variables and define constants.
- `src/` contains reusable services such as Supabase clients, blockchain auditing, and compliance engines.
- `public/api.php` routes API calls into individual endpoint handlers in `public/api/`.
- `database/` stores the SQL definitions that correspond to the Supabase backend and the local XAMPP environment.
- `cron/` scripts use the same shared library layers to perform automated maintenance and notifications.

---

## Key Files and Their Roles

### Root Files

#### `bootstrap.php`
- Purpose: Initialize the application environment.
- Role: Central bootstrap for error reporting, session startup, auto-loading, `.env` loading, constant definitions, and Supabase client creation.
- Used By: Nearly every PHP entrypoint in the app, including `login.php`, `public/api.php`, `includes/config.php`, and all portal pages.
- Dependencies: `includes/config.php`, `src/lib/SupabaseClient.php`, `includes/supabase.php`.
- Responsibilities:
  - Enable error reporting and display during development.
  - Start PHP sessions safely.
  - Define constants like `PROJECT_ROOT`, `BASE_PATH`, `PUBLIC_PATH`, `APP_URL`, `API_URL`, and `STORAGE_URL`.
  - Load environment variables from `.env` into `$_ENV` and as OS-level environment variables.
  - Register a PSR-4-compatible autoloader for `src/`.
  - Provide global helper functions: `get_path()`, `get_url()`, and `supabase()`.
- Security: Logs fatal bootstrap failures and protects `SupabaseClient` initialization errors.

#### `includes/config.php`
- Purpose: Load environment configuration and define application constants.
- Role: Provides global configuration values to all application code.
- Used By: `bootstrap.php`, `public/api.php`, `public/portal/*`, `includes/*`, and any page that imports configuration.
- Dependencies: `bootstrap.php`, `.env` file, PHP environment variables.
- Responsibilities:
  - Load `.env` with `loadEnv()` and expose values via `env()`.
  - Define `APP_NAME`, `APP_URL`, `APP_ENV`, Supabase keys, SMTP configuration, JWT secret, session lifetime, and file upload limits.
  - Define database constants like `TABLE_USERS`, `TABLE_MEMBERS`, `TABLE_INSTITUTIONS`, `TABLE_TRANSACTIONS`, `TABLE_PENDING_AFFILIATIONS`, and `TABLE_ATTENDANCE`.
  - Validate required environment variables in production.
  - Configure error reporting and session security based on environment.
  - Set timezone to `Asia/Manila`.

#### `public/api.php`
- Purpose: API request proxy and router.
- Role: Accepts API requests and forwards them to endpoint handler files.
- Used By: frontend code, mobile clients, browser fetch/ajax, and any direct API consumer.
- Responsibilities:
  - Load `bootstrap.php` and `includes/config.php`.
  - Enforce JSON response with `Content-Type: application/json`.
  - Sanitize the `endpoint` query parameter and build the handler file path.
  - Include an API handler file if found, capture its output, and ensure JSON output.
  - Log proxy routing activity and errors.
  - Return proper HTTP status codes for missing endpoints (`404`) or server errors (`500`).
- Security: Sanitizes endpoint names and prevents directory traversal via regex.
- Side effects: All endpoint code runs in the same proxy environment.

#### `login.php`
- Purpose: Authenticate users and establish sessions.
- Role: The login page for administrators, officers, and members.
- Used By: all system users when logging in.
- Responsibilities:
  - Prevent browser caching of the login page.
  - Manage logout requests and destruction of session cookies.
  - Redirect already-authenticated users to the correct portal dashboard.
  - Accept POSTed email and password.
  - Authenticate users through a legacy `users` table first, then Supabase Auth as fallback.
  - Load the user profile from `user_profiles` and set session state.
  - Audit login success and failures via `log_audit()`.
  - Redirect users by role:
    - `admin` → `/public/portal/admin/dashboard.php`
    - `school_officer` → `/public/portal/school-officer/dashboard.php`
    - `member` → `/public/portal/member/dashboard.php`
  - Support development test accounts when production auth is unavailable.
- Authentication Notes:
  - Uses `password_verify()` for legacy hashed passwords.
  - Falls back to `SupabaseClient::authSignIn()`.
  - Tracks `must_change_password` and forces password reset when required.

#### `logout.php`
- Purpose: End user sessions cleanly.
- Role: Logout endpoint that clears session and redirects to `index.php`.
- Used By: any page or link that logs out a user.
- Responsibilities:
  - Clear `$_SESSION` and destroy the PHP session.
  - Delete the session cookie.
  - Redirect to the public landing page.

#### `includes/paths.php`
- Purpose: Define role-specific paths and portal URL helpers.
- Role: Normalizes application paths across pages.
- Used By: pages and modules that need portal URL or directory path resolution.
- Responsibilities:
  - Define `BASE_PATH`, `SRC_PATH`, `PUBLIC_PATH`, `CONFIG_PATH`, `LIB_PATH`, `API_PATH`, `PORTAL_PATH`, `CSS_PATH`, and `JS_PATH`.
  - Provide `get_portal_url($role, $page)` and `get_role_path($role)`.

#### `includes/role-config.php`
- Purpose: Define navigation menus for each application role.
- Role: Stores `$ROLE_NAVIGATION` configuration for all supported roles.
- Used By: `includes/sidebar.php` and portal pages that build the sidebar.
- Responsibilities:
  - Define navigation items for `admin`, `school_officer`, and `member`.
  - Provide role titles, display labels, and menu URLs.
  - Ensure the application has exactly three active roles and no legacy role definitions.

#### `includes/sidebar.php`
- Purpose: Render the stable portal sidebar for authenticated users.
- Role: Builds a consistent sidebar HTML structure for portal pages.
- Used By: all portal pages that include the shared sidebar component.
- Responsibilities:
  - Load `$ROLE_NAVIGATION` from `includes/role-config.php`.
  - Detect the current user role and fallback to `school_officer` when needed.
  - Determine `$current_page` using `basename(__FILE__, '.php')` when set by each portal page.
  - Compare `$current_page` against each menu item URL and apply the `active` CSS class.
  - Keep the sidebar HTML markup identical across pages; only the `active` state changes.

#### `includes/audit.php`
- Purpose: Centralized audit logging helper.
- Role: Records important system events to the `audit_logs` table.
- Used By: authentication pages, membership actions, affiliation workflows, and any module that calls `log_audit()`.
- Responsibilities:
  - Insert audit records into Supabase `audit_logs`.
  - Store action type, table name, record ID, old/new payloads, performer ID, IP address, and user agent.
  - Catch and log exceptions internally to avoid breaking user flow.

#### `includes/supabase.php`
- Purpose: Return Supabase connection settings.
- Role: Centralized source for Supabase URL, anon key, and service role key.
- Used By: `bootstrap.php`, `login.php`, `src/` libraries, and API endpoint handlers.
- Security: Loads the service role key from environment variables; no secrets are hard-coded in runtime code.

### Core Library Files in `src/lib`

#### `src/lib/SupabaseClient.php`
- Purpose: Generic Supabase REST client.
- Role: Performs CRUD operations against Supabase tables and Auth endpoints.
- Used By: business logic classes, API handlers, portal pages, cron jobs.
- Responsibilities:
  - Provide `select()`, `insert()`, `update()`, `delete()`, `upsert()` operations.
  - Use either `curl` or `file_get_contents()` depending on availability.
  - Build Supabase REST endpoints using `rest/v1/<table>`.
  - Send headers with `apikey`, `Authorization`, `Content-Type`, and `Prefer: return=representation`.
  - Log request endpoints, payloads, and responses for troubleshooting.
  - Support service role key usage through `setServiceRoleKey()`.
- Security: Throws exceptions for HTTP 400+ responses and JSON decode failures.

#### `src/lib/BlockchainService.php`
- Purpose: Implement a hash-chained audit trail.
- Role: Provides blockchain-style record integrity for audit, compliance, transaction, and certificate data.
- Used By: compliance engine, blockchain API endpoints, certificate issuance flows, document verification.
- Responsibilities:
  - Validate allowed record types.
  - Generate record hashes from `entity_type`, `entity_id`, sorted payload, and prior hash.
  - Insert blockchain records into `blockchain_records`.
  - Verify entire chains or individual records.
  - Generate Merkle roots from hash arrays.
- Security & Integrity:
  - Detects tampering by comparing recomputed hashes with stored values.
  - Uses SHA256 and `hash_equals()` for timing-safe comparisons.

#### `src/lib/ComplianceEngine.php`
- Purpose: Calculate institutional compliance score.
- Role: Encapsulates rules for participation and event hosting.
- Used By: the `cron/compliance_update.php` job and compliance reporting flows.
- Responsibilities:
  - Count active members and distinct attendance records.
  - Compute participation rate and hosted event count.
  - Derive compliance status:
    - `compliant` (participation ≥ 40% and hosted events ≥ 1)
    - `at_risk` (one condition fails)
    - `non_compliant` (both conditions fail)
  - Store compliance scores in `compliance_scores` via upsert.
  - Record compliance calculations in blockchain audit trail.
  - Send alert notifications to school officers when an institution is at risk.
- Note: The class is implemented in source and currently invoked by the compliance cron job.

---

## Public Portal and UI Files

### `public/portal/admin/dashboard.php`
- Purpose: Dashboard page for administrators.
- Role: Entrypoint for admin system overview.
- Used By: authenticated `admin` users.
- Responsibilities:
  - Verify session role authorization.
  - Query pending affiliations, member counts, institution counts via `SupabaseClient`.
  - Render an admin UI with statistics and action cards.
  - Include shared layout from `includes/sidebar.php`.
- Frontend behavior:
  - Uses inline CSS for dashboard-specific styling.
  - Uses Font Awesome icons and layout classes.
  - Displays alerts and summary cards.
- Security: Redirects unauthorized users to `/login.php`.

### `public/api/` Endpoints

The API folder contains HTTP handlers for the application's business operations. Key categories include:

- `affiliate` / affiliation management
- `blockchain` / record verification and explorer
- `compliance` / compliance scoring and reports
- `admin` / administrator management, reports, backup/restore, and system logs
- `treasurer` / financial reports and verification
- `school-officer` / school-specific operations
- `super-admin` / health checks, impersonation, and cron job management
- general endpoints for `events`, `membership`, `notifications`, `documents`, and user workflows

Each endpoint:
- expects `?endpoint=` or direct file invocation through the API proxy
- loads the shared config/bootstrap environment
- interacts with Supabase tables or other service classes
- returns JSON responses
- logs errors or invalid access.

#### `public/api/submit-affiliation.php`
- Purpose: Submit new school affiliation requests.
- Role: allows institutions to apply for IECEP-LSC affiliation.
- Used By: public affiliation forms and school officers.
- Responsibilities:
  - Validate submitted affiliation data.
  - Insert a row into `pending_affiliations`.
  - Upload or link supporting documents.
  - Return structured success or error JSON.

---

## Database and Schema Documentation

### `database/` Folder
- `additional_tables.sql` — supplemental table definitions for data modules not included in the main schema.
- `add_event_id_to_transactions.sql` — migration that adds an event foreign key relationship to transaction records.
- `enhancements_sql.sql` — schema improvements and feature-specific SQL statements.
- `fix_blockchain_schema.sql` — blockchain record schema adjustments.
- `supabase_complete_query.sql` — complete Supabase schema export.
- `xampp_localhost_complete_query.sql` — local MySQL schema export for XAMPP environments.
- `database/migrations/` — incremental SQL migrations for features such as member ID counters, pending affiliations, event compliance, auto-generated accounts, and blockchain enhancements.

### Data Model and Tables

The system core data tables include:
- `user_profiles` — user metadata, roles, institution assignment, profile status, and access control.
- `users` — authentication accounts for Supabase and legacy login handling.
- `members` — membership records tied to schools, statuses, and digital IDs.
- `institutions` — affiliated schools and chapter details.
- `transactions` — payment and fee records.
- `attendance` — event attendance logging.
- `pending_affiliations` — affiliation application workflow.
- `audit_logs` — system activity audit trail.
- `blockchain_records` — hash-chained integrity records.
- `compliance_scores` — historical institutional compliance calculations.
- `email_verifications` — email OTP or verification records.
- `notifications` — user notification messages.
- `events` — event definitions, dates, and statuses.
- `documents` / repository tables — stored documents, verification state, and metadata.

### Database Relationships

- `user_profiles.institution_id` → `institutions.id`
- `members.institution_id` → `institutions.id`
- `transactions.institution_id` → `institutions.id`
- `attendance.event_id` → `events.id`
- `attendance.user_id` → `user_profiles.id`
- `compliance_scores.institution_id` → `institutions.id`
- `blockchain_records.entity_id` → matched business records by type
- `pending_affiliations.institution_id` → `institutions.id` when approved

### Normalization and Indexing

- Most tables are normalized by business entity: users, members, institutions, events, and transactions.
- Lookup and join columns use direct foreign keys where supported by Supabase.
- The blockchain audit design stores hashed payload JSON and chain metadata to preserve immutability.

### ER Diagram Explanation

The ER model is based on the following relationships:
- One-to-many: `institutions` to `members`, `institutions` to `events`, `institutions` to `transactions`, `institutions` to `pending_affiliations`.
- One-to-many: `user_profiles` to `notifications`, `user_profiles` to `attendance` records.
- Many-to-many is represented by association tables such as `attendance` linking `users` and `events`.
- Blockchain records are a historical audit chain rather than a direct relational join.

---

## Authentication and Authorization

### Login Flow

1. User opens `login.php`.
2. The system checks for an existing session.
3. If login form is submitted, email and password are validated.
4. The system attempts legacy authentication with the `users` table.
5. If legacy auth fails, it attempts Supabase Auth sign-in.
6. If authentication succeeds, the system loads `user_profiles`.
7. Session variables are set: `user_id`, `email`, `full_name`, `role`, `logged_in`, and `user` object.
8. Successful login events are audited.
9. Users are redirected to role-specific dashboards.

### Logout Flow

1. The user clicks logout.
2. `login.php` or `logout.php` clears session state.
3. The session cookie is invalidated.
4. The browser is redirected to `index.php`.

### Session Security

- Sessions are started in `bootstrap.php` and `login.php`.
- No local MySQL database file is required for application data; the system uses Supabase as the exclusive data backend.
- `config.php` enforces `session.cookie_httponly`, `session.use_strict_mode`, and `SameSite=Strict`.
- `APP_ENV` distinguishes between development and production error handling.

### Roles and Permissions

- `admin` — full system management, administrator portal access, system settings, audit logs, recovery.
- `school_officer` — institution-specific member and compliance management.
- `member` — personal dashboard, event registration, and profile access.
- Role navigation is defined in `includes/role-config.php` and rendered by `includes/sidebar.php`.
- Access validation happens in portal pages and middleware such as `includes/middleware/auth.php`.

### Security Implementation

- Passwords in legacy storage use `password_verify()`.
- Supabase Auth is the primary authentication layer.
- API endpoints are routed through a sanitized proxy.
- Audit logging records login failures, password changes, role changes, and critical data operations.
- Uploads are limited by `MAX_FILE_SIZE` and allowed MIME types.
- Environment variables and secrets are never hard-coded in production code.

---

## System Flow

### Visitor to Dashboard Workflow

1. Visitor opens the public landing page or `index.php`.
2. Visitor logs in via `login.php`.
3. The system authenticates and sets session state.
4. The user is redirected to the portal dashboard appropriate for their role.
5. Dashboard actions call APIs, load portal pages, or submit forms.
6. Business logic uses `SupabaseClient`, `ComplianceEngine`, `BlockchainService`, or other shared libraries.
7. Data is stored in Supabase tables and audit logs.
8. Responses are rendered to the user or returned as JSON.

### Affiliation Workflow

1. Institution completes an affiliation request via public or private form.
2. `public/api/submit-affiliation.php` records the request in `pending_affiliations`.
3. Administrators review requests in `public/portal/admin/*`.
4. Approvals update institution and membership metadata.
5. Blockchain audit records are written if enabled.

### Event and Attendance Workflow

1. Events are created and managed from `public/portal/*/events`.
2. Members register for events and receive digital IDs.
3. Attendance is recorded by `event-qr-checkin.php` or attendance API handlers.
4. Compliance is evaluated based on event participation and hosted events.

### Financial and Payment Workflow

1. Fees are calculated using `public/api/calculate-fees.php` and internal fee calculators.
2. Note: A simulation helper exists for testing purposes (`public/api/simulate-payment.php`), but live payments are recorded manually by the treasurer.
3. Transactions are stored in `transactions`.
4. Receipts are generated through `public/api/generate-receipt.php`.

---

## Feature Documentation

The system supports these major feature areas:

- Membership Management: member directory, membership IDs, renewals, and status tracking.
- Affiliation Management: school applications, document submission, approval workflows.
- Compliance and Reporting: attendance tracking, participation scoring, compliance heatmaps.
- Event Management: event registration, QR check-in, attendance records.
- Financial Reporting: fee calculation, transaction logs, treasurer reports.
- Blockchain Verification: hash-chain audit, certificate verification, chain integrity checks.
- Notifications: announcements, email notifications, push notifications, reminders.
- Document Verification: upload repository, document review, certificate verification.
- Public Transparency: public-facing verification pages, audit explorer, blockchain hash proof.

Each feature is implemented across these folders:
- `public/api/` for backend endpoints
- `public/portal/` for user-facing portal pages
- `src/lib/` for reusable service logic and integrations
- `includes/` for shared helpers and UI components
- `database/` for schema definitions and migrations

---

## API Documentation

### Endpoint Structure

The API proxy accepts requests through:
- `public/api.php?endpoint=<endpoint_name>`

Each endpoint file handles business logic and returns JSON.

### Example API Endpoints

- `affiliation_status` — returns affiliation status for schools.
- `blockchain` — blockchain-related verification and explorer data from `public/api/blockchain.php`.
- `compliance-report` — retrieves compliance summaries.
- `generate-receipt` — creates payment receipts.
- `verify-payment` — verifies payment records.
- `verify-chain` — verifies blockchain integrity.
- `event-registration` — registers members for events.
- `upload-profile-picture` — uploads user profile images.
- `super-admin/system-health` — health and system diagnostics.

### Error Handling

- API proxy returns `400` when `endpoint` is missing.
- Proxy returns `404` when endpoint file is not found.
- Proxy returns `500` for uncaught exceptions or non-JSON handler output.
- Individual endpoint files are expected to return structured JSON responses.

---

## Security Documentation

### SQL Injection Prevention

- The project primarily uses Supabase REST API instead of raw SQL queries.
- Query filters are constructed safely through `SupabaseClient` helper methods.

### Authentication and Authorization

- Uses Supabase Auth for production authentication.
- Supports legacy password hashes.
- Role-based redirects and UI access controls.
- `includes/middleware/auth.php` protects portal routes.

### Session Security

- `session.cookie_httponly` and `session.use_strict_mode` are enabled.
- `SameSite=Strict` prevents CSRF in cookie-based sessions.
- Sessions are destroyed fully on logout.
- The sidebar active-link state is maintained via `includes/sidebar.php` and `includes/role-config.php`.

### Input Validation and Output Escaping

- Login input is trimmed and validated.
- API endpoint names are sanitized.
- Portal pages escape HTML output with `htmlspecialchars()` when rendering user data.

### CSRF and XSS

- The project uses a custom CSRF helper (`includes/csrf.php`) for form protection.
- UI pages use safe output escaping and content security via sanitized HTML.

### File Upload Security

- `ALLOWED_FILE_TYPES` and `MAX_FILE_SIZE` are enforced through config.
- Document uploads are limited by file extension and size.
- Uploaded files are stored in `public/uploads/` and `public/assets/uploads/`.

---

## Responsive Design

### Breakpoints and Behavior

- Desktop: full dashboard layout with grid cards and sidebar navigation.
- Tablet: responsive cards stack vertically with reduced margins.
- Mobile: single-column UI, collapsible navigation, touch-friendly buttons.
- Small mobile: simplified forms and compact dashboard cards.

### CSS and Layout

- UI uses CSS grid in dashboard cards.
- Many portal pages rely on shared global styles in `public/assets/css/styles.css`.
- Inline CSS in portal dashboards scopes specific component styling.
- Font families include `Inter` and `Times New Roman` for professional typography.

---

## Developer Notes

### Coding Standards

- Structuring code by feature: public pages, includes, src libraries, and API endpoints.
- Shared configuration in `bootstrap.php` and `includes/config.php`.
- Reusable helpers in `includes/` and `src/lib/`.
- Consistent session handling and environment-aware error reporting.

### Naming Conventions

- Files use kebab-case for public pages and API endpoints.
- PHP classes use PascalCase in `src/lib/` with namespaces.
- Configuration constants use snake_case UPPERCASE.
- Role identifiers include `admin`, `school_officer`, and `member`.

### Maintenance Guide

- Keep `.env` secrets out of source control.
- Use `composer install` for dependency management.
- Update Supabase schema through SQL migration files in `database/migrations/`.
- Review `docs/full_file_index.txt` for complete file coverage.
- Confirm portal pages require session guard and proper role checks.
- Sync any database schema changes with Supabase and local SQL files.

### Future Expansion

- Add native REST controllers and structured routing.
- Replace API proxy with a proper router and middleware stack.
- Implement more robust validation and centralized request handling.
- Introduce unit tests for core libraries and API endpoints.
- Add localized language support and mobile-first UI improvements.
- Expand blockchain records to include Merkle proofs for external verification.

---

## Appendix A: Full File Inventory

The `docs/full_file_index.txt` file included in the repository contains every project file path discovered at the time of documentation generation.

This document covers the complete architecture, the entire folder structure, and all major components. For exact file-by-file index reference, consult `docs/full_file_index.txt`.
