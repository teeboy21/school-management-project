<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}

require_once 'system_check.php';

$user_id = (int) $_SESSION['user_id'];
$query = "
    SELECT COUNT(sub.subject_name) AS total
    FROM student_subject ss
    JOIN subjects sub ON ss.subject_id = sub.id
    WHERE ss.student_id = $user_id AND ss.status = 'approved'
";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$total_subjects = $row['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
<ul class="app-nav">
<li class="active">Dashboard</li>
<li><a href="studentsubject.php">My Subjects</a></li>
<li><a href="assignments.php">Assignments</a></li>
<li><a href="results.php">Results</a></li>
<li><a href="fees.php">My Fees</a></li>
<li><a href="userprofile.php">Profile</a></li>
<li><a href="selectsubject.php">Select Subjects</a></li>
<li><a href="teacher_ratings.php">Rate Teachers</a></li>
<li><a href="events.php">Events</a></li>
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
                <div class="app-header-title">Student Dashboard</div>
                <div class="app-header-subtitle">Track subjects, results, and academic actions from a cleaner workspace.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['student_number'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Overview</h2>
                <p>Your dashboard is now simplified around the actions students use most often.</p>
            </div>

            <div class="cards-grid">
                <div class="stat-card">
                    <div>Approved Subjects</div>
                    <strong><?= (int) $total_subjects ?></strong>
                </div>
                <div class="quick-card" onclick="window.location.href='studentsubject.php'">
                    <div>Subjects</div>
                    <strong>Review</strong>
                    <p>See approved and pending subjects in one place.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='selectsubject.php'">
                    <div>Select Subjects</div>
                    <strong>Update</strong>
                    <p>Choose compulsory and optional subjects with a friendlier form.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='results.php'">
                    <div>Results</div>
                    <strong>Check</strong>
                    <p>Review published marks and academic performance.</p>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
