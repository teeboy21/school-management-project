<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Access denied"
    ]);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$student = $conn->query("
    SELECT s.user_id, s.fullname, c.grade_id, c.class_name, g.name AS grade_name
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN grades g ON c.grade_id = g.id
    WHERE s.user_id = $user_id
")->fetch_assoc();

if (!$student) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Student profile not found"
    ]);
    exit;
}

$grade_id = (int) ($student['grade_id'] ?? 0);

$approved = [];
$approvedResult = $conn->query("
    SELECT sub.id, sub.subject_name
    FROM student_subject ss
    JOIN subjects sub ON sub.id = ss.subject_id
    WHERE ss.student_id = $user_id AND ss.status = 'approved'
    ORDER BY sub.subject_name
");
while ($row = $approvedResult->fetch_assoc()) {
    $approved[] = $row;
}

$pending = [];
$pendingResult = $conn->query("
    SELECT sub.id, sub.subject_name
    FROM student_subject ss
    JOIN subjects sub ON sub.id = ss.subject_id
    WHERE ss.student_id = $user_id AND ss.status = 'pending'
    ORDER BY sub.subject_name
");
while ($row = $pendingResult->fetch_assoc()) {
    $pending[] = $row;
}

$max_setting = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_optional_subjects'")->fetch_assoc();
$max_optional = (int)($max_setting['setting_value'] ?? 7);

$available = [];
if ($grade_id > 0 && count($approved) === 0 && count($pending) === 0) {
    $availableResult = $conn->query("
        SELECT id, subject_name, is_compulsory
        FROM subjects
        WHERE grade_id = $grade_id
        ORDER BY is_compulsory DESC, subject_name
    ");
    while ($row = $availableResult->fetch_assoc()) {
        $available[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "student" => $student,
    "approved_subjects" => $approved,
    "pending_subjects" => $pending,
    "available_subjects" => $available,
    "max_optional_subjects" => $max_optional
]);
?>
