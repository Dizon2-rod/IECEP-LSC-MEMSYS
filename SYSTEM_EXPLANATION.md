# IECEP-LSC MEMSYS - Detailed System Explanation

## 1. System Purpose

IECEP-LSC MEMSYS is a PHP-based membership management platform for the IECEP Laguna Student Chapter. It supports:
- member onboarding, renewal, and approval workflows
- school affiliation requests and status tracking
- role-based portal dashboards for officers, members, registration, admissions, and administrators
- secure authentication, session handling, and authorization checks
- Supabase-backed data persistence and authentication integration
- SMTP email delivery for invitations, password resets, and notifications
- in-app and push notification management
- financial reporting, payment monitoring, and treasury analytics
- member verification, digital ID issuance, and blockchain audit logging

## 2. Workspace Structure

### Root-level files
- `index.php` - public home page, primary landing page, and front-end forms
- `login.php` - login page and credentials submission
- `logout.php` - session termination and redirect
- `change-password.php`, `change-password-old.php` - password updates
- `forgot-password.php`, `reset-password.php`, `get-reset-token.php` - password recovery flows
- `verify-member.php` - public member verification interface
- `diagnostic.php`, `debug-supabase.php`, `check-affiliations.php`, `check-users.php` - diagnostic and debug utilities

### Main folders
- `includes/` - shared configuration, utilities, templates, path helpers, and middleware
- `public/` - public web pages, API endpoints, portal pages, service worker and static assets
- `src/` - application libraries, service wrappers, and models
- `database/` - schema definitions, migration scripts, and database utilities
- `assets/`, `public/assets/`, `public/css/`, `public/js/` - static assets, CSS, and JavaScript files
- `vendor/` - Composer dependencies and installed PHP packages

## 3. Core Configuration

### `includes/config.php`
- Loads Composer autoload and project autoload files
- Reads `.env` file into `$_ENV` and `$_SERVER` using a custom `loadEnv()` helper
- Defines environment constants:
  - `APP_NAME`, `APP_URL`, `APP_ENV`
  - `SUPABASE_URL`, `SUPABASE_ANON_KEY`, `SUPABASE_SERVICE_ROLE_KEY`
  - `SMTP_HOST`, `SMTP_PORT`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_FROM_NAME`, `SMTP_FROM_EMAIL`
  - `JWT_SECRET`, `SESSION_LIFETIME`, `CRON_SECRET`
  - `MAX_FILE_SIZE`, `ALLOWED_FILE_TYPES`
  - Supabase table constants: `TABLE_USERS`, `TABLE_MEMBERS`, `TABLE_INSTITUTIONS`, `TABLE_TRANSACTIONS`, `TABLE_PENDING_MEMBERS`, `TABLE_PENDING_AFFILIATIONS`, `TABLE_ATTENDANCE`
- Configures error reporting based on `APP_ENV`
- Sets secure session cookie settings and session strict mode
- Sets PHP default timezone to `Asia/Manila`
- Exposes `outputFrontendConfig()` for embedding frontend config into pages
- Initializes the global `$supabaseClient` and optionally `BlockchainService`

### `includes/paths.php`
- Defines filesystem path constants:
  - `BASE_PATH`, `PUBLIC_PATH`, `SRC_PATH`, `CONFIG_PATH`, `LIB_PATH`, `API_PATH`, `INCLUDES_PATH`, `PORTAL_PATH`, `ASSETS_PATH`, `CSS_PATH`, `JS_PATH`
- Defines URL constants:
  - `BASE_URL`, `PUBLIC_URL`, `PORTAL_URL`, `ASSETS_URL`, `CSS_URL`, `JS_URL`, `API_URL`
- Provides helper functions:
  - `get_path($relativePath)` for filesystem resolution
  - `get_url($relativePath)` for public URLs
  - `get_portal_url($role, $page = 'dashboard.php')` for portal navigation
  - `get_role_path($role)` to map roles to portal folder names
- Notes that `API_URL` points to `BASE_URL . '/src/api'` but most live APIs are under `public/api/`

### `includes/role-config.php`
- Defines role labels, display names, and navigation menu structure
- Maps permissions and dashboard sections per role
- Used by portal pages and sidebar templates for rendering appropriate links

## 4. Authentication and Authorization

### Login workflow (`login.php`)
- Collects user credentials from the login page
- Validates against Supabase auth and user profile data
- Loads `user_profiles` and role metadata
- Stores authenticated state in `$_SESSION['user']` and `$_SESSION['role']`
- Redirects to a role-based dashboard using mapping logic

### Access control (`public/portal/auth_check.php`)
- Started by most portal pages to enforce authentication
- Loads shared path configuration from `includes/paths.php`
- Provides helper functions:
  - `require_role($allowed_roles, $redirect = true)` - verifies authorization and redirects or returns 403
  - `get_role_dashboard($role)` - constructs the user-specific dashboard URL
  - `get_user_info()` - returns the current session user array
  - `is_logged_in()` - checks if a session exists
  - `get_role_display_name($role)` - converts role keys to human labels
- Users without permission are redirected to login or shown a 403 page

### Session data sources
- `$_SESSION['user']` contains user profile data
- `$_SESSION['role']` contains the current role
- `$_SESSION['user_name']`, `$_SESSION['user_email']` are fallback values
- `user['user_metadata']` and `user['app_metadata']` are alternate role sources

## 5. Role and Portal Mapping

### Supported roles
- admin
- super_admin
- registration
- committee_registration
- treasurer
- eb_treasurer
- school_officer
- member
- auditor
- eb_auditor
- secretary
- eb_secretary_general
- creatives
- committee_creatives
- marketing
- logistics
- technical
- documentation
- vp_internal
- vp_external
- vp_academic
- pro
- president
- officer
- committee

### Portal directories
- `public/portal/admin/` - admin dashboard, members, affiliations, events, payments
- `public/portal/super-admin/` - super admin dashboard, user management, role management
- `public/portal/registration/` - registration dashboard, pending affiliations, member batch processing
- `public/portal/treasurer/` - treasurer dashboard, payments, budget, reports
- `public/portal/school-officer/` - school officer dashboard, members, compliance, reports
- `public/portal/member/` - member dashboard, profile, affiliation details
- `public/portal/auditor/` - auditor dashboard, compliance, audit logs, reports
- `public/portal/secretary/` - secretary dashboard, members, events, minutes, documents
- `public/portal/creatives/` - creatives dashboard, announcements, graphics, publications, team
- `public/portal/vp-internal/` - VP Internal dashboard and chapter development
- `public/portal/vp-external/` - VP External dashboard and external relations
- `public/portal/vp-academic/` - VP Academic dashboard and academic affairs
- `public/portal/pro/` - PRO dashboard and media
- `public/portal/president/` - president dashboard, member overview, reports
- `public/portal/officer/` - officer dashboard, members, events
- `public/portal/committee/` - committee dashboard, documentation, logistics, marketing, technical

### Role-to-folder mapping
- `eb_president` ? `super-admin`
- `eb_vp_internal` ? `registration`
- `eb_treasurer` ? `treasurer`
- `eb_auditor` ? `auditor`
- `eb_secretary_general` ? `secretary`
- `committee_creatives` ? `creatives`
- `committee_logistics`, `committee_marketing`, `committee_technical`, `committee_documentation` ? `committee`
- legacy roles such as `logistics`, `marketing`, `technical`, `documentation` map to `committee`

## 6. Public and Portal Pages

### Public pages
- `index.php` - public landing page and primary form pages
- `apply.php` - membership and affiliation application UI
- `affiliated-schools.php` - affiliated school list page
- `board-of-trustees.php`, `mission-vision.php`, `iecep-hymn.php` - informational pages
- `contact.php` / `contact-submit.php` - contact form and submit handler
- `verify-member.php` - public UI for member verification
- `forgot-password.php` / `reset-password.php` - password recovery pages

### Portal pages and structure
- `public/portal/*` pages are protected by `auth_check.php`
- Each portal page includes sidebar and header templates from `includes/`
- Example portal pages:
  - `public/portal/admin/dashboard.php`
  - `public/portal/registration/members.php`
  - `public/portal/treasurer/reports.php`
  - `public/portal/member/profile.php`
  - `public/portal/school-officer/members.php`
  - `public/portal/super-admin/users.php`

## 7. API Routing and Endpoints

### Generic proxy: `public/api.php`
- Uses query parameter `endpoint`
- Includes `public/api/<endpoint>.php`
- Captures output buffer and returns valid JSON or standardized error responses
- Example request: `public/api.php?endpoint=notifications`

### Primary API endpoints
- `public/api/process-member-batch.php` - batch member approval and account creation
- `public/api/financial-report.php` - treasurer financial analytics
- `public/api/notifications.php` - notification list, read state, stats, VAPID key retrieval
- `public/api/send-notification.php` - push notification broadcast
- `public/api/save-subscription.php` - push subscription registration
- `public/api/verify-member.php` - public member verification endpoint
- `public/api/reset-password.php` - reset password submission endpoint
- `public/api/request-password-reset.php` - request password reset token
- `public/api/submit-affiliation.php` - affiliation application submission
- `public/api/send-reminder.php` - reminder broadcast to users or institutions

### Notifications API actions
- `action=list` - returns notifications with `user_id.eq.<currentUserId>` or `user_id.is.null`
- `action=mark_read` - marks one notification as read
- `action=mark_all_read` - marks all unread notifications as read
- `action=stats` - returns `total` and `unread` notification counts
- `action=vapid_key` - returns the public VAPID key for push subscription

### Common route patterns
- Member verification page: `/IECEP-LSC-MEMSYS/public/verify-member.php`
- Notifications API: `/IECEP-LSC-MEMSYS/public/api/notifications.php?action=list`
- Batch processing API: `/IECEP-LSC-MEMSYS/public/api/process-member-batch.php`
- Financial report API: `/IECEP-LSC-MEMSYS/public/api/financial-report.php`
- Push send API: `/IECEP-LSC-MEMSYS/public/api/send-notification.php`

## 8. Core Feature Workflows

### Member batch processing
- User-facing page: `public/portal/registration/members.php`
- Permission check: `require_role(['registration','committee_registration','admin','super_admin'])`
- Form submits a JSON POST to `public/api/process-member-batch.php`
- API validates `batch_id` and loads a pending batch with nested `pending_members`
- The system processes each pending row:
  - required fields: `id`, `email`, `full_name`
  - skip rows missing required data or already processed rows
  - find existing `members` by email
  - if existing member found, update record and optionally renew membership
  - if new member, generate a membership ID and insert a new record
  - send email notifications via `EmailService`
  - optionally generate digital ID and QR code
  - log errors in the summary response

### Membership ID generation
- retrieves `member_id_prefix` from `system_settings`
- uses the current year from `date('Y')`
- reads or initializes a row in `member_id_counter`
- increments `last_number` and formats the ID as `PREFIX-YEAR-XXXX`

### Notification system
- `public/api/notifications.php` returns both user-specific and global notifications
- `public/api/save-subscription.php` stores push endpoint data in Supabase
- `public/api/send-notification.php` can broadcast messages and push notifications
- `public/sw.js` handles service worker push events and offline caching
- Sidebar UI reads notification counts and updates the dropdown menu

### Financial reporting
- `public/portal/treasurer/reports.php` renders the treasurer dashboard
- client JavaScript fetches data from `public/api/financial-report.php`
- the API compiles monthly totals, payment status breakdowns, and transaction history
- chart data is rendered using front-end JS in `public/assets/js/charts.js`

### Verification and digital ID
- `public/verify-member.php` provides a polished verification UI
- the page calls `public/api/verify-member.php?id=<memberId>`
- response includes member metadata, payment status, digital ID URL, and hash
- digital ID validation can be backed by blockchain audit records via `BlockchainService`

### Affiliation and school data
- public affiliation forms submit to `public/api/submit-affiliation.php`
- approved affiliations are reviewed in portal admin pages
- `affiliated-schools.php` and data files present approved school lists

## 9. Libraries and Service Wrappers

### Supabase libraries
- `src/lib/SupabaseClient.php` - legacy client used by older scripts and debugging pages
- `src/lib/Supabase.php` - modern REST wrapper for Supabase operations
- `src/lib/SupabaseQuery.php` - query builder for filtering, sorting, and pagination
- `src/lib/SupabaseAuth.php` - higher-level authentication methods

### Email and blockchain
- `src/lib/EmailService.php` - SMTP email sending, template handling, and notification emails
- `src/lib/BlockchainService.php` - record audits and digital ID hashes in blockchain-aware storage

### Other helpers
- `autoload.php` - loads Composer and fallback project files
- `includes/lib/EmailService.php` - shared email helpers outside `src/lib`
- `public/assets/js/notifications.js` - notification pull and sidebar UI
- `public/sw.js` - service worker registration and push handling

## 10. Data Model and Tables

### Primary tables
- `user_profiles` - user accounts, roles, contact data, institution link, metadata
- `members` - registered members, membership IDs, member type, year level, payment status
- `transactions` - financial records, amounts, payment status, created timestamps
- `notifications` - stored notifications with `user_id`, message payload, read state
- `pending_members` - uploaded member rows awaiting approval
- `member_upload_batches` - batch upload metadata and status
- `member_id_counter` - year-based increment counters for member IDs
- `system_settings` - application settings including `member_id_prefix`
- `institutions` - affiliated schools and institutions

### Supporting tables
- `push_subscriptions` - browser and device push endpoints and keys
- `email_verifications` - reset tokens and verification metadata
- `pending_affiliations` - affiliation application staging records
- `attendance`, `reports`, `logs` - event tracking and audit history

### Field patterns
- `id`, `email`, `full_name`, `role`, `institution_id`, `membership_id`
- `payment_status`, `created_at`, `updated_at`, `read`
- notifications support `user_id` or `NULL` for broadcast messages
- batch processing uses `pending_members` nested within `member_upload_batches`

## 11. Deployment Notes

- `.env` must contain production Supabase keys and SMTP credentials
- `APP_URL`, `BASE_URL`, `PUBLIC_URL`, and `PORTAL_URL` should match the hosted path
- `SESSION_LIFETIME` and `JWT_SECRET` control session expiration and security
- `SUPABASE_SERVICE_ROLE_KEY` is required for privileged operations like user creation
- `SMTP_*` credentials are required for email delivery
- `outputFrontendConfig()` can expose frontend values for JavaScript
- ensure `BASE_URL` is configured correctly for `/IECEP-LSC-MEMSYS` deployments

## 12. Extension and Maintenance

### Add a portal page
1. Add the page under `public/portal/<role>/`
2. Require `auth_check.php` to enforce login and role checks
3. Register the page in `includes/sidebar.php` for the role menu
4. Optionally update `includes/role-config.php` for label and permission metadata

### Add a new API endpoint
1. Create `public/api/<endpoint>.php`
2. Optionally proxy it through `public/api.php`
3. Add authorization checks as needed
4. Use `src/lib/Supabase.php` and service wrappers for data access

### Add or extend roles
1. Update `get_role_path()` in `includes/paths.php`
2. Add role labels in `includes/auth_check.php` and `includes/role-config.php`
3. Add menu entries in `includes/sidebar.php`
4. Add portal routes and enforce `require_role()` on the pages

## 13. Summary

IECEP-LSC MEMSYS is a complete PHP/Supabase membership management system with role-based portals, secure auth, batch processing, notifications, financial reporting, and member verification. This file documents the exact route layout, feature behavior, file responsibilities, and extension points for the current codebase.
