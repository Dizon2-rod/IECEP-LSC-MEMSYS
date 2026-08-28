# IECEP-LSC MEMSYS - Complete System Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Core Features](#core-features)
3. [User Roles and Permissions](#user-roles-and-permissions)
4. [Database Architecture](#database-architecture)
5. [Key Functionalities](#key-functionalities)
6. [Technical Architecture](#technical-architecture)
7. [Security Features](#security-features)
8. [API Endpoints](#api-endpoints)
9. [Blockchain Integration](#blockchain-integration)
10. [Financial Reporting](#financial-reporting)

---

## System Overview

**IECEP-LSC MEMSYS** (Member Management System) is a comprehensive web-based platform designed for the Institute of Electronics Engineers of the Philippines - Luzon Student Chapter (IECEP-LSC). The system manages student chapter memberships, financial transactions, events, compliance tracking, and institutional affiliations with full blockchain transparency.

### System Purpose
- Centralize member management across affiliated educational institutions
- Streamline membership fee collection and financial reporting
- Track compliance and participation metrics
- Provide transparent financial operations through blockchain verification
- Facilitate event management and registration
- Enable digital credentialing and certification

### Technology Stack
- **Backend**: PHP 8.0+
- **Database**: PostgreSQL (Supabase)
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Authentication**: Supabase Auth
- **PDF Generation**: Dompdf
- **QR Code Generation**: Custom QR Code Service
- **Blockchain**: Custom Blockchain Service for transaction verification
- **Storage**: Supabase Storage
- **Real-time**: Supabase Realtime subscriptions

---

## Core Features

### 1. Membership Management
- **Member Registration**: New member onboarding with digital ID generation
- **Member Directory**: Centralized database of all members across institutions
- **Bulk Upload**: Excel-based batch member import with validation
- **Digital ID Cards**: QR-enabled digital credentials with blockchain verification
- **Membership Renewal**: Automated renewal tracking and reminders
- **Alumni Tracking**: Alumni status management and graduation tracking

### 2. Financial Management
- **Fee Collection**: Membership fees, event fees, and affiliation fees
- **Transaction Processing**: Multi-payment method support (bank transfer, credit card, online payment)
- **Receipt Generation**: Automated PDF receipts with blockchain verification
- **Financial Reporting**: Comprehensive dashboards for admin and school officers
- **Budget Management**: Budget allocation and tracking
- **Payment Plans**: Installment-based payment options
- **Blockchain Recording**: All financial transactions recorded on blockchain for auditability

### 3. Affiliation Management
- **School Affiliation**: New institution application and approval workflow
- **Document Verification**: Required document submission and verification
- **Compliance Tracking**: Institutional compliance scoring
- **Fee Brackets**: Tiered fee structure based on member count
- **Partner Chapters**: External chapter collaboration management

### 4. Event Management
- **Event Creation**: Conference, seminar, workshop, and meeting management
- **Registration System**: Online event registration with QR check-in
- **Attendance Tracking**: Real-time attendance monitoring
- **Event Logistics**: Venue, catering, and equipment management
- **Certificate Issuance**: Participation certificates with blockchain verification

### 5. Compliance & Audit
- **Compliance Scoring**: Institutional participation rate tracking
- **Audit Logs**: Comprehensive system activity logging
- **Financial Audit Trail**: Transaction modification tracking
- **Compliance Checks**: Automated rule validation
- **Participation Metrics**: Event attendance and hosting statistics

### 6. Communication & Notifications
- **Announcements**: Role-based announcement system
- **Push Notifications**: Real-time browser notifications
- **Email Templates**: Customizable email communications
- **Email Blasts**: Bulk email campaigns with tracking
- **Contact Messages**: Direct messaging system

### 7. Document Management
- **Document Repository**: Centralized document storage
- **Version Control**: Document versioning and history
- **Access Control**: Role-based document access
- **Minutes Templates**: Meeting minutes standardization

### 8. Committee Management
- **Registration Committee**: Member registration and duplicate detection
- **Creatives Committee**: Content creation and scheduling
- **Marketing Committee**: Campaign management and lead tracking
- **Logistics Committee**: Inventory and vendor management
- **Secretary**: Document and task management

### 9. Public Transparency
- **Financial Transparency Portal**: Public view of financial data
- **Blockchain Verification**: Transaction hash verification
- **Public Announcements**: Public-facing event and news updates
- **Member Directory Validation**: Public member credential verification

---

## User Roles and Permissions

### 1. Admin
**Full system access with administrative privileges**

**Permissions:**
- Complete access to all system modules
- User management and role assignment
- Financial dashboard with full reporting
- Transaction approval and processing
- Institution affiliation approval
- Event creation and management
- System configuration and settings
- Audit log access
- Blockchain verification access
- Impersonation capabilities (for troubleshooting)

**Key Features:**
- Financial Dashboard with summary cards, charts, and tables
- Transaction management with payment processing
- Institution management and compliance monitoring
- Member directory access across all institutions
- System logs and audit trail review
- Role permission management

### 2. School Officer
**Institution-specific access for managing their school chapter**

**Permissions:**
- View and manage their institution's members only
- Upload member directories for their institution
- View their institution's financial reports
- Register members for events
- View compliance status for their institution
- Access institution-specific documents
- Generate digital IDs for their members

**Key Features:**
- School Officer Dashboard with institution overview
- Member management (view, upload, validate)
- Financial Reports (institution-specific view)
- Compliance Status monitoring
- Event registration for members
- Document access (institution-specific)

**Data Isolation:**
- All queries filtered by `institution_id` from session
- Cannot view other institutions' data
- Cannot access system-wide financial data

### 3. Member
**Individual member access for personal information**

**Permissions:**
- View personal profile and digital ID
- Register for events
- View personal transaction history
- Download personal receipts
- Update personal information
- View announcements

**Key Features:**
- Member Dashboard with personal overview
- Digital ID access and verification
- Event registration
- Payment history
- Profile management
- Notification access

---

## Database Architecture

### Core Tables (50+ tables)

#### Level 1: Core Entities (No dependencies)
- **institutions**: Affiliated educational institutions
- **affiliated_schools**: School chapter information
- **partner_chapters**: External partner organizations
- **fee_brackets**: Tiered fee structure configuration
- **compliance_rules**: Compliance threshold rules
- **system_settings**: System configuration parameters

#### Level 2: User Management
- **user_profiles**: User account profiles with role assignment
- **auth.users**: Supabase authentication users (managed by Supabase)

#### Level 3: Membership
- **members**: Member records with digital ID information
- **member_id_counter**: Membership ID sequence tracking
- **upload_batches**: Batch upload tracking for member imports
- **membership_id_sequences**: Year-based membership ID sequences

#### Level 4: Affiliation
- **pending_affiliations**: New institution applications
- **affiliation_applications**: Legacy affiliation applications
- **affiliation_documents**: Required document submissions
- **affiliation_approvals**: Multi-level approval workflow
- **member_directory_imports**: Imported member data validation

#### Level 5: Financial
- **transactions**: All financial transactions with blockchain integration
- **payments**: Payment records linked to members
- **invoices**: Invoice generation and tracking
- **payment_plans**: Installment payment schedules
- **budgets**: Budget allocation and tracking
- **transactions_archive**: Historical transaction archiving
- **payment_gateway_logs**: Payment gateway integration logs

#### Level 6: Events
- **events**: Event creation and management
- **event_registrations**: Event registration records
- **event_attachments**: Event-related files
- **event_attendees**: Attendance tracking
- **event_logistics**: Event logistics management

#### Level 7: Notifications
- **notifications**: User notifications
- **push_subscriptions**: Push notification subscriptions
- **email_templates**: Email template management
- **email_verification_tokens**: Email change verification
- **password_resets**: Password reset tokens

#### Level 8: Audit & Compliance
- **audit_logs**: System activity logging
- **compliance_scores**: Institutional compliance metrics
- **financial_audit_trail**: Financial transaction audit
- **compliance_checks**: Automated compliance validation

#### Level 9: Content
- **announcements**: System announcements
- **scheduled_announcements**: Scheduled content publishing
- **content_workflow**: Content approval workflow

#### Level 10: Certificates
- **certificates**: Certificate issuance with blockchain verification

#### Level 11: Blockchain
- **blockchain_records**: Blockchain transaction records for verification

#### Level 12: Collaboration
- **collaboration_posts**: Partner chapter collaboration posts

#### Level 13: Admin Features
- **system_logs**: System-level logging
- **role_permissions**: Role-based permission management
- **cron_jobs**: Scheduled task management
- **impersonation_sessions**: Admin impersonation tracking

#### Level 14: Member Features
- **user_reminder_settings**: User notification preferences

#### Level 15: School Officer Features
- **schools**: School chapter information
- **temp_school_members**: Temporary member import data

#### Level 16: Secretary Features
- **documents**: Document repository
- **minutes_templates**: Meeting minutes templates
- **committee_tasks**: Committee task management

#### Level 17: Marketing Features
- **marketing_campaigns**: Marketing campaign management
- **email_blasts**: Bulk email campaigns
- **email_tracking**: Email engagement tracking
- **leads**: Lead management
- **social_posts**: Social media scheduling

#### Level 18: Logistics Features
- **inventory_items**: Inventory management
- **vendors**: Vendor management
- **asset_loans**: Asset borrowing tracking

#### Level 19: Registration Features
- **potential_duplicates**: Duplicate member detection
- **temp_user_imports**: Temporary user import data

### Key Database Features
- **Row Level Security (RLS)**: Fine-grained access control
- **Automatic Timestamps**: Triggers for created_at/updated_at
- **UUID Primary Keys**: Distributed system compatibility
- **Foreign Key Constraints**: Data integrity enforcement
- **JSONB Columns**: Flexible metadata storage
- **Indexes**: Optimized query performance
- **Real-time Subscriptions**: Live data updates

---

## Key Functionalities

### 1. Member Registration Flow
1. School Officer uploads member directory via Excel
2. System validates data (email format, required fields)
3. Pending members created in `pending_members` table
4. EB Treasurer reviews and approves payment
5. Payment processed and transaction recorded on blockchain
6. Supabase Auth user account created
7. Member record created in `members` table
8. Digital ID generated with QR code
9. Digital ID hash recorded on blockchain
10. Credentials email sent to member
11. Member can log in and access dashboard

### 2. Affiliation Application Flow
1. Institution submits affiliation application
2. Required documents uploaded (letter of intent, constitution, etc.)
3. Registration Committee reviews documents
4. Multi-level approval workflow (initial → board → final)
5. Compliance checks performed
6. Institution approved and added to system
7. Fee bracket assigned based on member count
8. Affiliation fee payment processed
9. Institution gains School Officer access

### 3. Event Registration Flow
1. Admin or School Officer creates event
2. Event details published (date, venue, fee, capacity)
3. Members register for event
4. Payment processed if required
5. QR ticket generated
6. Check-in via QR code at event
7. Attendance recorded
8. Certificate issued with blockchain verification
9. Compliance scores updated

### 4. Financial Transaction Flow
1. Transaction initiated (membership fee, event fee, etc.)
2. Payment processed via chosen method
3. Transaction status updated to 'paid'
4. Blockchain record created with transaction data
5. Blockchain hash stored in transaction record
6. Receipt PDF generated with blockchain verification
7. Receipt uploaded to Supabase Storage
8. Financial dashboards updated
9. Compliance scores recalculated

### 5. Compliance Scoring
1. Participation rate calculated (events attended / total events)
2. Hosted event count tracked
3. Overall score computed based on rules
4. Institutional compliance status determined
5. Alerts sent for non-compliant institutions
6. Historical trends tracked

### 6. Digital ID Verification
1. Digital ID contains QR code with verification URL
2. URL includes member ID and hash
3. Verification endpoint validates hash against blockchain
4. Member details displayed if valid
5. Verification status shown (valid/invalid)
6. Timestamp and verification count tracked

---

## Technical Architecture

### Backend Architecture
```
/public
  /api
    /bootstrap.php          - Application bootstrap
    /auth.php               - Authentication endpoints
    /blockchain.php         - Blockchain service
    /financial-report.php   - Financial reporting API
    /verify-transaction.php - Transaction verification
    /treasurer.php          - Treasurer operations
    /attendance.php         - Attendance tracking
    /member.php             - Member operations
    /event-registration.php - Event registration
    /renew-membership.php   - Membership renewal
    /compliance.php         - Compliance operations
  /portal
    /admin                  - Admin dashboard
      /financial
        /dashboard.php      - Financial dashboard
        /transactions.php   - Transaction management
        /reports.php        - Financial reports
    /school-officer         - School Officer dashboard
      /financial
        /reports.php        - Institution financial reports
      /members
        /list.php           - Member list
        /upload.php         - Member upload
      /compliance
        /status.php         - Compliance status
  /transparency.php         - Public transparency portal
/includes
  /config.php               - Configuration
  /supabase.php            - Supabase client
  /middleware
    /auth.php               - Authentication middleware
  /lib
    /BlockchainService.php - Blockchain operations
    /EmailService.php       - Email operations
    /PdfService.php         - PDF generation
    /QrCodeService.php     - QR code generation
    /DigitalIdService.php  - Digital ID generation
/src
  /lib
    /pdf.php                - PDF service implementation
```

### Frontend Architecture
- **Vanilla JavaScript**: No framework dependencies
- **Chart.js**: Data visualization for financial dashboards
- **Bootstrap 5**: UI framework
- **Font Awesome 6**: Icon library
- **Design Tokens**: Consistent styling (#0B1D4A, #D4AF37, Inter font)

### Service Layer
- **Supabase Client**: Database operations
- **BlockchainService**: Transaction recording and verification
- **EmailService**: Email communications
- **PdfService**: Receipt and certificate generation
- **QrCodeService**: QR code generation
- **DigitalIdService**: Digital ID creation

### Authentication Flow
1. User logs in via Supabase Auth
2. Session stored in PHP session
3. User profile loaded from `user_profiles` table
4. Role checked via middleware
5. Access granted based on role permissions
6. Session timeout handling
7. Password change enforcement for new users

---

## Security Features

### 1. Authentication
- **Supabase Auth**: Secure authentication with JWT tokens
- **Password Requirements**: Enforced password complexity
- **Session Management**: Secure session handling
- **Password Reset**: Token-based password reset
- **Email Verification**: Email change verification

### 2. Authorization
- **Role-Based Access Control (RBAC)**: Three-tier role system
- **Row Level Security (RLS)**: Database-level access control
- **Middleware Protection**: Route-level authentication checks
- **Data Isolation**: School Officer data filtered by institution

### 3. Data Protection
- **Input Validation**: All user inputs sanitized
- **SQL Injection Prevention**: Parameterized queries via Supabase
- **XSS Protection**: Output encoding
- **CSRF Protection**: Token-based CSRF protection
- **File Upload Validation**: File type and size restrictions

### 4. Audit Trail
- **Audit Logs**: All system actions logged
- **Financial Audit Trail**: Transaction modifications tracked
- **User Activity**: User actions recorded
- **IP Address Logging**: Source IP tracking
- **User Agent Logging**: Device/browser tracking

### 5. Blockchain Security
- **Immutable Records**: Blockchain-verified transactions
- **Hash Verification**: Transaction hash verification
- **Digital ID Verification**: Credential verification via blockchain
- **Certificate Verification**: Certificate authenticity verification

### 6. Data Encryption
- **TLS/SSL**: Encrypted data transmission
- **Password Hashing**: Secure password storage
- **Sensitive Data**: Encrypted storage where applicable

---

## API Endpoints

### Authentication
- `POST /api/auth.php?action=login` - User login
- `POST /api/auth.php?action=logout` - User logout
- `POST /api/auth.php?action=register` - User registration
- `POST /api/auth.php?action=reset-password` - Password reset

### Financial
- `GET /api/financial-report.php?type=monthly&year=YYYY` - Monthly financial data
- `GET /api/financial-report.php?type=annual&year=YYYY` - Annual financial data
- `GET /api/financial-report.php?type=per-institution` - Per-institution data
- `GET /api/financial-report.php?type=per-event` - Per-event data
- `GET /api/verify-transaction.php?hash=HASH` - Transaction verification

### Treasurer
- `GET /api/treasurer.php?action=pending-member-payments` - Get pending payments
- `POST /api/treasurer.php?action=mark-members-paid` - Mark members as paid
- `GET /api/treasurer.php?action=transactions` - Get transactions
- `POST /api/treasurer.php?action=report` - Generate financial report

### Members
- `GET /api/member.php?action=list` - List members
- `POST /api/member.php?action=create` - Create member
- `PUT /api/member.php?action=update` - Update member
- `DELETE /api/member.php?action=delete` - Delete member

### Events
- `GET /api/event-registration.php?action=list` - List events
- `POST /api/event-registration.php?action=register` - Register for event
- `POST /api/event-registration.php?action=check-in` - Check-in to event

### Compliance
- `GET /api/compliance.php?action=scores` - Get compliance scores
- `POST /api/compliance.php?action=calculate` - Calculate compliance

### Attendance
- `POST /api/attendance.php?action=record` - Record attendance
- `GET /api/attendance.php?action=report` - Attendance report

---

## Blockchain Integration

### Purpose
- **Transparency**: Public verification of financial transactions
- **Auditability**: Immutable transaction records
- **Trust**: Verifiable credentials and certificates

### Implementation
- **BlockchainService**: Custom service for blockchain operations
- **Transaction Recording**: All financial transactions recorded
- **Hash Storage**: Transaction hashes stored in database
- **Verification API**: Public verification endpoint

### Recorded Entities
- **Transactions**: Financial transactions (membership fees, event fees)
- **Digital IDs**: Member digital credentials
- **Certificates**: Event participation certificates
- **Membership Changes**: Member status changes

### Verification Flow
1. User provides transaction hash
2. System queries blockchain_records table
3. Hash validated against stored data
4. Transaction details returned (with masked personal data)
5. Verification status displayed

### Public Transparency Portal
- **Location**: `/transparency.php`
- **Features**:
  - Total funds collected
  - Blockchain-verified transaction count
  - Last updated timestamp
  - Hash verification search
  - Public financial data display

---

## Financial Reporting

### Admin Financial Dashboard
**Location**: `/portal/admin/financial/dashboard.php`

**Features**:
- **Summary Cards**:
  - Total Income (Current Year)
  - Total Pending Payments
  - Number of Paid Transactions
  - Institutions with Outstanding Fees
- **Monthly Income Chart**: Bar chart showing monthly income trends
- **Per-Institution Table**: Income by institution with pending amounts
- **Per-Event Table**: Income by event with blockchain verification badges
- **Blockchain Verification**: Links to transparency portal for verification

### School Officer Financial Reports
**Location**: `/portal/school-officer/financial/reports.php`

**Features**:
- **Fee Summary Card**: Institution fee overview
- **Transaction History**: All payments by institution
- **Event Participation Fees**: Event-related payments
- **Data Isolation**: Only shows institution's own data

### Financial Report API
**Location**: `/api/financial-report.php`

**Parameters**:
- `type`: monthly, annual, per-institution, per-event
- `year`: YYYY (for monthly/annual)

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "month": "January",
      "total_income": 50000.00,
      "total_transactions": 50
    }
  ],
  "blockchain_hash": "abc123..."
}
```

### Receipt Generation
- **PDF Receipts**: Automated receipt generation
- **Blockchain Verification**: Hash included on receipt
- **QR Code**: Verification QR code
- **Storage**: Supabase Storage for receipt files

---

## System Configuration

### Fee Brackets (Board Resolution No. 021-2024)

| Bracket | Student Members | National Affiliation Fee |
|------------|----------------|--------------------------|
| Small | 1–50 | ₱1,500.00 |
| Medium | 51–100 | ₱2,000.00 |
| Large | 101–150 | ₱2,500.00 |
| Enterprise | 151 and above | ₱3,000.00 |

Plus **₱800.00 operational and activity fee** per organization, collected upon each renewal of affiliation every new school year.

### Compliance Rules
- **Minimum Participation**: 40% required
- **Required Hosted Events**: 1 event per year

### System Settings
- **Academic Year Start**: June 1
- **Academic Year End**: May 31
- **Compliance Threshold**: 40%

---

## Deployment

### Requirements
- PHP 8.0+
- PostgreSQL (Supabase)
- Supabase Account
- SSL Certificate
- Domain Name

### Environment Variables
- `SUPABASE_URL`: Supabase project URL
- `SUPABASE_KEY`: Supabase API key
- `APP_URL`: Application base URL
- `EMAIL_HOST`: SMTP host
- `EMAIL_PORT`: SMTP port
- `EMAIL_USER`: SMTP username
- `EMAIL_PASS`: SMTP password

### Database Setup
1. Execute `database/supabase_complete_query.sql` in Supabase SQL Editor
2. Configure Row Level Security policies
3. Set up storage buckets (receipts, documents, member_ids)
4. Configure email templates
5. Set up fee brackets and compliance rules

### File Permissions
- `/storage`: Writeable for file uploads
- `/logs`: Writeable for application logs
- `/temp`: Writeable for temporary files

---

## Maintenance

### Regular Tasks
- **Database Backups**: Daily automated backups
- **Log Rotation**: Weekly log cleanup
- **Certificate Renewal**: SSL certificate renewal
- **System Updates**: Regular security updates
- **Performance Monitoring**: System performance tracking

### Monitoring
- **System Logs**: Error and activity logging
- **Audit Logs**: Security event tracking
- **Performance Metrics**: Response time monitoring
- **Database Performance**: Query optimization

### Troubleshooting
- **Authentication Issues**: Check Supabase Auth configuration
- **Database Issues**: Check connection and RLS policies
- **Email Issues**: Verify SMTP configuration
- **File Upload Issues**: Check storage permissions
- **Blockchain Issues**: Verify blockchain service connectivity

---

## Support

### Documentation
- **API Documentation**: Endpoint specifications
- **User Guides**: Role-specific user manuals
- **Admin Guide**: System administration guide
- **Developer Guide**: Customization guide

### Contact
- **Technical Support**: support@iecep-lsc.org
- **System Administrator**: admin@iecep-lsc.org
- **Documentation**: docs@iecep-lsc.org

---

---

## Enterprise Cryptographic Blockchain & Audit System

### Architecture Overview
The IECEP-LSC MEMSYS implements a high-performance **Enterprise Cryptographic Ledger (Private Hash-Chained Audit Trail)** using SHA-256 cryptographic chaining, asymmetric RSA-2048 digital signatures via OpenSSL, and Merkle tree batch hashing.

```
[ Affiliation / Member / Receipt Payload ]
                 │
                 ▼
[ Deterministic JSON Key Sorting (jsonSort) ]
                 │
                 ▼
[ SHA-256 Chaining: Hash(Entity + Payload + Previous Hash) ]
                 │
                 ▼
[ Asymmetric Digital Signing (RSA-2048 via Chapter Private Key) ]
                 │
                 ▼
[ Supabase/PostgreSQL Immutable Ledger: blockchain_records ]
                 │
                 ▼
[ Interactive Blockchain Explorer & Public Verification Gateways ]
```

### Key Components

1. **Core Service (`src/lib/BlockchainService.php`)**:
   - `record()`: Universal hash-chaining recorder with automatic RSA-2048 digital signing.
   - `recordAffiliation()`: Anchors institutional applications and all 6 required uploaded files with a combined Merkle root.
   - `recordMemberBatch()`: Hashes member rosters in batches and anchors them with binary Merkle roots.
   - `pullMemberHistory()`: Chronologically reconstructs and cryptographically validates the entire state history of any student member.
   - `pushMemberUpdate()`: Appends verified member state updates.
   - `recordReceipt()` & `recordFinancialTransaction()`: Cryptographically seals payments and financial audits.
   - `verifyChain()`: Scans full block sequences for any tampered historical records.
   - `exportBlockProof()`: Generates W3C-compatible `.json` verifiable proof receipts.

2. **Binary Merkle Tree Engine (`src/lib/MerkleTree.php`)**:
   - Computes logarithmic $O(\log N)$ cryptographic Merkle roots for batch verification of uploaded directories and multi-document affiliation kits.

3. **Public Blockchain Explorer (`public/blockchain-explorer.php`)**:
   - Real-time block height and 100% chain integrity monitor.
   - Multi-chain filtering (*Affiliations, Required Documents, Members, Batches, Receipts, Financial Audits, Compliance*).
   - Search by Block Hash, Transaction ID, Entity ID, or Student ID.
   - Interactive Block Details Inspector with JSON payload viewer and one-click **"Download Verifiable Cryptographic Proof (.json)"**.

4. **Cryptographic Key Management (`storage/keys/`)**:
   - `iecep_blockchain_private.pem`: 2048-bit RSA Chapter Private Key (used strictly by server node to sign blocks).
   - `iecep_blockchain_public.pem`: Public Key Certificate (used for public third-party verification).

---

## Version History

### Version 2.0 (August 2026)
- **Enterprise Blockchain Engine**: Added RSA-2048 asymmetric digital signatures for non-repudiation.
- **Affiliation Requirements Auto-Anchoring**: Automatic SHA-256 and Merkle root calculation for all 6 required affiliation documents.
- **Member Push & Pull Engine**: Added deterministic state reconstruction and batch Merkle tree hashing.
- **Dedicated Blockchain Explorer**: Built an interactive public explorer dashboard (`public/blockchain-explorer.php`).
- **Verifiable Proof Certificates**: One-click W3C JSON-LD proof download.
- **Database Schema Upgrades**: Enhanced `blockchain_records` with UUID compatibility and signature metadata.

---

**Last Updated**: August 28, 2026
**System Version**: 2.0.0
**Documentation Version**: 2.0.0
