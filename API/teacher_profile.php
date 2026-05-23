<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$teacherId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT u.email, t.employee_number, t.fullname, t.phone, t.address, t.gender, t.hire_date
    FROM user u
    JOIN teachers t ON u.id = t.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

$subjects = [];
$subjectsResult = $conn->query("
    SELECT DISTINCT s.subject_name
    FROM teacher_subject ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.teacher_id = {$teacherId}
");
while ($row = $subjectsResult->fetch_assoc()) {
    $subjects[] = $row['subject_name'];
}

$classes = [];
$classesResult = $conn->query("
    SELECT DISTINCT g.name AS grade, c.class_name
    FROM teacher_subject ts
    JOIN classes c ON ts.class_id = c.id
    JOIN grades g ON c.grade_id = g.id
    WHERE ts.teacher_id = {$teacherId}
");
while ($row = $classesResult->fetch_assoc()) {
    $classes[] = $row['grade'] . ' ' . $row['class_name'];
}

echo json_encode([
    "status" => "success",
    "teacher" => $teacher,
    "subjects" => $subjects,
    "classes" => $classes
]);
?>
