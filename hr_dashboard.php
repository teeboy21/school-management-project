<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'hr_manager') {
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
<title>HR Dashboard</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
<ul class="app-nav">
<li class="active">Dashboard</li>
<li><a href="hr.php">HR & Payroll</a></li>
<li><a href="registeremployee.php">Employees</a></li>
<li><a href="viewTeacher.php">Teachers</a></li>
<li><a href="registerTeacher.php">Manage Teacher Accounts</a></li>
<li><a href="assignteacher.php">Assign Teachers</a></li>
<li><a href="salaries.php">Salaries</a></li>

<li><a href="reports.php">Reports</a></li>
<li><a href="view_ratings.php">Teacher Ratings</a></li>
<li><a href="pending_approvals.php">Approvals</a></li>
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
                <div class="app-header-title">HR Dashboard</div>
                <div class="app-header-subtitle">Manage staff, contracts, donations, and payroll.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'HR Manager') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Welcome back</h2>
                <p>Use the shortcuts below to manage human resources and related operations.</p>
            </div>

            <div class="cards-grid">
                <div class="quick-card" onclick="window.location.href='hr.php'">
                    <div>HR & Payroll</div>
                    <strong>Open</strong>
                    <p>Manage employee salaries, contracts, and payroll processing.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='registeremployee.php'">
                    <div>Employees</div>
                    <strong>Manage</strong>
                    <p>View, edit, and register non-teaching staff.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='viewTeacher.php'">
                    <div>Teachers</div>
                    <strong>Directory</strong>
                    <p>Browse and manage teaching staff records.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='registerTeacher.php'">
                    <div>Teacher Accounts</div>
                    <strong>Register</strong>
                    <p>Create and manage teacher user accounts.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='assignteacher.php'">
                    <div>Teacher Assignments</div>
                    <strong>Allocate</strong>
                    <p>Assign teachers to subjects and classes.</p>
                </div>

                <div class="quick-card" onclick="window.location.href='salaries.php'">
                    <div>Salaries</div>
                    <strong>Payroll</strong>
                    <p>Review and process monthly salary payments.</p>
                </div>

                <div class="quick-card" onclick="window.location.href='reports.php'">
                    <div>Reports</div>
                    <strong>Review</strong>
                    <p>View HR and financial reports.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='pending_approvals.php'">
                    <div>Pending Approvals</div>
                    <strong id="pendingApprovalCount" style="font-size:32px">...</strong>
                    <p>Review approval requests</p>
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
