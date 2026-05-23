<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$student = $conn->query("
    SELECT u.id, s.fullname, c.class_name, g.name AS grade
    FROM user u
    JOIN students s ON u.id = s.user_id
    JOIN classes c ON s.class_id = c.id
    JOIN grades g ON c.grade_id = g.id
    WHERE u.id = $user_id
")->fetch_assoc();

$subjects = $conn->query("
    SELECT sub.subject_name
    FROM student_subject ss
    JOIN subjects sub ON ss.subject_id = sub.id
    WHERE ss.student_id = $user_id AND ss.status = 'approved'
");

$pending = $conn->query("
    SELECT *
    FROM student_subject
    WHERE student_id = $user_id AND status = 'pending'
")->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Subjects</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="userdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">My Subjects</div>
                <div class="app-header-subtitle">View approved subjects and track whether your latest choices are still pending approval.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></h2>
                <p><strong>Class:</strong> <?= htmlspecialchars(($student['grade'] ?? '') . ' ' . ($student['class_name'] ?? '')) ?></p>
            </div>

            <?php if ($pending > 0): ?>
                <div class="alert alert-warning">Your latest subject request is still waiting for approval.</div>
            <?php else: ?>
                <div class="alert alert-info">No pending subject approval at the moment.</div>
            <?php endif; ?>

            <div class="panel">
                <h2>Approved Subjects</h2>
                <?php if ($subjects->num_rows > 0): ?>
                    <?php while ($s = $subjects->fetch_assoc()): ?>
                        <span class="badge"><?= htmlspecialchars($s['subject_name']) ?></span>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No approved subjects yet.</div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>
</body>
</html>
