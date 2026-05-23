<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'principal') {
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
<title>Principal Dashboard</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
<ul class="app-nav">
<li class="active">Dashboard</li>
<li><a href="student.php">Students</a></li>
<li><a href="viewTeacher.php">Teachers</a></li>
<li><a href="registeremployee.php">Employees</a></li>
<li><a href="acedemics.php">Academics</a></li>
<li><a href="finance.php">Finance</a></li>
<li><a href="financial_reports.php">Financial Reports</a></li>
<li><a href="reports.php">Reports</a></li>
<li><a href="adminaprove.php">Account Approvals</a></li>
<li><a href="pending_approvals.php">Approvals</a></li>
<li><a href="view_ratings.php">Teacher Ratings</a></li>
<li><a href="events.php">Events</a></li>
<li><a href="projects.php">Projects</a></li>
<li><a href="school_settings.php">School Settings</a></li>
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
                <div class="app-header-title">Principal Dashboard</div>
                <div class="app-header-subtitle">School-wide overview of academics, staff, finance, and operations.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Principal') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Welcome back</h2>
                <p>High-level overview of the school's key areas.</p>
            </div>

            <div class="cards-grid">
                <div class="quick-card" onclick="window.location.href='student.php'">
                    <div>Students</div>
                    <strong>Browse</strong>
                    <p>View the student directory and records.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='viewTeacher.php'">
                    <div>Teachers</div>
                    <strong>Browse</strong>
                    <p>View teacher profiles and assignments.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='acedemics.php'">
                    <div>Academics</div>
                    <strong>Review</strong>
                    <p>Monitor grades, classes, and subject offerings.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='finance.php'">
                    <div>Finance</div>
                    <strong>Review</strong>
                    <p>Oversee school finances and fee collection.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='financial_reports.php'">
                    <div>Financial Reports</div>
                    <strong>Reports</strong>
                    <p>View monthly and yearly financial summaries.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='hr.php'">
                    <div>HR & Payroll</div>
                    <strong>Overview</strong>
                    <p>Monitor staff salaries, contracts, and payroll.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='events.php'">
                    <div>Events</div>
                    <strong>Plan</strong>
                    <p>View and manage school events and calendar.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='projects.php'">
                    <div>Projects</div>
                    <strong>Track</strong>
                    <p>Monitor development projects and milestones.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='pending_approvals.php'">
                    <div>Pending Approvals</div>
                    <strong id="pendingApprovalCount" style="font-size:32px">...</strong>
                    <p>Review expense, budget, and contract approvals</p>
                </div>
                <div class="quick-card" onclick="window.location.href='school_settings.php'">
                    <div>School Settings</div>
                    <strong>Edit</strong>
                    <p>Manage school name, contact, hours, terms and policies.</p>
                </div>

            </div>
        </section>
        <script>
        fetch('API/approval_api.php?action=count_pending').then(r=>r.json()).then(d => {
            document.getElementById('pendingApprovalCount').textContent = d.count || 0;
        }).catch(() => { document.getElementById('pendingApprovalCount').textContent = '0'; });
        </script>
    </main>
</div>
</body>
</html>
