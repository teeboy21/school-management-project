<?php
session_start();
include 'config.php';
$role = $_SESSION['role'] ?? '';
$dash_map = [
    'admin' => 'admindashboard.php',
    'principal' => 'principal_dashboard.php',
    'teacher' => 'teacherdashboard.php',
    'student' => 'userdashboard.php',
    'finance_manager' => 'finance_dashboard.php',
    'hr_manager' => 'hr_dashboard.php',
    'it_technician' => 'it_dashboard.php',
];
$dashboard_url = $dash_map[$role] ?? 'login.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terms and Conditions</title>
<link rel="stylesheet" href="internal.css">
<style>
.terms-content { max-width: 800px; margin: 0 auto; line-height: 1.7; }
.terms-content h2 { margin-top: 30px; font-size: 18px; }
.terms-content p { margin: 10px 0; color: var(--app-muted); font-size: 14px; }
.terms-content ul { margin: 10px 0; padding-left: 20px; color: var(--app-muted); font-size: 14px; }
.terms-content ul li { margin-bottom: 6px; }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Back</a>
<div class="app-header-meta">
<div class="app-header-title">Terms and Conditions</div>
<div class="app-header-subtitle">Acceptable use policy for the school management system</div>
</div>
</header>
<section class="app-content">
<div class="panel terms-content">

<h2>1. Acceptance of Terms</h2>
<p>By accessing and using this School Management System ("the System"), you agree to be bound by these Terms and Conditions. If you do not agree with any part of these terms, you must not use the System.</p>

<h2>2. User Accounts and Security</h2>
<ul>
<li>You are responsible for maintaining the confidentiality of your login credentials.</li>
<li>You must not share your account password with anyone else.</li>
<li>You must notify the school administration immediately if you suspect unauthorised use of your account.</li>
<li>The school reserves the right to disable any user account without prior notice if a security breach is suspected.</li>
<li>Each user may hold only one active account unless explicitly authorised otherwise.</li>
</ul>

<h2>3. Acceptable Use</h2>
<ul>
<li>The System is to be used only for legitimate educational and administrative purposes.</li>
<li>Users must not attempt to access, modify, or delete data belonging to other users without proper authorization.</li>
<li>Users must not introduce malicious software, attempt to breach security, or disrupt System operations.</li>
<li>Teachers and staff must ensure that all marks, comments, and records entered are accurate and appropriate.</li>
<li>Students must use the System in a manner consistent with the school's code of conduct.</li>
</ul>

<h2>4. Data Privacy</h2>
<ul>
<li>The school collects and processes personal data (including names, contact details, academic records) in accordance with applicable data protection laws.</li>
<li>Personal data will not be shared with third parties except as required by law or with explicit consent.</li>
<li>Users may request access to their personal data held in the System by contacting the school administration.</li>
<li>Academic records are retained for the duration required by the school's records retention policy.</li>
</ul>

<h2>5. Academic Records and Marks</h2>
<ul>
<li>Marks and academic records entered into the System are considered official school records.</li>
<li>Teachers must verify the accuracy of marks before final submission.</li>
<li>Once published, marks may only be amended through the school's formal review process.</li>
<li>Assignment submissions are the original work of the student unless explicitly stated otherwise.</li>
</ul>

<h2>6. Financial Transactions</h2>
<ul>
<li>All fee payments and financial transactions recorded in the System are subject to the school's fee policy.</li>
<li>Payment records serve as official receipts for accounting purposes.</li>
<li>Users must not attempt to manipulate or falsify financial records.</li>
</ul>

<h2>7. Limitation of Liability</h2>
<p>The school provides the System on an "as is" basis. While every effort is made to ensure availability and accuracy, the school is not liable for:</p>
<ul>
<li>Temporary service interruptions or downtime.</li>
<li>Data loss resulting from circumstances beyond the school's reasonable control.</li>
<li>Indirect or consequential damages arising from System use.</li>
</ul>

<h2>8. Changes to Terms</h2>
<p>The school reserves the right to modify these Terms and Conditions at any time. Users will be notified of material changes via the System or email. Continued use after changes constitutes acceptance of the revised terms.</p>

<h2>9. Governing Law</h2>
<p>These Terms and Conditions are governed by the laws of the Republic of South Africa. Any disputes arising from System use shall be subject to the jurisdiction of the appropriate South African courts.</p>

<h2>10. Contact</h2>
<p>For questions about these terms or to report a violation, please contact the school administration at the details provided on the school's official communication channels.</p>

</div>
</section>
</main>
</div>
</body>
</html>
