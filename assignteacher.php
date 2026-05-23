<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager'])) {
    header("Location: login.html");
    exit();
}

$message = '';
$message_type = 'success';

if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM teacher_subject WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $message = "Assignment deleted successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_assignment'])) {
    $id = (int) $_POST['id'];
    $teacher_id = (int) $_POST['teacher_id'];
    $subject_id = (int) $_POST['subject_id'];
    $class_id = (int) $_POST['class_id'];

    $stmt = $conn->prepare("
        UPDATE teacher_subject
        SET teacher_id = ?, subject_id = ?, class_id = ?
        WHERE id = ?
    ");
    $stmt->bind_param("iiii", $teacher_id, $subject_id, $class_id, $id);
    $stmt->execute();
    $message = "Assignment updated successfully.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_assignment'])) {
    $teacher_id = (int) $_POST['teacher_id'];
    $subject_id = (int) $_POST['subject_id'];
    $class_id = (int) $_POST['class_id'];

    $check = $conn->prepare("
        SELECT id
        FROM teacher_subject
        WHERE teacher_id = ? AND subject_id = ? AND class_id = ?
    ");
    $check->bind_param("iii", $teacher_id, $subject_id, $class_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $message = "This assignment already exists.";
        $message_type = "warning";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO teacher_subject (teacher_id, subject_id, class_id)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $teacher_id, $subject_id, $class_id);
        $stmt->execute();
        $message = "Teacher assigned successfully.";
    }
}

$teacherRows = [];
$teacherResult = $conn->query("
    SELECT t.user_id, t.fullname, u.email
    FROM teachers t
    JOIN user u ON t.user_id = u.id
    ORDER BY t.fullname
");
while ($row = $teacherResult->fetch_assoc()) {
    $teacherRows[] = $row;
}

$subjectRows = [];
$subjectResult = $conn->query("
    SELECT s.id, s.subject_name, g.name AS grade_name
    FROM subjects s
    JOIN grades g ON s.grade_id = g.id
    ORDER BY g.name, s.subject_name
");
while ($row = $subjectResult->fetch_assoc()) {
    $subjectRows[] = $row;
}

$classRows = [];
$classResult = $conn->query("
    SELECT c.id, c.class_name, g.name AS grade_name
    FROM classes c
    JOIN grades g ON c.grade_id = g.id
    ORDER BY g.name, c.class_name
");
while ($row = $classResult->fetch_assoc()) {
    $classRows[] = $row;
}

$assignments = $conn->query("
    SELECT ts.id, t.fullname, u.email, s.subject_name, c.class_name, g.name AS grade, ts.teacher_id, ts.subject_id, ts.class_id
    FROM teacher_subject ts
    JOIN teachers t ON ts.teacher_id = t.user_id
    JOIN user u ON t.user_id = u.id
    JOIN subjects s ON ts.subject_id = s.id
    JOIN classes c ON ts.class_id = c.id
    JOIN grades g ON c.grade_id = g.id
    ORDER BY t.fullname, s.subject_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assign Teachers</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Teacher Assignment</div>
                <div class="app-header-subtitle">Assign teachers to subjects and classes, then review or correct allocations below.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= $message_type === 'warning' ? 'warning' : 'success' ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="panel" style="margin-bottom:20px;">
                <h2>Create Assignment</h2>
                <form method="POST" class="form-grid">
                    <div>
                        <label>Teacher</label>
                        <select name="teacher_id" required>
                            <option value="">Select Teacher</option>
                            <?php foreach ($teacherRows as $teacher): ?>
                                <option value="<?= (int) $teacher['user_id'] ?>">
                                    <?= htmlspecialchars($teacher['fullname'] . ' - ' . $teacher['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Subject</label>
                        <select name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjectRows as $subject): ?>
                                <option value="<?= (int) $subject['id'] ?>">
                                    <?= htmlspecialchars($subject['subject_name'] . ' (' . $subject['grade_name'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Class</label>
                        <select name="class_id" required>
                            <option value="">Select Class</option>
                            <?php foreach ($classRows as $class): ?>
                                <option value="<?= (int) $class['id'] ?>">
                                    <?= htmlspecialchars($class['grade_name'] . ' ' . $class['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="page-actions">
                        <button type="submit" name="create_assignment" class="btn btn-primary">Assign Teacher</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <h2>Current Assignments</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Grade</th>
                                <th>Class</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($assignment = $assignments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($assignment['fullname']) ?></td>
                                    <td><?= htmlspecialchars($assignment['email']) ?></td>
                                    <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                                    <td><?= htmlspecialchars($assignment['grade']) ?></td>
                                    <td><?= htmlspecialchars($assignment['class_name']) ?></td>
                                    <td>
                                        <form method="POST" style="display:flex; gap:8px; flex-wrap:wrap;">
                                            <input type="hidden" name="id" value="<?= (int) $assignment['id'] ?>">
                                            <select name="teacher_id" required>
                                                <?php foreach ($teacherRows as $teacher): ?>
                                                    <option value="<?= (int) $teacher['user_id'] ?>" <?= (int) $teacher['user_id'] === (int) $assignment['teacher_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($teacher['fullname']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select name="subject_id" required>
                                                <?php foreach ($subjectRows as $subject): ?>
                                                    <option value="<?= (int) $subject['id'] ?>" <?= (int) $subject['id'] === (int) $assignment['subject_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($subject['subject_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select name="class_id" required>
                                                <?php foreach ($classRows as $class): ?>
                                                    <option value="<?= (int) $class['id'] ?>" <?= (int) $class['id'] === (int) $assignment['class_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($class['grade_name'] . ' ' . $class['class_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="update_assignment" class="btn btn-secondary">Update</button>
                                            <a class="btn btn-danger" href="?delete=<?= (int) $assignment['id'] ?>" onclick="return confirm('Delete this assignment?')">Delete</a>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
