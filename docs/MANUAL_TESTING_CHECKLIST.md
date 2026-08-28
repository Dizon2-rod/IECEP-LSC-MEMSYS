# IECEP-LSC MEMSYS Manual Testing Checklist

## Overview
This comprehensive testing checklist covers all features, roles, and edge cases for the IECEP-LSC Member Management System. Use this checklist to ensure system stability and functionality before deployment.

---

## 1. Authentication & Access Control

### 1.1 Login Functionality
- [ ] Verify login page loads correctly
- [ ] Test valid credentials (admin, school_officer, member)
- [ ] Test invalid credentials (wrong username)
- [ ] Test invalid credentials (wrong password)
- [ ] Test empty credentials
- [ ] Verify session persistence after login
- [ ] Test logout functionality
- [ ] Verify redirect to login after logout
- [ ] Test session timeout (if implemented)
- [ ] Verify "Remember Me" functionality (if implemented)

### 1.2 Role-Based Access Control
- [ ] Admin can access admin dashboard
- [ ] School Officer can access school officer dashboard
- [ ] Member can access member dashboard
- [ ] Admin cannot access member-specific pages
- [ ] Member cannot access admin pages
- [ ] School Officer cannot access admin pages
- [ ] Verify role-based sidebar navigation
- [ ] Test unauthorized access attempts (403 errors)

### 1.3 Password Management
- [ ] Test forced password change on first login
- [ ] Verify password requirements (8+ chars, uppercase, lowercase, number, special)
- [ ] Test password change functionality
- [ ] Verify password change link in member sidebar
- [ ] Test password strength validation
- [ ] Verify password confirmation matching

---

## 2. Member Profile Management

### 2.1 Profile Picture Upload (Enhancement 1)
- [ ] Verify profile picture upload modal appears
- [ ] Test valid image upload (JPG, PNG)
- [ ] Test file size validation (<2MB)
- [ ] Test invalid file type rejection
- [ ] Test oversized file rejection
- [ ] Verify picture displays correctly after upload
- [ ] Test picture update (replace existing)
- [ ] Verify Supabase Storage integration
- [ ] Test picture display in member profile
- [ ] Test picture display in member directory

### 2.2 Profile Information
- [ ] Verify profile page loads with member data
- [ ] Test profile information display
- [ ] Verify institution association display
- [ ] Test membership status display
- [ ] Verify contact information display
- [ ] Test digital ID display
- [ ] Verify payment status display

---

## 3. Event Management

### 3.1 Event Registration
- [ ] Verify event listing displays correctly
- [ ] Test event registration for upcoming events
- [ ] Verify registration confirmation
- [ ] Test duplicate registration prevention
- [ ] Verify registration status display
- [ ] Test registration for past events (should be blocked)
- [ ] Verify event details display
- [ ] Test event fee display
- [ ] Verify venue and date information

### 3.2 Event Registration History (Enhancement 2)
- [ ] Verify event history tab exists
- [ ] Test switching between upcoming and history tabs
- [ ] Verify attended events display in history
- [ ] Verify event details in history view
- [ ] Test registration status in history
- [ ] Verify date sorting in history
- [ ] Test empty history state

### 3.3 Attendance Certificate Download (Enhancement 3)
- [ ] Verify certificate download link appears for attended events
- [ ] Test certificate PDF generation
- [ ] Verify certificate contains correct member name
- [ ] Verify certificate contains event name
- [ ] Verify certificate contains date
- [ ] Test blockchain verification seal display
- [ ] Verify QR code generation on certificate
- [ ] Test certificate download functionality
- [ ] Verify certificate file format (PDF)
- [ ] Test certificate for multiple events

---

## 4. Compliance Management

### 4.1 Compliance Dashboard
- [ ] Verify compliance dashboard loads for admin
- [ ] Test institution compliance display
- [ ] Verify participation rate calculation
- [ ] Test compliance status badges
- [ ] Verify member count display
- [ ] Test hosted events count
- [ ] Verify last activity timestamp

### 4.2 Compliance Trend Chart (Enhancement 5)
- [ ] Verify trend chart displays on dashboard
- [ ] Test chart data loading
- [ ] Verify institution selection in chart
- [ ] Test year range display (last 3 years)
- [ ] Verify participation rate line chart
- [ ] Test chart interactivity (hover tooltips)
- [ ] Verify chart legend display
- [ ] Test chart responsiveness on mobile
- [ ] Verify chart color scheme matches design tokens

---

## 5. Survey & Feedback System

### 5.1 Survey Creation (Enhancement 6 - Admin)
- [ ] Verify survey creation page loads
- [ ] Test survey title input
- [ ] Test survey description input
- [ ] Test event association dropdown
- [ ] Test question addition
- [ ] Test question type selection (text, rating, yes/no)
- [ ] Test question removal
- [ ] Test multiple question addition
- [ ] Verify survey save functionality
- [ ] Test survey status (active/inactive)

### 5.2 Survey Submission (Enhancement 6 - Member)
- [ ] Verify available surveys display
- [ ] Test survey filtering by attended events
- [ ] Verify submitted survey status
- [ ] Test survey modal opening
- [ ] Test question display in modal
- [ ] Test text answer input
- [ ] Test rating selection (1-5)
- [ ] Test yes/no selection
- [ ] Verify survey submission
- [ ] Test duplicate submission prevention
- [ ] Verify submission confirmation

### 5.3 Survey Responses (Admin)
- [ ] Verify response viewing functionality
- [ ] Test response count display
- [ ] Verify individual response viewing
- [ ] Test response data display

---

## 6. Social Media Integration

### 6.1 Facebook Page Feed (Enhancement 7)
- [ ] Verify Facebook feed section on landing page
- [ ] Test Facebook SDK loading
- [ ] Verify page feed displays
- [ ] Test feed responsiveness
- [ ] Verify feed styling matches design
- [ ] Test feed on mobile devices
- [ ] Verify fallback if feed fails to load

---

## 7. Security Features

### 7.1 Two-Factor Authentication (Enhancement 8)
- [ ] Verify 2FA enable page loads for admin
- [ ] Test TOTP secret generation
- [ ] Verify QR code display
- [ ] Test manual code entry option
- [ ] Verify authenticator app instructions
- [ ] Test 2FA verification code submission
- [ ] Verify 2FA enable success
- [ ] Test 2FA disable functionality
- [ ] Verify 2FA verification page on login
- [ ] Test invalid 2FA code rejection
- [ ] Test valid 2FA code acceptance
- [ ] Verify 2FA session persistence

---

## 8. Automated Reports

### 8.1 Monthly Financial Report (Enhancement 9)
- [ ] Verify cron endpoint exists
- [ ] Test cron secret authentication
- [ ] Verify previous month data fetch
- [ ] Test transaction data aggregation
- [ ] Verify PDF generation
- [ ] Test email recipient configuration
- [ ] Verify email sending functionality
- [ ] Test PDF attachment in email
- [ ] Verify report content accuracy
- [ ] Test audit log creation
- [ ] Verify error handling for failed reports

---

## 9. Communication Tools

### 9.1 Newsletter Management (Enhancement 11)
- [ ] Verify newsletter creation page loads
- [ ] Test newsletter subject input
- [ ] Test HTML content input
- [ ] Test recipient filter selection (all, members, officers)
- [ ] Verify newsletter draft creation
- [ ] Test newsletter send functionality
- [ ] Verify email blast status update
- [ ] Test recipient count display
- [ ] Verify newsletter history display

### 9.2 Email Tracking (Enhancement 11)
- [ ] Verify tracking code generation
- [ ] Test tracking pixel insertion
- [ ] Verify email open tracking
- [ ] Test email click tracking
- [ ] Verify statistics display
- [ ] Test open rate calculation
- [ ] Test click rate calculation
- [ ] Verify tracking data persistence

---

## 10. User Interface

### 10.1 Dark Mode Toggle (Enhancement 12)
- [ ] Verify dark mode toggle button appears
- [ ] Test light mode to dark mode toggle
- [ ] Test dark mode to light mode toggle
- [ ] Verify theme persistence (localStorage)
- [ ] Test dark mode color scheme
- [ ] Verify design tokens update correctly
- [ ] Test dark mode on all pages
- [ ] Verify icon changes (moon/sun)
- [ ] Test dark mode on mobile
- [ ] Verify dark mode accessibility

---

## 11. Data Export

### 11.1 Member Directory Export (Enhancement 13)
- [ ] Verify export CSV button on admin member list
- [ ] Test CSV download functionality
- [ ] Verify CSV file naming (with date)
- [ ] Test CSV headers (Membership ID, Name, Email, etc.)
- [ ] Verify member data accuracy in CSV
- [ ] Test institution data inclusion
- [ ] Verify UTF-8 BOM for Excel compatibility
- [ ] Test CSV file opening in Excel
- [ ] Test CSV file opening in Google Sheets
- [ ] Verify empty data handling

---

## 12. Institution Affiliation

### 12.1 Affiliation Application
- [ ] Verify affiliation modal opens
- [ ] Test email verification step
- [ ] Verify 6-digit code generation
- [ ] Test code expiration (10 minutes)
- [ ] Test code validation
- [ ] Test invalid code rejection
- [ ] Verify application form step
- [ ] Test document upload (6 required documents)
- [ ] Test file validation (PDF, size limits)
- [ ] Verify form submission
- [ ] Test application status tracking

### 12.2 Institution Management (Admin)
- [ ] Verify institution listing
- [ ] Test institution approval
- [ ] Test institution rejection
- [ ] Verify institution status update
- [ ] Test institution details view
- [ ] Verify compliance status assignment

---

## 13. Financial Management

### 13.1 Transaction Processing
- [ ] Verify transaction creation
- [ ] Test payment status update
- [ ] Verify transaction history display
- [ ] Test transaction filtering
- [ ] Verify blockchain hash generation
- [ ] Test receipt generation

### 13.2 Financial Reports
- [ ] Verify financial dashboard loads
- [ ] Test income summary display
- [ ] Verify expense tracking
- [ ] Test date range filtering
- [ ] Verify report export functionality

---

## 14. System Administration

### 14.1 User Management
- [ ] Verify user listing
- [ ] Test user creation
- [ ] Test user role assignment
- [ ] Verify user deactivation
- [ ] Test user deletion
- [ ] Verify user search functionality

### 14.2 System Settings
- [ ] Verify system settings page loads
- [ ] Test email configuration update
- [ ] Test system URL configuration
- [ ] Verify setting persistence
- [ ] Test setting validation

---

## 15. Mobile Responsiveness

### 15.1 Landing Page
- [ ] Test landing page on mobile (320px+)
- [ ] Test landing page on tablet (768px+)
- [ ] Verify hero section responsiveness
- [ ] Test navigation menu on mobile
- [ ] Verify school logos display on mobile

### 15.2 Portal Pages
- [ ] Test admin dashboard on mobile
- [ ] Test member dashboard on mobile
- [ ] Test sidebar toggle on mobile
- [ ] Verify tables are scrollable on mobile
- [ ] Test forms on mobile

### 15.3 Forms
- [ ] Test affiliation form on mobile
- [ ] Test registration form on mobile
- [ ] Verify input field sizing
- [ ] Test button touch targets (48px minimum)

---

## 16. Browser Compatibility

### 16.1 Desktop Browsers
- [ ] Test on Google Chrome (latest)
- [ ] Test on Mozilla Firefox (latest)
- [ ] Test on Microsoft Edge (latest)
- [ ] Test on Safari (latest)

### 16.2 Mobile Browsers
- [ ] Test on Chrome Mobile (Android)
- [ ] Test on Safari Mobile (iOS)
- [ ] Test on Samsung Internet

---

## 17. Performance Testing

### 17.1 Page Load Times
- [ ] Verify landing page loads < 3 seconds
- [ ] Verify dashboard loads < 2 seconds
- [ ] Verify API response times < 500ms
- [ ] Test with 100+ members in database
- [ ] Test with 50+ events in database

### 17.2 Database Performance
- [ ] Test query performance for member listing
- [ ] Test query performance for compliance calculations
- [ ] Verify index usage on key columns
- [ ] Test concurrent user handling (10+ users)

---

## 18. Error Handling

### 18.1 User-Facing Errors
- [ ] Verify 404 page displays
- [ ] Verify 403 page displays
- [ ] Verify 500 error handling
- [ ] Test network error handling
- [ ] Verify timeout error handling

### 18.2 Form Validation Errors
- [ ] Test required field validation
- [ ] Test email format validation
- [ ] Test phone number validation
- [ ] Test file size validation
- [ ] Test file type validation

---

## 19. Security Testing

### 19.1 Input Validation
- [ ] Test SQL injection prevention
- [ ] Test XSS prevention
- [ ] Test CSRF protection
- [ ] Verify file upload validation
- [ ] Test path traversal prevention

### 19.2 Session Security
- [ ] Verify session fixation prevention
- [ ] Test session hijacking prevention
- [ ] Verify secure cookie flags
- [ ] Test session timeout

---

## 20. Accessibility Testing

### 20.1 WCAG Compliance
- [ ] Test keyboard navigation
- [ ] Verify screen reader compatibility
- [ ] Test color contrast ratios
- [ ] Verify alt text on images
- [ ] Test form labels
- [ ] Verify ARIA attributes
- [ ] Test focus indicators

---

## 21. Integration Testing

### 21.1 Supabase Integration
- [ ] Verify database connection
- [ ] Test real-time subscriptions
- [ ] Verify authentication flow
- [ ] Test storage operations
- [ ] Verify error handling

### 21.2 Email Service Integration
- [ ] Test email sending functionality
- [ ] Verify email template rendering
- [ ] Test attachment handling
- [ ] Verify email delivery

### 21.3 Blockchain Integration
- [ ] Verify transaction hash generation
- [ ] Test certificate verification
- [ ] Verify blockchain record creation

---

## 22. Edge Cases

### 22.1 Data Edge Cases
- [ ] Test with empty database
- [ ] Test with single member
- [ ] Test with 1000+ members
- [ ] Test with no events
- [ ] Test with past events only
- [ ] Test with future events only

### 22.2 User Behavior Edge Cases
- [ ] Test rapid form submissions
- [ ] Test browser back button navigation
- [ ] Test multiple tab usage
- [ ] Test session expiration during action
- [ ] Test concurrent edits

---

## 23. Regression Testing

### 23.1 Core Functionality
- [ ] Verify login still works after enhancements
- [ ] Verify member registration still works
- [ ] Verify event registration still works
- [ ] Verify compliance tracking still works
- [ ] Verify financial tracking still works

### 23.2 Enhancement-Specific Regression
- [ ] Verify profile picture doesn't break profile display
- [ ] Verify surveys don't break event registration
- [ ] Verify 2FA doesn't break login flow
- [ ] Verify dark mode doesn't break styling
- [ ] Verify newsletter doesn't break email service

---

## 24. Documentation Verification

### 24.1 User Documentation
- [ ] Verify user guide accuracy
- [ ] Test tutorial steps
- [ ] Verify screenshot accuracy
- [ ] Test FAQ answers

---

## 25. Enterprise Blockchain & Explorer Testing

### 25.1 Cryptographic Key Management & RSA-2048 Signatures
- [ ] Verify automatic RSA-2048 keypair generation in `storage/keys/`
- [ ] Test digital signing on block recording (`openssl_sign`)
- [ ] Test signature verification with chapter public key (`openssl_verify`)
- [ ] Test signature rejection when payload or hash is tampered

### 25.2 Affiliation Requirements & Document Anchoring
- [ ] Test SHA-256 calculation for all 6 required affiliation files
- [ ] Verify Merkle Tree Root calculation across multi-document submissions
- [ ] Test master affiliation block recording
- [ ] Verify receipt number anchoring on submission

### 25.3 Member State Push & Pull Engine
- [ ] Test member batch roster push & batch Merkle root generation
- [ ] Test `pullMemberHistory()` chronological state reconstruction
- [ ] Verify status progression tracking across blocks
- [ ] Test deterministic UUID conversion for non-standard student numbers

### 25.4 Blockchain Explorer Dashboard (`public/blockchain-explorer.php`)
- [ ] Verify page loads with live Block Height and 100% Chain Integrity badge
- [ ] Test multi-chain filter tabs (*Affiliations, Required Documents, Members, Batches, Receipts, Financial Audits, Compliance*)
- [ ] Test search bar filtering by Block Hash, Entity ID, or Type
- [ ] Test "Inspect Block" modal with formatted JSON payload viewer
- [ ] Test "Download Verifiable Cryptographic Proof (.json)" button
- [ ] Verify responsiveness and navigation link in Resources dropdown

### 25.5 Automated Test Suite Execution
- [ ] Run `php tools/test_blockchain_suite.php` and verify all tests pass

---

## Testing Summary

### Test Execution Log
- **Date:** _______________
- **Tester:** _______________
- **Environment:** [ ] Development [ ] Staging [ ] Production
- **Browser/Version:** _______________
- **OS:** _______________

### Results Summary
- **Total Tests:** ___
- **Passed:** ___
- **Failed:** ___
- **Blocked:** ___
- **N/A:** ___

### Critical Issues Found
1. 
2. 
3. 

### Recommendations
1. 
2. 
3. 

### Sign-off
- **Tester Signature:** _______________
- **Date:** _______________
- **Approved by:** _______________
- **Date:** _______________
