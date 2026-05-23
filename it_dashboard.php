<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'it_technician') {
    header("Location: login.html");
    exit();
}

require_once 'system_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>IT Dashboard</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
<ul class="app-nav">
<li class="active">Dashboard</li>
<li><a href="systemsettings.php">System Settings</a></li>
<li><a href="manage_accounts.php">Manage Accounts</a></li>
<li><a href="audit_log.php">Audit Log</a></li>
<li><a href="error_logs.php">Error Logs</a></li>
<li><a href="employee_profile.php">My Profile</a></li>
<li><a href="employee_salary.php">My Salary</a></li>
<li>
<form action="logout.php" method="post">
<button type="submit">Logout</button>
</form>
</li>
</ul>
    </aside>

    <main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">IT Dashboard</div>
                <div class="app-header-subtitle">Manage system settings, users, and technical operations.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'IT Technician') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Welcome back</h2>
                <p>Use the shortcuts below to manage system configuration and technical operations.</p>
            </div>

            <div class="cards-grid">
                <div class="quick-card" onclick="window.location.href='systemsettings.php'">
                    <div>System Settings</div>
                    <strong>Configure</strong>
                    <p>Control system on/off, registration windows, and policy settings.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='registeradmin.php'">
                    <div>Register Admin</div>
                    <strong>Manage</strong>
                    <p>Add and manage administrator accounts.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='registeremployee.php'">
                    <div>Employees</div>
                    <strong>Manage</strong>
                    <p>View and manage employee accounts.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='audit_log.php'">
                    <div>Audit Log</div>
                    <strong>View</strong>
                    <p>Track logins, registrations, payments, and all system activity</p>
                </div>
                <div class="quick-card" onclick="window.location.href='reports.php'">
                    <div>Reports</div>
                    <strong>Review</strong>
                    <p>View system and operational reports.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='error_logs.php'">
                    <div>Error Logs</div>
                    <strong>Debug</strong>
                    <p>View captured system errors and stack traces.</p>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
