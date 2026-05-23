<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Access denied"
    ]);
    exit;
}

$studentId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid student id"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        s.id AS student_id,
        s.user_id,
        s.student_number,
        s.fullname,
        s.phone,
        s.class_id
    FROM students s
    WHERE s.id = ?
");
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Student not found"
    ]);
    exit;
}

$classesResult = $conn->query("
    SELECT
        c.id,
        g.name AS grade_name,
        c.class_name
    FROM classes c
    JOIN grades g ON c.grade_id = g.id
    ORDER BY g.name, c.class_name
");

$classes = [];
while ($row = $classesResult->fetch_assoc()) {
    $classes[] = $row;
}

echo json_encode([
    "status" => "success",
    "student" => $student,
    "classes" => $classes
]);
?>
