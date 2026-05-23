<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.html");
    exit();
}

$total_students = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM students"))['total'] ?? 0);
$pending_students = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS pending FROM user WHERE status = 'profile_completed'"))['pending'] ?? 0);
$total_teachers = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS totalteachers FROM teachers"))['totalteachers'] ?? 0);
$total_employees = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS totalemployees FROM employees"))['totalemployees'] ?? 0);
$total_classes = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS totalclasses FROM classes"))['totalclasses'] ?? 0);
$total_subjects = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS totalsubjects FROM subjects"))['totalsubjects'] ?? 0);
$pending_subjects = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS pending_subjects FROM student_subject WHERE status = 'pending'"))['pending_subjects'] ?? 0);
$teacher_assignments = (int) (mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total_assignments FROM teacher_subject"))['total_assignments'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
        <ul class="app-nav">
            <li class="active">Dashboard</li>
            <li><a href="student.php">Students</a></li>
            <li><a href="adminaprove.php">Accounts Approvals</a></li>
            <li><a href="enroll.php">Register Student</a></li>
            <li><a href="approvesubject.php">Approve Subjects</a></li>
            <li><a href="projects.php">Development Projects</a></li>
            <li><a href="events.php">Events</a></li>
            <li><a href="reports.php">Reports</a></li>
            <li><a href="registeradmin.php">Register Admin</a></li>
            <li><a href="manage_accounts.php">Manage Accounts</a></li>
<li><a href="audit_log.php">Audit Log</a></li>
<li><a href="error_logs.php">Error Logs</a></li>
            <li><a href="school_settings.php">School Settings</a></li>
            <li><a href="systemsettings.php">System Settings</a></li>
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
                <div class="app-header-title">Admin Dashboard</div>
                <div class="app-header-subtitle">Run approvals, registrations, assignments, and academic setup from a single workspace.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Operations Overview</h2>
                <p>The dashboard now surfaces the next actions the admin needs to take instead of only showing generic cards.</p>
            </div>

            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card"><div>Total Students</div><strong><?= $total_students ?></strong></div>
                <div class="stat-card"><div>Pending Student Approvals</div><strong><?= $pending_students ?></strong></div>
                <div class="stat-card"><div>Total Teachers</div><strong><?= $total_teachers ?></strong></div>
                <div class="stat-card"><div>Classes</div><strong><?= $total_classes ?></strong></div>
                <div class="stat-card"><div>Subjects</div><strong><?= $total_subjects ?></strong></div>
                <div class="stat-card"><div>Pending Subject Approvals</div><strong><?= $pending_subjects ?></strong></div>
                <div class="stat-card"><div>Teacher Assignments</div><strong><?= $teacher_assignments ?></strong></div>
                <div class="stat-card"><div>Employees</div><strong><?= $total_employees ?></strong></div>
            </div>

            <div class="cards-grid">
                <div class="quick-card" onclick="window.location.href='adminaprove.php'">
                    <div>Approval Queue</div>
                    <strong><?= $pending_students ?></strong>
                    <p>Review students waiting for class assignment and approval.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='approvesubject.php'">
                    <div>Subject Requests</div>
                    <strong><?= $pending_subjects ?></strong>
                    <p>Approve or reject students' selected subjects.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='enroll.php'">
                    <div>Student Registration</div>
                    <strong>New</strong>
                    <p>Register a student account and admission profile directly.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='student.php'">
                    <div>Student Directory</div>
                    <strong><?= $total_students ?></strong>
                    <p>Review, edit, or remove student records.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='events.php'">
                    <div>Events</div>
                    <strong>Plan</strong>
                    <p>Use the events workspace for future calendar and notices management.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='reports.php'">
                    <div>Reports</div>
                    <strong>Review</strong>
                    <p>Jump to report and export pages quickly.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='audit_log.php'">
                    <div>Audit Log</div>
                    <strong>View</strong>
                    <p>Track logins, registrations, payments, and all system activity</p>
                </div>
                <div class="quick-card" onclick="window.location.href='error_logs.php'">
                    <div>Error Logs</div>
                    <strong>Debug</strong>
                    <p>View captured system errors and stack traces.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='school_settings.php'">
                    <div>School Settings</div>
                    <strong>Edit</strong>
                    <p>Manage school name, contact info, hours, terms and policies.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='systemsettings.php'">
                    <div>System Settings</div>
                    <strong>Configure</strong>
                    <p>Control system on/off, registration windows, modules, and policy settings.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='registeradmin.php'">
                    <div>Register Admin</div>
                    <strong>Prepare</strong>
                    <p>Add another administrator once your admin role tables are ready.</p>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
