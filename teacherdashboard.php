<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
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
<title>Teacher Dashboard</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
<ul class="app-nav">
<li class="active">Dashboard</li>
<li><a href="myclassesteacher.php">My Classes</a></li>
<li><a href="mystudentsteacher.php">Students</a></li>
<li><a href="marks.php">Marks</a></li>
<li><a href="assignments.php">Assignments</a></li>
<li><a href="results.php">Student Results</a></li>
<li><a href="teacher_salary.php">Salary</a></li>
<li><a href="teacherprofile.php">Profile</a></li>
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
                <div class="app-header-title">Teacher Dashboard</div>
                <div class="app-header-subtitle">Manage classes, marks, and student progress from one place.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Teacher') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['employee_number'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Welcome back</h2>
                <p>Use the shortcuts below to continue with the most common teacher tasks.</p>
            </div>

            <div class="cards-grid">
                <div class="quick-card" onclick="window.location.href='myclassesteacher.php'">
                    <div>Assigned Classes</div>
                    <strong>Open</strong>
                    <p>See the classes and subjects currently assigned to you.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='mystudentsteacher.php'">
                    <div>My Students</div>
                    <strong>Browse</strong>
                    <p>View the students connected to your approved subjects.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='marks.php'">
                    <div>Marks</div>
                    <strong>Capture</strong>
                    <p>Enter or update marks with the corrected mark entry flow.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='teacherprofile.php'">
                    <div>Profile</div>
                    <strong>Review</strong>
                    <p>Check your email, employee number, and teaching allocations.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='assignments.php'">
                    <div>Assignments</div>
                    <strong>Track</strong>
                    <p>Open the assignment workspace prepared for students.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='results.php'">
                    <div>Student Results</div>
                    <strong>Follow Up</strong>
                    <p>Review the student-facing results flow after marks are captured.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='mystudentsteacher.php'">
                    <div>Student Support</div>
                    <strong>Review</strong>
                    <p>Look up learners quickly while checking subject-linked rosters.</p>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
