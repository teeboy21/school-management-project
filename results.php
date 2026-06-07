<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

require_once 'system_check.php';

$role = $_SESSION['role'] ?? '';
$user_id = (int) $_SESSION['user_id'];

$is_teacher = ($role === 'teacher');
$is_student = ($role === 'student');

if (!$is_teacher && !$is_student) {
    header("Location: login.html");
    exit();
}

// ----- Student view -----
if ($is_student) {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'results_visible_to_students'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $results_visible = ($row && $row['setting_value'] === 'visible');

    $results = $conn->query("
        SELECT
            sub.subject_name,
            m.mark,
            (
                SELECT ROUND(AVG(as2.score), 1)
                FROM assignment_submissions as2
                JOIN assignments a2 ON as2.assignment_id = a2.id
                WHERE as2.student_id = s.user_id AND a2.subject_id = sub.id AND as2.score IS NOT NULL
            ) AS assignment_avg
        FROM students s
        JOIN student_subject ss ON ss.student_id = s.user_id
        JOIN subjects sub ON sub.id = ss.subject_id
        LEFT JOIN marks m ON m.student_id = s.id AND m.subject_id = sub.id
        WHERE s.user_id = $user_id AND ss.status = 'approved'
        ORDER BY sub.subject_name
    ");

    $marks = [];
    $exam_total = 0; $exam_count = 0; $assign_total = 0; $assign_count = 0;
    while ($row = $results->fetch_assoc()) {
        $marks[] = $row;
        if ($row['mark'] !== null) { $exam_total += (int) $row['mark']; $exam_count++; }
        if ($row['assignment_avg'] !== null) { $assign_total += (float) $row['assignment_avg']; $assign_count++; }
    }
    $exam_average = $exam_count > 0 ? round($exam_total / $exam_count, 1) : null;
    $assign_average = $assign_count > 0 ? round($assign_total / $assign_count, 1) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Results</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="userdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">My Results</div>
<div class="app-header-subtitle">View marks for your approved subjects.</div>
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
<div class="stat-card"><div>Subjects</div><strong><?= count($marks) ?></strong></div>
<div class="stat-card"><div>Exam Average</div><strong><?= $exam_average !== null ? $exam_average . '%' : 'N/A' ?></strong></div>
<div class="stat-card"><div>Assignment Average</div><strong><?= $assign_average !== null ? $assign_average . '%' : 'N/A' ?></strong></div>
</div>
<div class="panel">
<h2>Published Results</h2>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Subject</th><th>Exam Mark</th><th>Assignment Avg</th><th>Overall</th></tr></thead>
<tbody>
<?php if (!empty($marks)): ?>
<?php foreach ($marks as $row): ?>
<?php
$exam = $row['mark'] !== null ? (int) $row['mark'] : null;
$assign = $row['assignment_avg'] !== null ? (float) $row['assignment_avg'] : null;
if ($exam !== null && $assign !== null) $overall = round(($exam + $assign) / 2, 1);
elseif ($exam !== null) $overall = (float) $exam;
elseif ($assign !== null) $overall = $assign;
else $overall = null;
?>
<tr>
<td><?= htmlspecialchars($row['subject_name']) ?></td>
<td><?= $exam !== null ? $exam . '%' : '<span class="text-muted">—</span>' ?></td>
<td><?= $assign !== null ? $assign . '%' : '<span class="text-muted">—</span>' ?></td>
<td><strong><?= $overall !== null ? $overall . '%' : '<span class="text-muted">—</span>' ?></strong></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="4" class="empty-state">No approved subject results found yet.</td></tr>
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

<?php exit; } // end student view ?>

<?php
// ----- Teacher view -----
$selected_subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;

$subjects = $conn->prepare("
    SELECT DISTINCT s.id, s.subject_name, g.name AS grade_name
    FROM teacher_subject ts
    JOIN subjects s ON ts.subject_id = s.id
    JOIN grades g ON s.grade_id = g.id
    WHERE ts.teacher_id = ?
    ORDER BY g.name, s.subject_name
");
$subjects->bind_param("i", $user_id);
$subjects->execute();
$subjects_result = $subjects->get_result();

$students_list = [];
$subject_name = '';
if ($selected_subject_id > 0) {
    $sn = $conn->prepare("SELECT subject_name FROM subjects WHERE id = ?");
    $sn->bind_param("i", $selected_subject_id);
    $sn->execute();
    $sn_res = $sn->get_result();
    if ($srow = $sn_res->fetch_assoc()) $subject_name = $srow['subject_name'];

    $students = $conn->prepare("
        SELECT
            s.id,
            s.fullname,
            s.student_number,
            m.mark AS exam_mark,
            (
                SELECT ROUND(AVG(as2.score), 1)
                FROM assignment_submissions as2
                JOIN assignments a2 ON as2.assignment_id = a2.id
                WHERE as2.student_id = s.user_id AND a2.subject_id = ? AND as2.score IS NOT NULL
            ) AS assignment_avg,
            (
                SELECT COUNT(as3.id)
                FROM assignment_submissions as3
                JOIN assignments a3 ON as3.assignment_id = a3.id
                WHERE as3.student_id = s.user_id AND a3.subject_id = ?
            ) AS assignment_count
        FROM students s
        JOIN student_subject ss ON s.user_id = ss.student_id
        LEFT JOIN marks m ON m.student_id = s.id AND m.subject_id = ?
        WHERE ss.subject_id = ? AND ss.status = 'approved'
        ORDER BY s.fullname
    ");
    $students->bind_param("iiii", $selected_subject_id, $selected_subject_id, $selected_subject_id, $selected_subject_id);
    $students->execute();
    $students_result = $students->get_result();
    while ($row = $students_result->fetch_assoc()) {
        $students_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Results - Teacher View</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="teacherdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Student Results</div>
<div class="app-header-subtitle">View marks and assignment averages for your students per subject.</div>
</div>
<div class="app-user">
<div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Teacher') ?></div>
<div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
</div>
</header>
<section class="app-content">
<div class="panel">
<form method="GET" class="toolbar">
<div>
<label for="subject_id">Subject</label>
<select class="select-input" name="subject_id" id="subject_id" onchange="this.form.submit()">
<option value="">Select Subject</option>
<?php while ($s = $subjects_result->fetch_assoc()): ?>
<option value="<?= (int) $s['id'] ?>" <?= $selected_subject_id === (int) $s['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($s['subject_name'] . ' — ' . $s['grade_name']) ?>
</option>
<?php endwhile; ?>
</select>
</div>
</form>

<?php if ($selected_subject_id > 0): ?>
<div style="margin-top:20px;">
<h2><?= htmlspecialchars($subject_name) ?></h2>
<p style="color:var(--app-muted);margin-bottom:15px;"><?= count($students_list) ?> student(s) enrolled</p>
<?php if (!empty($students_list)): ?>
<table class="data-table">
<thead>
<tr>
<th>Student #</th>
<th>Name</th>
<th>Exam Mark</th>
<th>Assignment Avg</th>
<th>Assignments</th>
<th>Overall</th>
</tr>
</thead>
<tbody>
<?php foreach ($students_list as $st): ?>
<?php
$exam = $st['exam_mark'] !== null ? (int) $st['exam_mark'] : null;
$assign = $st['assignment_avg'] !== null ? (float) $st['assignment_avg'] : null;
if ($exam !== null && $assign !== null) $overall = round(($exam + $assign) / 2, 1);
elseif ($exam !== null) $overall = (float) $exam;
elseif ($assign !== null) $overall = $assign;
else $overall = null;
?>
<tr>
<td><?= htmlspecialchars($st['student_number'] ?: '—') ?></td>
<td><?= htmlspecialchars($st['fullname']) ?></td>
<td><?= $exam !== null ? $exam . '%' : '<span class="text-muted">—</span>' ?></td>
<td><?= $assign !== null ? $assign . '%' : '<span class="text-muted">—</span>' ?></td>
<td><?= (int) $st['assignment_count'] ?></td>
<td><strong><?= $overall !== null ? $overall . '%' : '<span class="text-muted">—</span>' ?></strong></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<div class="empty-state">No approved students found for this subject.</div>
<?php endif; ?>
</div>
<?php endif; ?>
</div>
</section>
</main>
</div>
</body>
</html>
