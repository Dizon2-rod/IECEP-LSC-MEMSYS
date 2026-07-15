<?php
require_once __DIR__ . '/includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - IECEP-LSC MEMSYS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/css/design-tokens.css">
    <style>
        :root {
            --primary-color: #0B1D4A;
            --secondary-color: #C49A00;
        }
        
        body {
            background-color: #f8fafc;
        }
        
        .policy-container {
            background: white;
            border-radius: 12px;
            padding: 3rem;
            margin: 2rem auto;
            max-width: 900px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .policy-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid var(--secondary-color);
        }
        
        .policy-section {
            margin-bottom: 2rem;
        }
        
        .policy-section h3 {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .policy-section ul {
            padding-left: 1.5rem;
        }
        
        .policy-section li {
            margin-bottom: 0.5rem;
        }
        
        .effective-date {
            text-align: center;
            color: #64748b;
            font-style: italic;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <div class="container">
        <div class="policy-container">
            <div class="policy-header">
                <h1>Privacy Policy</h1>
                <p class="text-muted">Institute of Electronics Engineers of the Philippines – Laguna State Chapter<br>Membership Management System</p>
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
                    <li><strong>Academic Information:</strong> Course/program, year level, academic performance (if applicable)</li>
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
                    <li><strong>Communication:</strong> Sending notifications, announcements, and relevant updates</li>
                    <li><strong>Blockchain Verification:</strong> Recording immutable audit trails for transparency and accountability</li>
                    <li><strong>System Security:</strong> Preventing fraud, ensuring data integrity, and maintaining system security</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>4. Data Sharing and Disclosure</h3>
                <p>We may share your personal information with:</p>
                <ul>
                    <li><strong>IECEP-LSC Officers:</strong> Authorized officers for membership verification and compliance monitoring</li>
                    <li><strong>Institution Representatives:</strong> School officers for institutional compliance tracking</li>
                    <li><strong>Registration Committee:</strong> For affiliation application review and approval</li>
                    <li><strong>Treasurer:</strong> For financial transaction processing and fee management</li>
                    <li><strong>Authorized Third Parties:</strong> Service providers (email services, payment processors) under strict confidentiality agreements</li>
                    <li><strong>Legal Authorities:</strong> When required by law or court order</li>
                </ul>
                <p>We do not sell, rent, or trade your personal information with third parties for marketing purposes.</p>
            </div>
            
            <div class="policy-section">
                <h3>5. Data Security</h3>
                <p>We implement appropriate security measures to protect your information:</p>
                <ul>
                    <li><strong>Encryption:</strong> Data is encrypted in transit and at rest</li>
                    <li><strong>Blockchain Logging:</strong> Critical actions are recorded on an immutable blockchain for audit trails</li>
                    <li><strong>Access Controls:</strong> Role-based access restrictions limit data access to authorized personnel</li>
                    <li><strong>Secure Authentication:</strong> Multi-factor authentication and secure password policies</li>
                    <li><strong>Regular Audits:</strong> Periodic security audits and vulnerability assessments</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>6. Your Rights Under RA 10173</h3>
                <p>Under the Data Privacy Act, you have the right to:</p>
                <ul>
                    <li><strong>Right to Access:</strong> Request access to your personal information we hold</li>
                    <li><strong>Right to Correct:</strong> Request correction of inaccurate or incomplete information</li>
                    <li><strong>Right to Erasure:</strong> Request deletion of your information (subject to legal and operational requirements)</li>
                    <li><strong>Right to Object:</strong> Object to processing of your information for certain purposes</li>
                    <li><strong>Right to File a Complaint:</strong> File a complaint with the National Privacy Commission</li>
                    <li><strong>Right to Data Portability:</strong> Request a copy of your information in a structured format</li>
                </ul>
                <p>To exercise these rights, contact our Data Protection Officer at privacy@iecep-lsc.org</p>
            </div>
            
            <div class="policy-section">
                <h3>7. Data Retention</h3>
                <p>We retain your personal information for as long as necessary for the purposes outlined in this policy, or as required by law. Specifically:</p>
                <ul>
                    <li><strong>Active Members:</strong> Information retained while membership is active</li>
                    <li><strong>Former Members:</strong> Basic information retained for 7 years after membership expiration</li>
                    <li><strong>Financial Records:</strong> Retained for 10 years as required by tax laws</li>
                    <li><strong>Blockchain Records:</strong> Immutable records retained indefinitely for audit purposes</li>
                    <li><strong>System Logs:</strong> Retained for 1 year for security monitoring</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>8. Cookies and Tracking</h3>
                <p>We use cookies and similar technologies to:</p>
                <ul>
                    <li>Maintain your session and authentication state</li>
                    <li>Remember your preferences and settings</li>
                    <li>Analyze system usage and improve performance</li>
                    <li>Provide personalized content and notifications</li>
                </ul>
                <p>You can manage cookie preferences through your browser settings.</p>
            </div>
            
            <div class="policy-section">
                <h3>9. Children's Privacy</h3>
                <p>Our services are intended for use by individuals 18 years and older. We do not knowingly collect personal information from minors. If we discover that we have collected information from a minor, we will take steps to delete it.</p>
            </div>
            
            <div class="policy-section">
                <h3>10. Changes to This Policy</h3>
                <p>We may update this Privacy Policy from time to time. We will notify you of significant changes by:</p>
                <ul>
                    <li>Posting the updated policy on our website</li>
                    <li>Sending email notifications to registered members</li>
                    <li>Displaying prominent notices within the system</li>
                </ul>
            </div>
            
            <div class="policy-section">
                <h3>11. Contact Information</h3>
                <p>If you have questions about this Privacy Policy or our data practices, please contact:</p>
                <ul>
                    <li><strong>Data Protection Officer:</strong> privacy@iecep-lsc.org</li>
                    <li><strong>IECEP-LSC Office:</strong> info@iecep-lsc.org</li>
                    <li><strong>National Privacy Commission:</strong> info@npc.gov.ph</li>
                </ul>
            </div>
            
            <div class="effective-date">
                <p>This Privacy Policy is effective as of January 1, 2026.</p>
                <p>Last updated: January 1, 2026</p>
            </div>
        </div>
    </div>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
