<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$students = [];
$studentResult = $conn->query("
    SELECT DISTINCT u.id, u.email, s.fullname
    FROM student_subject ss
    JOIN user u ON ss.student_id = u.id
    JOIN students s ON s.user_id = u.id
    WHERE ss.status = 'pending'
    ORDER BY s.fullname
");

while ($student = $studentResult->fetch_assoc()) {
    $student_id = (int) $student['id'];
    $subjects = [];
    $subjectResult = $conn->query("
        SELECT sub.subject_name
        FROM student_subject ss
        JOIN subjects sub ON ss.subject_id = sub.id
        WHERE ss.student_id = $student_id AND ss.status = 'pending'
        ORDER BY sub.subject_name
    ");
    while ($subject = $subjectResult->fetch_assoc()) {
        $subjects[] = $subject['subject_name'];
    }
    $student['subjects'] = $subjects;
    $students[] = $student;
}

echo json_encode(["status" => "success", "data" => $students]);
?>
