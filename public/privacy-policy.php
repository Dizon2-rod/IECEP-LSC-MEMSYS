<?php
require_once __DIR__ . '/../bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — IECEP-LSC MEMSYS</title>
    <?php include __DIR__ . '/../includes/head-meta.php'; ?>
    <style>
        :root {
            --primary: #0B1D4A;
            --accent: #D4AF37;
        }
        body {
            background-color: #F8FAFC;
            font-family: 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1E293B;
            margin: 0;
            padding: 0;
        }
        .policy-wrapper {
            padding: 2.5rem 1rem 4rem;
            max-width: 920px;
            margin: 0 auto;
        }
        .policy-container {
            background: #FFFFFF;
            border-radius: 16px;
            padding: 3rem 2.5rem;
            border: 1px solid #E2E8F0;
            box-shadow: 0 4px 20px rgba(11, 29, 74, 0.05);
        }
        @media (max-width: 640px) {
            .policy-wrapper {
                padding: 1.5rem 0.75rem 3rem;
            }
            .policy-container {
                padding: 1.75rem 1.25rem;
                border-radius: 12px;
            }
        }
        .policy-header {
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.75rem;
            border-bottom: 2px solid #D4AF37;
        }
        .policy-header h1 {
            font-family: 'Times New Roman', Arial, serif;
            font-size: 2.2rem;
            font-weight: 800;
            color: #0B1D4A;
            margin: 0 0 0.5rem;
        }
        @media (max-width: 640px) {
            .policy-header h1 {
                font-size: 1.5rem;
            }
        }
        .policy-header p {
            color: #64748B;
            font-size: 0.95rem;
            margin: 0;
            line-height: 1.5;
        }
        .policy-section {
            margin-bottom: 2rem;
        }
        .policy-section h3 {
            color: #0B1D4A;
            font-size: 1.15rem;
            font-weight: 700;
            margin: 0 0 0.75rem;
        }
        .policy-section p {
            color: #475569;
            font-size: 0.92rem;
            line-height: 1.65;
            margin: 0 0 0.75rem;
        }
        .policy-section ul {
            padding-left: 1.5rem;
            margin: 0 0 0.75rem;
        }
        .policy-section li {
            color: #475569;
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 0.4rem;
        }
        .policy-section li strong {
            color: #0F172A;
        }
        .effective-date {
            text-align: center;
            color: #64748B;
            font-size: 0.85rem;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #E2E8F0;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
    
    <div class="policy-wrapper">
        <div class="policy-container">
            <div class="policy-header">
                <h1>Privacy Policy</h1>
                <p>Institute of Electronics Engineers of the Philippines – Laguna Student Chapter<br>Membership Management System (MEMSYS)</p>
            </div>
            
            <div class="policy-section">
                <h3>1. Introduction</h3>
                <p>IECEP-LSC MEMSYS ("we", "our", or "us") is committed to protecting the privacy and security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our Membership Management System, in compliance with the Data Privacy Act of 2012 (Republic Act No. 10173).</p>
            </div>
            
            <div class="policy-section">
                <h3>2. Information We Collect</h3>
                <p>We collect the following types of personal information:</p>
                <ul>
                    <li><strong>Personal Information:</strong> Full name, date of birth, contact details (email, phone number), address</li>
                    <li><strong>Professional Information:</strong> Student/employee ID, institution affiliation, position/title, membership details</li>
                    <li><strong>Academic Information:</strong> Course/program, year level, academic standing</li>
                    <li><strong>Financial Information:</strong> Payment records, transaction history, fee bracket classification</li>
                    <li><strong>Event Participation:</strong> Event attendance records, compliance scores, participation rates</li>
                    <li><strong>System Usage:</strong> Login history, IP address, device information, session data</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>3. Purpose of Collection</h3>
                <p>We collect and process your personal information for the following purposes:</p>
                <ul>
                    <li><strong>Membership Management:</strong> Processing applications, renewals, and maintaining membership records</li>
                    <li><strong>Compliance Monitoring:</strong> Tracking participation rates and compliance with IECEP-LSC Constitution</li>
                    <li><strong>Event Management:</strong> Managing event registrations, attendance, and certifications</li>
                    <li><strong>Financial Operations:</strong> Processing payments, generating invoices, and financial reporting</li>
                    <li><strong>Communication:</strong> Sending announcements, newsletters, and official correspondence</li>
                    <li><strong>System Security:</strong> Authenticating users and protecting system integrity</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>4. Legal Basis for Processing</h3>
                <p>We process your personal information based on:</p>
                <ul>
                    <li><strong>Consent:</strong> Your explicit consent when registering for an account or submitting affiliation documents</li>
                    <li><strong>Contractual Obligation:</strong> Fulfilling our commitments as a professional student organization</li>
                    <li><strong>Legitimate Interest:</strong> Operating, maintaining, and improving our services</li>
                    <li><strong>Legal Compliance:</strong> Meeting statutory requirements under Philippine law</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>5. Data Sharing and Disclosure</h3>
                <p>We do not sell your personal information. We may share your information with:</p>
                <ul>
                    <li><strong>Affiliated Institutions:</strong> School officers and advisers for institutional member verification</li>
                    <li><strong>IECEP National:</strong> For national membership synchronization and reporting</li>
                    <li><strong>Service Providers:</strong> Cloud infrastructure (Supabase, Railway) under strict data protection agreements</li>
                    <li><strong>Legal Authorities:</strong> When required by law, subpoena, or legal process</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>6. Data Security</h3>
                <p>We implement technical and organizational security measures to protect your data:</p>
                <ul>
                    <li>TLS/HTTPS encryption for all data in transit</li>
                    <li>AES-256 encryption for sensitive data at rest</li>
                    <li>Role-Based Access Control (RBAC) restricting data access to authorized personnel only</li>
                    <li>Regular security audits and automated vulnerability monitoring</li>
                    <li>Secure tokenized authentication and bcrypt password hashing</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>7. Data Retention</h3>
                <p>We retain personal information for as long as necessary to fulfill the purposes outlined in this policy or as required by applicable laws:</p>
                <ul>
                    <li><strong>Active Members:</strong> Maintained throughout active membership duration</li>
                    <li><strong>Inactive/Alumni:</strong> Retained for historical academic record verification</li>
                    <li><strong>Financial Records:</strong> Retained for 5 years in compliance with auditing standards</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>8. Your Rights</h3>
                <p>Under the Data Privacy Act of 2012, you have the right to:</p>
                <ul>
                    <li><strong>Be Informed:</strong> Know how your personal data is collected and processed</li>
                    <li><strong>Access:</strong> Request a copy of your personal information</li>
                    <li><strong>Rectification:</strong> Request correction of inaccurate or outdated data</li>
                    <li><strong>Erasure/Blocking:</strong> Request deletion of data subject to statutory limits</li>
                    <li><strong>Data Portability:</strong> Obtain your data in an electronic format</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>9. Contact Information</h3>
                <p>If you have questions about this Privacy Policy or our data practices, please contact:</p>
                <ul>
                    <li><strong>Official Email:</strong> ieceplsc24@gmail.com</li>
                    <li><strong>Facebook Page:</strong> facebook.com/IECEPLSC</li>
                    <li><strong>Organization:</strong> IECEP Laguna Student Chapter</li>
                </ul>
            </div>
            
            <div class="effective-date">
                <p>This Privacy Policy is effective as of January 1, 2026. Last updated: August 2026.</p>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/../includes/footer-new.php'; ?>
</body>
</html>
