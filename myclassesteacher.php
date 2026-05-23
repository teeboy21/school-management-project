<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: login.php");
    exit();
}

$teacher_id = (int) $_SESSION['user_id'];

$assignments = $conn->prepare("
    SELECT DISTINCT
        g.name AS grade,
        c.class_name,
        s.subject_name
    FROM teacher_subject ts
    JOIN classes c ON ts.class_id = c.id
    JOIN grades g ON c.grade_id = g.id
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.teacher_id = ?
    ORDER BY g.name, c.class_name, s.subject_name
");
$assignments->bind_param("i", $teacher_id);
$assignments->execute();
$result = $assignments->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Classes</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="teacherdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">My Classes</div>
                <div class="app-header-subtitle">See every class and subject currently assigned to you.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Teacher') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel">
                <h2>Assignments</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>Class</th>
                                <th>Subject</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['grade']) ?></td>
                                        <td><?= htmlspecialchars($row['class_name']) ?></td>
                                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty-state">No class assignments found yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
