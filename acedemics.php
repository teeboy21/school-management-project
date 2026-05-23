<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Academics</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Academics</div>
                <div class="app-header-subtitle">Academic setup now has a proper home with direct links to grades, classes, subjects, and approvals.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="cards-grid">
                <div class="quick-card" onclick="window.location.href='managegrades.php'">
                    <div>Grades</div>
                    <strong>Manage</strong>
                    <p>Create and update grade levels used across the system.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='manageclasses.php'">
                    <div>Classes</div>
                    <strong>Manage</strong>
                    <p>Control grade-to-class combinations.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='managesubjects.php'">
                    <div>Subjects</div>
                    <strong>Manage</strong>
                    <p>Organize subjects by grade and compulsory status.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='approvesubject.php'">
                    <div>Subject Approvals</div>
                    <strong>Review</strong>
                    <p>Approve or reject student subject choices.</p>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
