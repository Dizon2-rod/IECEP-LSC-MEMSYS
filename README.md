# IECEP-LSC MEMSYS (Membership Management System)

![IECEP-LSC Logo](public/uploads/features/1776563416_iecep-logo.png)

A comprehensive Progressive Web Application (PWA) for managing membership, affiliations, and compliance tracking for the Institute of Electronics Engineers of the Philippines - Laguna Student Chapter.

## 📋 Table of Contents
- [Overview](#overview)
- [Features](#features)
- [System Architecture](#system-architecture)
- [Installation](#installation)
- [Configuration](#configuration)
- [User Roles & Permissions](#user-roles--permissions)
- [API Documentation](#api-documentation)
- [Database Schema](#database-schema)
- [Development](#development)
- [Deployment](#deployment)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## 📖 Overview

IECEP-LSC MEMSYS is a modern web-based system designed to streamline membership management, school affiliations, compliance tracking, and event management for the IECEP Laguna Student Chapter. The system provides role-based access control, real-time notifications, document management, and comprehensive reporting capabilities.

### Key Objectives
- **Digital Transformation**: Replace manual processes with automated workflows
- **Centralized Management**: Single platform for all membership and affiliation operations
- **Compliance Tracking**: Monitor and enforce membership requirements
- **Real-time Communication**: Instant notifications and updates
- **Data Security**: Secure handling of sensitive member information
- **Scalability**: Support for growing membership base and multiple institutions

## ✨ Features

### 🏫 Affiliation Management
- **Multi-step Application Process**: Email verification → Document submission → Committee review → Approval
- **Document Upload**: Support for PDF, Excel, and CSV files
- **Automated Fee Calculation**: Based on member count and type (new/returning)
- **Real-time Status Tracking**: From submission to approval
- **Committee Review Dashboard**: Streamlined approval workflow

### 👥 Member Management
- **Bulk Import**: CSV/Excel member directory parsing
- **Member Classification**: Automatic detection of new vs. returning members
- **Digital ID Generation**: Unique member IDs with QR codes
- **Profile Management**: Complete member profiles with contact information
- **Status Tracking**: Active, pending, expired, and renewal statuses

### 💰 Financial Management
- **Automated Fee Calculation**: Based on IECEP-LSC constitution guidelines
- **Payment Simulation**: GCash payment simulation for testing
- **Receipt Generation**: Automated receipt creation
- **Financial Reports**: Comprehensive financial tracking and reporting
- **Fee Structure Management**: Configurable fee brackets

### 📊 Compliance & Reporting
- **Compliance Dashboard**: Visual tracking of member compliance
- **Automated Reminders**: Email notifications for pending requirements
- **Document Verification**: Secure document storage and verification
- **Audit Logs**: Complete audit trail of all system activities
- **Export Capabilities**: CSV/Excel/PDF exports for all reports

### 🔔 Notifications & Communication
- **Real-time Notifications**: In-app notification system
- **Email Integration**: SMTP-based email delivery
- **Push Notifications**: Browser push notifications
- **Announcement System**: Broadcast announcements to specific user groups
- **Event Reminders**: Automated event notifications

### 🎯 Event Management
- **Event Registration**: Online event sign-ups
- **QR Code Check-in**: Mobile-friendly event attendance tracking
- **Attendance Reports**: Detailed participation analytics
- **Certificate Generation**: Automated certificate creation for events
- **Event Calendar**: Integrated calendar view

### 🔐 Security Features
- **Role-Based Access Control**: Granular permissions for different user types
- **JWT Authentication**: Secure token-based authentication
- **CSRF Protection**: Cross-site request forgery protection
- **File Upload Security**: Virus scanning and type validation
- **Audit Logging**: Complete activity tracking
- **Data Encryption**: Sensitive data encryption at rest and in transit

## 🏗️ System Architecture

### Technology Stack
- **Backend**: PHP 8.0+ with custom MVC architecture
- **Frontend**: HTML5, CSS3, JavaScript (Vanilla + ES6+)
- **Database**: Supabase (PostgreSQL) with real-time capabilities
- **Authentication**: JWT + Supabase Auth
- **File Storage**: Local file system with Supabase Storage backup
- **Email**: PHPMailer with SMTP
- **PDF Generation**: DomPDF
- **QR Codes**: Endroid QR Code
- **Spreadsheets**: PhpSpreadsheet

### Directory Structure
```
IECEP-LSC-MEMSYS/
├── public/                    # Publicly accessible files
│   ├── api/                  # API endpoints
│   ├── portal/               # User portal (role-based)
│   ├── uploads/              # File uploads
│   └── assets/               # Static assets
├── src/                      # Application source code
│   ├── lib/                  # Core libraries
│   └── config/               # Configuration classes
├── includes/                 # Shared includes
│   ├── data/                 # Data files
│   ├── helpers/              # Helper functions
│   └── middleware/           # Middleware components
├── database/                 # Database migrations
├── cron/                     # Scheduled tasks
├── logs/                     # Application logs
├── storage/                  # Temporary storage
├── vendor/                   # Composer dependencies
└── tools/                    # Development tools
```

### Data Flow
1. **User Request** → Frontend Interface
2. **API Endpoint** → PHP Controller
3. **Business Logic** → Service Layer
4. **Data Access** → SupabaseClient
5. **Database** → Supabase PostgreSQL
6. **Response** → JSON/HTML to User

## 🚀 Installation

### Prerequisites
- PHP 8.0 or higher
- Composer
- Node.js (for optional frontend dependencies)
- Web server (Apache/Nginx)
- Supabase account
- SMTP email service

### Step-by-Step Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/Dizon2-rod/IECEP-LSC-MEMSYS.git
   cd IECEP-LSC-MEMSYS
   ```

2. **Install PHP Dependencies**
   ```bash
   composer install
   ```

3. **Install JavaScript Dependencies (Optional)**
   ```bash
   npm install
   ```

4. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your configuration
   ```

5. **Set Up Supabase**
   - Create a new Supabase project
   - Run database migrations from `database/migrations/`
   - Configure RLS (Row Level Security) policies

6. **Configure Web Server**
   - Point document root to `public/` directory
   - Enable URL rewriting
   - Set proper file permissions

7. **Run Initial Setup**
   ```bash
   php tools/setup.php
   ```

### Quick Start with XAMPP
1. Copy project to `htdocs/IECEP-LSC-MEMSYS`
2. Start Apache and MySQL in XAMPP Control Panel
3. Access via `http://localhost/IECEP-LSC-MEMSYS`

## ⚙️ Configuration

### Environment Variables (.env)
```env
# Application
APP_NAME="IECEP-LSC MEMSYS"
APP_URL="http://localhost/IECEP-LSC-MEMSYS"
APP_ENV="development"

# Supabase
SUPABASE_URL="https://your-project.supabase.co"
SUPABASE_ANON_KEY="your-anon-key"
SUPABASE_SERVICE_ROLE_KEY="your-service-role-key"

# Email (SMTP)
SMTP_HOST="smtp.gmail.com"
SMTP_PORT=587
SMTP_USERNAME="your-email@gmail.com"
SMTP_PASSWORD="your-app-password"
SMTP_FROM_NAME="IECEP-LSC-MEMSYS"
SMTP_FROM_EMAIL="your-email@gmail.com"

# Security
JWT_SECRET="your-jwt-secret-key"
SESSION_LIFETIME=86400
CRON_SECRET="your-cron-secret"

# File Uploads
MAX_FILE_SIZE=5242880  # 5MB
ALLOWED_FILE_TYPES="pdf,doc,docx,jpg,jpeg,png,xls,xlsx,csv"
```

### Supabase Setup
1. **Create Tables**: Run all SQL files in `database/migrations/`
2. **Configure RLS**: Enable Row Level Security on all tables
3. **Set Up Storage**: Create `affiliations` and `documents` buckets
4. **Configure Auth**: Set up email authentication providers

### Email Configuration
- **Gmail**: Use App Password (2-factor authentication required)
- **Other SMTP**: Update host, port, and credentials accordingly
- **Test Email**: Use `tools/test-email.php` to verify configuration

## 👥 User Roles & Permissions

### Role Hierarchy
1. **Super Admin** (EB President)
   - Full system access
   - User management
   - System configuration
   - Audit logs

2. **Administrator**
   - Affiliation approvals
   - Member management
   - Financial oversight
   - Report generation

3. **School Officer**
   - Member management for their institution
   - Document uploads
   - Compliance tracking
   - Payment management

4. **Member**
   - Personal profile management
   - Event registration
   - Digital ID access
   - Payment history

5. **Committee Roles**
   - **Creatives**: Content management, announcements
   - **Logistics**: Event management, venue coordination
   - **Treasurer**: Financial management, fee collection
   - **Auditor**: Compliance verification, audit trails
   - **Secretary**: Documentation, minutes, correspondence

### Permission Matrix
| Feature | Super Admin | Admin | School Officer | Member | Committee |
|---------|-------------|-------|----------------|--------|-----------|
| User Management | ✅ Full | ✅ Limited | ❌ | ❌ | ❌ |
| Affiliation Approval | ✅ | ✅ | ❌ | ❌ | ❌ |
| Member Management | ✅ | ✅ | ✅ (Own) | ❌ | ❌ |
| Financial Management | ✅ | ✅ | ✅ (Own) | ✅ (Own) | ✅ (Role) |
| Document Upload | ✅ | ✅ | ✅ | ❌ | ✅ (Role) |
| Event Management | ✅ | ✅ | ✅ | ✅ | ✅ (Role) |
| Reports | ✅ | ✅ | ✅ (Own) | ✅ (Own) | ✅ (Role) |
| System Configuration | ✅ | ❌ | ❌ | ❌ | ❌ |

## 🔌 API Documentation

### Base URL
```
http://your-domain.com/IECEP-LSC-MEMSYS/public/api/
```

### Authentication Endpoints
- `POST /auth.php` - User login
- `POST /request-password-reset.php` - Password reset request
- `POST /reset-password.php` - Password reset confirmation

### Affiliation Endpoints
- `POST /submit-affiliation.php` - Submit new affiliation application
- `GET /affiliation_status.php` - Check affiliation status
- `POST /affiliation-review-action.php` - Committee review actions
- `POST /affiliation-revision.php` - Request revisions

### Member Endpoints
- `POST /member.php` - Member CRUD operations
- `POST /process-member-batch.php` - Bulk member processing
- `GET /verify-member.php` - Member verification
- `POST /renew-membership.php` - Membership renewal

### Financial Endpoints
- `POST /calculate-fees.php` - Fee calculation
- `POST /simulate-payment.php` - Payment simulation
- `POST /generate-receipt.php` - Receipt generation
- `GET /financial-report.php` - Financial reports

### Event Endpoints
- `POST /events.php` - Event management
- `POST /event-registration.php` - Event registration
- `POST /event-qr-checkin.php` - QR check-in
- `GET /attendance.php` - Attendance reports

### Notification Endpoints
- `POST /notifications.php` - Notification management
- `POST /send-notification.php` - Send notifications
- `POST /send-reminder.php` - Send reminders

### API Response Format
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {},
  "timestamp": "2024-01-01T12:00:00+08:00"
}
```

## 🗄️ Database Schema

### Core Tables
- **user_profiles** - User accounts and profiles
- **members** - Member information and status
- **pending_affiliations** - Affiliation applications
- **institutions** - School/institution details
- **transactions** - Financial transactions
- **events** - Event information
- **attendance** - Event attendance records
- **notifications** - System notifications
- **audit_logs** - System audit trail

### Key Relationships
- One Institution → Many Members
- One Member → Many Transactions
- One Event → Many Attendance records
- One User → One Role → Many Permissions

### Indexes
- Email addresses (unique)
- Member IDs (unique)
- Transaction references (unique)
- Timestamps for reporting

## 💻 Development

### Development Setup
1. **Clone Development Branch**
   ```bash
   git clone -b develop https://github.com/Dizon2-rod/IECEP-LSC-MEMSYS.git
   ```

2. **Install Development Dependencies**
   ```bash
   composer install --dev
   ```

3. **Set Up Development Environment**
   ```bash
   cp .env.example .env.dev
   # Configure for local development
   ```

4. **Run Development Server**
   ```bash
   php -S localhost:8000 -t public
   ```

### Coding Standards
- **PSR-12** for PHP code
- **ES6+** for JavaScript
- **BEM** methodology for CSS
- **Meaningful commit messages**
- **Documentation for all public methods**

### Testing
```bash
# Run unit tests
php vendor/bin/phpunit tests/

# Run integration tests
php tools/test-integration.php

# Test API endpoints
php tools/test-api.php
```

### Debugging
- **XDebug** for PHP debugging
- **Browser DevTools** for frontend
- **Supabase Logs** for database queries
- **Application Logs** in `logs/` directory

## 🚢 Deployment

### Production Checklist
- [ ] Update `.env` with production values
- [ ] Set `APP_ENV=production`
- [ ] Configure SSL certificate
- [ ] Set up backup system
- [ ] Configure monitoring
- [ ] Test all critical paths
- [ ] Update database indexes
- [ ] Configure CDN (if needed)

### Deployment Steps
1. **Prepare Production Environment**
   ```bash
   git pull origin main
   composer install --no-dev --optimize-autoloader
   ```

2. **Database Migration**
   ```bash
   php database/migrate.php --env=production
   ```

3. **File Permissions**
   ```bash
   chmod 755 public/
   chmod 644 public/.htaccess
   chmod 777 logs/ storage/ public/uploads/
   ```

4. **Web Server Configuration**
   - Configure virtual host
   - Enable HTTPS
   - Set up caching
   - Configure security headers

5. **Monitoring Setup**
   - Error tracking (Sentry/Loggly)
   - Performance monitoring
   - Uptime monitoring
   - Backup verification

### Backup Strategy
- **Daily database backups** to cloud storage
- **Weekly full system backups**
- **Real-time file sync** for uploads
- **Versioned backups** with retention policy

## 🔒 Security

### Security Features
1. **Authentication & Authorization**
   - JWT-based authentication
   - Role-based access control
   - Session management with secure cookies
   - Password hashing with bcrypt

2. **Input Validation**
   - Server-side validation for all inputs
   - SQL injection prevention
   - XSS protection
   - CSRF tokens

3. **File Upload Security**
   - File type validation
   - Virus scanning
   - Size limits
   - Secure storage

4. **Data Protection**
   - Encryption at rest for sensitive data
   - HTTPS enforcement
   - Secure headers (CSP, HSTS)
   - Regular security audits

### Security Best Practices
- Regular dependency updates
- Security headers configuration
- Rate limiting on API endpoints
- Regular security scanning
- Incident response plan

### Compliance
- **Data Privacy Act (Philippines)** compliance
- Secure handling of personal information
- Right to erasure implementation
- Data breach notification procedures

## 🐛 Troubleshooting

### Common Issues

#### 1. Email Not Sending
**Symptoms**: Verification emails not received
**Solutions**:
- Check SMTP configuration in `.env`
- Verify email credentials
- Check spam folder
- Test with `tools/test-email.php`

#### 2. File Upload Failures
**Symptoms**: "Failed to upload file" errors
**Solutions**:
- Check `upload_max_filesize` in php.ini
- Verify directory permissions
- Check file type restrictions
- Review error logs

#### 3. Database Connection Issues
**Symptoms**: "Supabase connection failed"
**Solutions**:
- Verify Supabase URL and keys
- Check network connectivity
- Verify RLS policies
- Check Supabase project status

#### 4. Slow Performance
**Symptoms**: Slow page loads, timeouts
**Solutions**:
- Enable caching
- Optimize database queries
- Compress assets
- Implement CDN

### Log Files
- **Application Logs**: `logs/error.log`
- **Access Logs**: Web server logs
- **Database Logs**: Supabase dashboard
- **Email Logs**: SMTP server logs

### Debug Mode
Enable debug mode in `.env`:
```env
APP_ENV=development
DEBUG=true
```

## 🤝 Contributing

### Contribution Guidelines
1. **Fork the Repository**
2. **Create a Feature Branch**
   ```bash
   git checkout -b feature/amazing-feature
   ```
3. **Commit Your Changes**
   ```bash
   git commit -m 'Add amazing feature'
   ```
4. **Push to Branch**
   ```bash
   git push origin feature/amazing-feature
   ```
5. **Open a Pull Request**

### Development Workflow
1. **Issue Tracking**: Use GitHub Issues
2. **Code Review**: All changes require review
3. **Testing**: Write tests for new features
4. **Documentation**: Update relevant documentation

### Code Review Checklist
- [ ] Code follows project standards
- [ ] Tests pass
- [ ] Documentation updated
- [ ] Security considerations addressed
- [ ] Performance impact assessed

## 📄 License

This project is proprietary software developed for IECEP-LSC. All rights reserved.

### Usage Restrictions
- For internal IECEP-LSC use only
- No redistribution without permission
- No commercial use
- Attribution required for derivatives

### Third-Party Licenses
- **PHP Dependencies**: Various open-source licenses (see `composer.lock`)
- **JavaScript Libraries**: Various licenses (see `package-lock.json`)
- **Fonts**: Google Fonts (SIL Open Font License)
- **Icons**: Font Awesome (Free license)

## 📞 Support

### Getting Help
- **Documentation**: Check this README first
- **Issues**: GitHub Issues for bug reports
- **Email**: iecep.lsc@support.edu.ph
- **Discord**: IECEP-LSC Community Server

### System Requirements Updates
- **PHP**: Minimum 8.0, Recommended 8.2+
- **Database**: Supabase (PostgreSQL 14+)
- **Storage**: 1GB minimum, 10GB recommended
- **Bandwidth**: 100GB/month minimum

### Maintenance Schedule
- **Daily**: Backup verification, error log review
- **Weekly**: Security updates, performance monitoring
- **Monthly**: Full system audit, database optimization
- **Quarterly**: Major updates, security assessment

---

## 🎯 Quick Reference

### Default Admin Credentials
```
Email: admin@iecep-lsc.edu.ph
Password: Admin@2024
```

### Important URLs
- **Main Application**: `http://your-domain.com/IECEP-LSC-MEMSYS`
- **Admin Portal**: `http://your-domain.com/IECEP-LSC-MEMSYS/portal/admin`
- **API Documentation**: `http://your-domain.com/IECEP-LSC-MEMSYS/public/api/`
- **System Status**: `http://your-domain.com/IECEP-LSC-MEMSYS/diagnostic.php`

### Emergency Contacts
- **Technical Support**: tech-support@iecep-lsc.edu.ph
- **System Administrator**: sysadmin@iecep-lsc.edu.ph
- **Security Issues**: security@iecep-lsc.edu.ph

---

*Last Updated: May 2024*  
*System Version: 2.0.0*  
*Documentation Version: 1.0.0*

---

**IECEP-LSC MEMSYS** - Empowering Student Chapter Management through Technology
