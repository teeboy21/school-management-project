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
    SELECT DISTINCT
        s.id,
        s.student_number,
        s.fullname,
        s.phone AS numbers,
        u.email,
        g.name AS gradename
    FROM teacher_subject ts
    JOIN student_subject ss ON ss.subject_id = ts.subject_id
    JOIN students s ON ss.student_id = s.user_id
    JOIN user u ON s.user_id = u.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN grades g ON c.grade_id = g.id
    WHERE ts.teacher_id = ?
    ORDER BY s.fullname
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $students
]);
?>
