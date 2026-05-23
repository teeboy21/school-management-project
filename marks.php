<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: login.php");
    exit();
}

require_once 'system_check.php';

$teacher_id = (int) $_SESSION['user_id'];
$selected_subject_id = isset($_GET['subject_id']) ? (int) $_GET['subject_id'] : 0;
$message = '';
$message_type = 'success';

$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'marks_entry_enabled'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$marks_entry_enabled = ($row && $row['setting_value'] === 'open');

$subjects = $conn->prepare("
    SELECT DISTINCT s.id, s.subject_name
    FROM teacher_subject ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.teacher_id = ?
    ORDER BY s.subject_name
");
$subjects->bind_param("i", $teacher_id);
$subjects->execute();
$subjects_result = $subjects->get_result();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_marks'])) {
    $selected_subject_id = (int) ($_POST['subject_id'] ?? 0);
    $marks = $_POST['marks'] ?? [];

    if ($selected_subject_id > 0 && !empty($marks)) {
        foreach ($marks as $student_id => $mark) {
            $student_id = (int) $student_id;
            $mark = ($mark === '') ? null : (int) $mark;

            if ($student_id <= 0 || $mark === null) {
                continue;
            }

            $check = $conn->prepare("SELECT id FROM marks WHERE student_id = ? AND subject_id = ?");
            $check->bind_param("ii", $student_id, $selected_subject_id);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                $update = $conn->prepare("UPDATE marks SET mark = ? WHERE student_id = ? AND subject_id = ?");
                $update->bind_param("iii", $mark, $student_id, $selected_subject_id);
                $update->execute();
            } else {
                $insert = $conn->prepare("INSERT INTO marks(student_id, subject_id, mark) VALUES (?, ?, ?)");
                $insert->bind_param("iii", $student_id, $selected_subject_id, $mark);
                $insert->execute();
            }
        }

        $message = "Marks saved successfully.";
    } else {
        $message = "Choose a subject and enter at least one mark.";
        $message_type = "warning";
    }
}

$student_result = null;
if ($selected_subject_id > 0) {
    $students = $conn->prepare("
        SELECT
            s.id,
            s.fullname,
            s.student_number,
            COALESCE(m.mark, '') AS current_mark,
            (SELECT as2.score FROM assignment_submissions as2 JOIN assignments a2 ON as2.assignment_id = a2.id WHERE as2.student_id = s.user_id AND a2.subject_id = ? AND as2.score IS NOT NULL LIMIT 1) AS assignment_mark
        FROM students s
        JOIN student_subject ss ON s.user_id = ss.student_id
        LEFT JOIN marks m ON m.student_id = s.id AND m.subject_id = ?
        WHERE ss.subject_id = ? AND ss.status = 'approved'
        ORDER BY s.fullname
    ");
    $students->bind_param("iii", $selected_subject_id, $selected_subject_id, $selected_subject_id);
    $students->execute();
    $student_result = $students->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marks</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="teacherdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Marks Entry</div>
                <div class="app-header-subtitle">Select one of your subjects and capture marks directly in the table.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Teacher') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <?php if (!$marks_entry_enabled): ?>
                <div class="hero-card" style="background:#fee2e2;border-color:#ef4444;">
                    <h2>Marks Entry Disabled</h2>
                    <p>Marks entry is currently locked by the administrator. Please check back later.</p>
                </div>
            <?php else: ?>
            <div class="hero-card">
                <h2>Enter Marks</h2>
                <p>The page now loads your assigned subjects first, shows existing marks, and updates them correctly.</p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= $message_type === 'warning' ? 'warning' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="panel">
                <form method="GET" class="toolbar">
                    <div>
                        <label for="subject_id">Subject</label>
                        <select class="select-input" name="subject_id" id="subject_id" onchange="this.form.submit()">
                            <option value="">Select Subject</option>
                            <?php while ($s = $subjects_result->fetch_assoc()): ?>
                                <option value="<?= (int) $s['id'] ?>" <?= $selected_subject_id === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['subject_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </form>

                <?php if ($selected_subject_id <= 0): ?>
                    <div class="empty-state">Choose a subject to load the student list.</div>
                <?php elseif ($student_result && $student_result->num_rows > 0): ?>
                    <form method="POST">
                        <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student Number</th>
                                        <th>Student Name</th>
                                        <th>Exam Mark</th>
                                        <th>Assignment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = $student_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($student['student_number'] ?: 'Not assigned') ?></td>
                                            <td><?= htmlspecialchars($student['fullname']) ?></td>
                                            <td>
                                                <input
                                                    class="text-input"
                                                    type="number"
                                                    min="0"
                                                    max="100"
                                                    name="marks[<?= (int) $student['id'] ?>]"
                                                    value="<?= htmlspecialchars((string) $student['current_mark']) ?>"
                                                    placeholder="0 - 100"
                                                >
                                            </td>
                                            <td>
                                                <?php if ($student['assignment_mark'] !== null): ?>
                                                    <span class="badge badge-info"><?= (int) $student['assignment_mark'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="page-actions">
                            <button type="submit" name="save_marks" class="btn btn-primary">Save Marks</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty-state">No approved students were found for this subject yet.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
