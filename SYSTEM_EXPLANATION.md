# IECEP-LSC MEMSYS - Complete System Explanation

## 1. System Purpose

IECEP-LSC MEMSYS is a membership and affiliation management system for the IECEP Laguna Student Chapter. It manages:
- member registration and batch approval
- school affiliation applications
- role-based portals and dashboards
- user authentication and role authorization
- email notifications and optional blockchain audit logging
- Supabase-based data storage and authentication

## 2. Architecture Overview

### Backend
- PHP application files at project root and under `public/`
- Role-based portal pages under `public/portal/`
- API endpoints under `public/api/`
- Shared configuration under `includes/`
- Supabase integration under `src/lib/`
- Database schema and migrations under `database/`

### Frontend
- Static assets under `public/assets/`, `public/css/`, `public/js/`
- Global JS helper in `public/js/app.js`
- PWA support via `public/sw.js`

### Database
- Supabase PostgreSQL schema defined in `database/schema.sql`
- Membership and role data stored in tables like `members`, `user_profiles`, `member_upload_batches`, `pending_members`, and `member_id_counter`
- Settings stored in `system_settings`

## 3. Main Configuration Files

### `includes/config.php`
- Loads `.env` values and defines constants
- Sets `APP_URL`, `APP_ENV`, Supabase keys, SMTP settings, JWT secret, session lifetime
- Configures error reporting and timezone (`Asia/Manila`)
- Exposes frontend config through `outputFrontendConfig()`
- Initializes global `SupabaseClient` and `BlockchainService`

### `includes/paths.php`
- Defines filesystem constants: `BASE_PATH`, `PUBLIC_PATH`, `SRC_PATH`, `INCLUDES_PATH`
- Defines URL constants: `BASE_URL`, `PUBLIC_URL`, `PORTAL_URL`, `ASSETS_URL`, `JS_URL`, `CSS_URL`
- Provides helper functions:
  - `get_path($relativePath)`
  - `get_url($relativePath)`
  - `get_portal_url($role, $page)`
  - `get_role_path($role)`

### `includes/role-config.php`
- Defines role-based navigation for portal dashboards
- Maps each role to UI menu items and display labels
- Provides helper functions to query role configs

## 4. Authentication & Access Control

### `login.php`
- Handles login form submission
- Uses Supabase service-role queries to authenticate against `auth.users`
- Verifies password with `password_verify`
- Loads `user_profiles` to get role and profile data
- Sets session data such as `user_id`, `email`, `full_name`, `role`, `logged_in`, and `user`
- Redirects users to role-specific dashboards
- Includes development fallback test accounts when `APP_ENV === 'development'`

### `public/portal/auth_check.php`
- Starts session and loads `paths.php`
- Implements `require_role()` to enforce page access
- Provides role helpers and redirect behavior
- Used by all portal pages to gate access

## 5. Portal Pages and Role Routes

### Portal directories
- `public/portal/registration/` - registration committee pages
- `public/portal/admin/` - admin portal pages
- `public/portal/school-officer/` - school officer portal pages
- `public/portal/member/` - member portal pages
- `public/portal/super-admin/` - super admin pages
- other committee or role folders may exist as needed

### Example portal routes
- `public/portal/registration/dashboard.php` - registration dashboard
- `public/portal/registration/members.php` - membership batch approval UI
- `public/portal/school-officer/members.php` - school officer member management (mapped by role config)
- `public/portal/admin/dashboard.php` - admin landing page
- `public/portal/member/dashboard.php` - member landing page

### Role URL mapping in `paths.php`
- `committee_registration` → `registration`
- `admin` → `admin`
- `school_officer` → `school-officer`
- `member` → `member`
- `eb_vp_internal` → `registration`
- `eb_president` → `super-admin`

## 6. API Routing

### `public/api.php`
- Acts as a generic proxy for API calls
- Reads `endpoint` and `action` from query parameters
- Includes the resulting file from `public/api/<endpoint>.php`
- Outputs JSON or 404 when the endpoint is missing
- Example: `/public/api.php?endpoint=auth&action=login`

### Direct API endpoints
- Some pages call API files directly instead of using `public/api.php`
- Example: `public/portal/registration/members.php` posts directly to `public/api/process-member-batch.php`

## 7. Key Supabase Integration Files

### `src/lib/Supabase.php`
- Namespaced as `App\\Lib\\Supabase`
- Uses Guzzle or fallback HTTP client to interact with Supabase
- Provides:
  - `from($table)` returning `SupabaseQuery`
  - `auth()` returning `SupabaseAuth`
  - `storage()` returning `SupabaseStorage`
  - `request($method, $path, $options, $useServiceKey, $jwt)`

### `src/lib/SupabaseQuery`
- Supports builder-style queries:
  - `select()`
  - `eq()`, `neq()`, `gt()`, `gte()`, `lt()`, `in()`, `like()`, `is()`
  - `order()`, `limit()`, `offset()`
  - `get()`, `insert()`, `update()`, `delete()`

### `src/lib/SupabaseAuth`
- Supports Supabase Auth endpoints:
  - `signUp()`
  - `signIn()`
  - `getUser()`
  - `updateUser()`
  - `adminCreateUser()`
  - `adminDeleteUser()`

### `src/lib/SupabaseClient.php`
- Legacy Supabase client using `curl`
- Provides simpler methods:
  - `select()`, `insert()`, `update()`, `delete()`, `upsert()`
  - `authSignUp()`, `authSignIn()`, `authUpdatePassword()`
- Used by pages like `login.php` and some legacy flows

## 8. Membership Batch Processing Flow

### `public/portal/registration/members.php`
- Presents a form where registration staff enter a member upload `batch_id`
- Sends a POST request to `public/api/process-member-batch.php`
- Shows processing summary on completion
- Requires authorized roles: `registration`, `committee_registration`, `admin`, `super_admin`

### `public/api/process-member-batch.php`
- Loads `auth_check.php` for authorization
- Uses `src/lib/supabase.php` for Supabase access
- Uses `includes/lib/EmailService.php` to send emails
- Validates request body and batch ID
- Loads member batch from `member_upload_batches` with nested `pending_members`
- For each pending member row:
  - validates required fields
  - checks existing member by email in `members`
  - if existing member:
    - uses existing `membership_id` or generates new one
    - updates member data
    - sends renewal confirmation email
    - increments renewed count
  - if new member:
    - creates Supabase auth user
    - inserts `user_profiles`
    - inserts `members`
    - sends credential email
    - increments new account count
  - updates `pending_members` status to `approved_payment_pending`
  - optionally records blockchain audit event
- Updates batch status to `approved_payment_pending`
- Returns JSON summary

### Membership ID generation in the endpoint
- `getMembershipPrefix($sb)` reads `member_id_prefix` from `system_settings`
- `getMembershipYear()` uses current year
- `getCounterRowForYear($sb, $year)` reads or creates a counter row in `member_id_counter`
- `generateMembershipId($sb)` increments that counter and builds an ID using the prefix, year, and zero-padded sequence

## 9. Database Structure Summary

### Important tables
- `members` — core member records
- `user_profiles` — role information and profile metadata
- `auth.users` — Supabase auth users
- `member_upload_batches` — uploaded CSV batch metadata
- `pending_members` — batch member rows awaiting approval
- `member_id_counter` — per-year membership ID sequence state
- `system_settings` — app settings like `member_id_prefix`

### `members` fields
- `id`
- `institution_id`
- `user_id`
- `full_name`
- `email`
- `membership_id`
- `member_type`
- `payment_status`
- `year_level`
- `created_at`, `updated_at`

### `member_id_counter`
- `year`
- `last_number`

## 10. Feature-to-File Mappings

### Login and session
- `login.php` — login form and authentication logic
- `logout.php` — end session
- `change-password.php` — password update flow

### Portal and dashboards
- `public/portal/registration/dashboard.php`
- `public/portal/registration/members.php`
- `public/portal/admin/dashboard.php`
- `public/portal/member/dashboard.php`
- `public/portal/school-officer/dashboard.php`

### API and backend actions
- `public/api.php` — proxy router for generic API calls
- `public/api/process-member-batch.php` — member batch approval
- `public/api/affiliation-review-action.php` — affiliation review actions
- `public/api/attendance.php` — attendance endpoints
- `public/api/auth.php` — authentication-related endpoint

### Shared utilities
- `includes/config.php`
- `includes/paths.php`
- `includes/role-config.php`
- `src/lib/Supabase.php`
- `src/lib/SupabaseClient.php`
- `src/lib/EmailService.php`
- `src/lib/BlockchainService.php`

## 11. Data Flow Example

### Member batch approval path
1. User opens `public/portal/registration/members.php`
2. User enters `batch_id` and submits
3. Browser sends POST JSON to `public/api/process-member-batch.php`
4. Endpoint loads batch and pending rows from Supabase
5. For each row, endpoint creates or updates member/auth records
6. Endpoint sends emails and updates statuses
7. Endpoint returns summary JSON
8. UI displays counts and errors

### Login path
1. User submits email/password to `login.php`
2. Server queries `auth.users` through `SupabaseClient`
3. Server verifies password and loads profile role
4. Session values are stored
5. User is redirected to role-specific portal page

## 12. Notes and Recommendations

- The system currently uses both `Supabase` and `SupabaseClient` libraries. Use `src/lib/Supabase.php` for new endpoints and keep `SupabaseClient.php` for legacy login/code.
- `public/api.php` is a generic proxy, but some newer flows post directly to `public/api/*.php`.
- `member_id_counter` is required for sequential membership IDs and should be populated before batch processing.
- `system_settings.member_id_prefix` controls the membership ID prefix.

---

This file is intended as a complete explanation of the codebase, routes, configuration, and feature connections for IECEP-LSC MEMSYS.
