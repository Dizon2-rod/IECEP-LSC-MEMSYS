# IECEP-LSC MEMSYS - Complete System Explanation

## 1. Overview

IECEP-LSC MEMSYS is a PHP-based membership management system for the IECEP Laguna Student Chapter. The platform supports:
- member registration, approval, and renewal
- school affiliation processing
- role-based portal dashboards for members, officers, registration, admin, and super-admin
- secure authentication and authorization
- Supabase data persistence and authentication integration
- email and notification delivery
- blockchain-backed digital ID verification and audit logging
- financial reporting for the treasurer

## 2. Architecture

### Backend
- Core application logic is in project root PHP files and `public/`
- Portal interfaces live under `public/portal/`
- API endpoints live under `public/api/`
- Shared bootstrap and utilities are in `includes/`
- Supabase SDK and service wrappers are in `src/lib/`
- Database schema and migrations are in `database/`

### Frontend
- Static web assets are under `public/assets/`, `public/css/`, and `public/js/`
- Chart rendering is handled in `public/assets/js/charts.js`
- Notification UI and service worker support are in `public/assets/js/` and `public/sw.js`
- Global header and sidebar templates are loaded from `includes/`

### Database
- The system relies on a Supabase PostgreSQL backend
- Schema includes tables for members, user profiles, batches, notifications, transactions, settings, and counters
- `database/schema.sql` defines the data model

## 3. Core Configuration

### `includes/config.php`
- Loads environment and application constants
- Defines URLs, Supabase credentials, SMTP settings, JWT secrets, and timezone
- Initializes session settings, error reporting, and global client services
- Exposes frontend configuration via `outputFrontendConfig()`

### `includes/paths.php`
- Defines project path and URL constants
- Provides helpers for computing asset and portal URLs
- Maps roles to portal folder names and routes

### `includes/role-config.php`
- Defines role permissions and sidebar navigation entries
- Maps each user role to UI labels and portal sections
- Used across portal pages for navigation and access control

## 4. Authentication & Authorization

### `login.php`
- Authenticates users against Supabase auth data
- Verifies credentials using secure password hashing
- Loads `user_profiles` to resolve role, institution, and profile details
- Establishes session state for `user_id`, `role`, `email`, and `user`
- Redirects users to role-specific dashboards

### `public/portal/auth_check.php`
- Enforces login and role restrictions on portal pages
- Provides centralized access control helper functions
- Ensures only authorized roles can open a portal page

## 5. Portal Structure

### Role-based portal dirs
- `public/portal/registration/` — registration committee views
- `public/portal/admin/` — administrative dashboards and tools
- `public/portal/school-officer/` — school officer workflows
- `public/portal/member/` — member-facing pages
- `public/portal/super-admin/` — executive and full-access admin pages

### Common portal features
- Dashboard summaries
- Data tables and approval workflows
- Notification and alert support
- Role-specific page rendering based on `role-config.php`

## 6. API Layer

### `public/api.php`
- Serves as a generic router for API actions
- Routes requests to `/public/api/<endpoint>.php`
- Provides JSON output and error handling for missing endpoints

### Direct API endpoints
- Many pages use direct endpoint calls for data actions
- Examples include `public/api/process-member-batch.php`, `public/api/financial-report.php`, `public/api/notifications.php`, and `public/api/verify-member.php`

## 7. Supabase Integration

### `src/lib/Supabase.php`
- Core Supabase wrapper using Guzzle or fallback client
- Supports REST requests with service-role and anon authentication
- Exposes query builder via `SupabaseQuery`

### `src/lib/SupabaseQuery`
- Builder methods include:
  - `select()`, `eq()`, `neq()`, `gt()`, `gte()`, `lt()`, `in()`, `like()`, `is()`
  - `or()` for complex boolean filters
  - `order()`, `limit()`, `offset()`
  - `get()`, `insert()`, `update()`, `delete()`

### `src/lib/SupabaseAuth`
- Provides higher-level auth methods:
  - `signUp()`, `signIn()`, `getUser()`, `updateUser()`
  - admin actions like `adminCreateUser()` and `adminDeleteUser()`

### `src/lib/SupabaseClient.php`
- Legacy client with `curl` wrappers
- Used by older auth and record mutation flows
- Supports `select`, `insert`, `update`, `delete`, `upsert`, and auth helpers

## 8. Batch Membership Approval

### `public/portal/registration/members.php`
- Accepts CSV batch IDs for member upload processing
- Submits requests to `public/api/process-member-batch.php`
- Displays approval summary and notifications

### `public/api/process-member-batch.php`
- Requires authenticated registrar/admin/super-admin access
- Reads `member_upload_batches` and nested `pending_members`
- Validates and processes each pending row
- Creates or updates Supabase auth users and member records
- Generates or reuses membership IDs
- Sends email notifications through `EmailService`
- Optionally writes blockchain audit records
- Tracks counts for new members, renewals, missing data, and duplicates

### Membership ID generation
- Uses `system_settings.member_id_prefix`
- Maintains year-based counters in `member_id_counter`
- Produces deterministic membership IDs with prefix, year, and sequence

## 9. Notification System

### `public/api/notifications.php`
- Lists notifications for the current authenticated user
- Supports global notifications and user-specific notifications
- Allows marking individual notifications as read
- Supports 'mark_all_read' and unread stats
- Exposes `vapid_key` for push notification registration

### `public/api/send-reminder.php`
- Allows authorized staff to send reminder notifications
- Targets specific users, institutions, or all users
- Stores notification records with `sent_by`, `user_id`, `action_url`, `created_at`, and `read` status
- Designed to support both in-app and push workflows

### Notification assets and UI
- Client side notification loading is implemented in `public/assets/js/notifications.js`
- The service worker in `public/sw.js` integrates push event handling and API polling
- Sidebar and header components render notification counts and details

## 10. Financial Reporting

### `public/portal/treasurer/reports.php`
- Treasurer dashboard that renders charts and summary cards
- Uses client-side JavaScript to fetch report data from `public/api/financial-report.php`
- Includes export/report printing support

### `public/api/financial-report.php`
- Guards access to treasurer, admin, and super-admin roles
- Aggregates 12 months of transaction income data
- Computes totals by payment status and transaction type
- Returns structured JSON for chart rendering and summary cards

### `public/assets/js/charts.js`
- Fetches the financial report API and renders:
  - monthly income line chart
  - payment status doughnut chart
  - detailed monthly summary table
  - summary totals for income, completed/pending amounts, and transaction count

## 11. Member Verification & Digital ID

### `public/api/verify-member.php`
- Verifies members by `id` or `digital_id_hash`
- Supports blockchain-backed digital ID hash verification when blockchain service is available
- Returns member metadata including institution name and digital ID URL

### Digital ID workflow
- Member records can store `digital_id_url` and `digital_id_hash`
- Blockchain service validates hash integrity for verified digital IDs

## 12. Key Data Model Elements

### Important tables
- `members` — primary member registry
- `user_profiles` — role, institution, and profile metadata
- `notifications` — in-app notification records
- `transactions` — financial transaction history
- `member_upload_batches` — uploaded batch metadata
- `pending_members` — row-level batch staging and approval data
- `member_id_counter` — sequential membership ID state
- `system_settings` — configurable app settings

### Common fields
- `id`, `email`, `full_name`, `role`, `institution_id`, `membership_id`, `payment_status`, `created_at`, `updated_at`

## 13. Recommended Maintenance Paths

- Keep Supabase credentials and service role keys secure in `.env`
- Ensure `includes/config.php` matches the deployed environment
- Maintain the `member_id_counter` table for deterministic membership IDs
- Update `system_settings` for app-wide configuration values
- Monitor scheduled notification and cron workflows for reminder delivery

## 14. System Behavior Summary

IECEP-LSC MEMSYS is designed as a role-oriented administrative portal with strong Supabase integration, batch member workflows, notification delivery, reporting dashboards, and extensible portal pages. It centralizes membership operations, compliance communication, and financial oversight while enabling secure auth and per-role access control.


- The system currently uses both `Supabase` and `SupabaseClient` libraries. Use `src/lib/Supabase.php` for new endpoints and keep `SupabaseClient.php` for legacy login/code.
- `public/api.php` is a generic proxy, but some newer flows post directly to `public/api/*.php`.
- `member_id_counter` is required for sequential membership IDs and should be populated before batch processing.
- `system_settings.member_id_prefix` controls the membership ID prefix.

---

This file is intended as a complete explanation of the codebase, routes, configuration, and feature connections for IECEP-LSC MEMSYS.
