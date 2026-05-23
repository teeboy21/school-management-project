<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}

require_once 'system_check.php';

$user_id = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'results_visible_to_students'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$results_visible = ($row && $row['setting_value'] === 'visible');

$results = $conn->query("
    SELECT
        sub.subject_name,
        m.mark
    FROM students s
    JOIN student_subject ss ON ss.student_id = s.user_id
    JOIN subjects sub ON sub.id = ss.subject_id
    LEFT JOIN marks m ON m.student_id = s.id AND m.subject_id = sub.id
    WHERE s.user_id = $user_id AND ss.status = 'approved'
    ORDER BY sub.subject_name
");

$marks = [];
$total = 0;
$count = 0;
while ($row = $results->fetch_assoc()) {
    $marks[] = $row;
    if ($row['mark'] !== null) {
        $total += (int) $row['mark'];
        $count++;
    }
}
$average = $count > 0 ? round($total / $count, 1) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Results</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="userdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">My Results</div>
                <div class="app-header-subtitle">View marks that have already been captured for your approved subjects.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <?php if (!$results_visible): ?>
                <div class="hero-card" style="background:#fee2e2;border-color:#ef4444;">
                    <h2>Results Hidden</h2>
                    <p>Results are currently not available yet. Please check back later.</p>
                </div>
            <?php else: ?>
            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card">
                    <div>Subjects With Results</div>
                    <strong><?= count($marks) ?></strong>
                </div>
                <div class="stat-card">
                    <div>Average Mark</div>
                    <strong><?= $average !== null ? $average . '%' : 'N/A' ?></strong>
                </div>
            </div>
            <div class="panel">
                <h2>Published Marks</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Mark</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($marks)): ?>
                                <?php foreach ($marks as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['subject_name']) ?></td>
                                        <td><?= $row['mark'] !== null ? htmlspecialchars((string) $row['mark']) . '%' : 'Not entered yet' ?></td>
                                        <td><span class="badge"><?= $row['mark'] !== null ? 'Available' : 'Pending' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="3" class="empty-state">No approved subject results found yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
