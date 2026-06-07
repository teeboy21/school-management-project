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
$data = json_decode(file_get_contents("php://input"), true);
$selected = $data['subjects'] ?? [];

if (!is_array($selected)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid subject selection"
    ]);
    exit;
}

$student = $conn->query("
    SELECT c.grade_id
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.user_id = $user_id
")->fetch_assoc();

$grade_id = (int) ($student['grade_id'] ?? 0);

if ($grade_id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "No class has been assigned to your account yet"
    ]);
    exit;
}

$approved_count = (int) ($conn->query("
    SELECT COUNT(*) AS total
    FROM student_subject
    WHERE student_id = $user_id AND status = 'approved'
")->fetch_assoc()['total'] ?? 0);

$pending_count = (int) ($conn->query("
    SELECT COUNT(*) AS total
    FROM student_subject
    WHERE student_id = $user_id AND status = 'pending'
")->fetch_assoc()['total'] ?? 0);

if ($approved_count > 0) {
    http_response_code(409);
    echo json_encode([
        "status" => "error",
        "message" => "Your subject application has already been approved"
    ]);
    exit;
}

if ($pending_count > 0) {
    http_response_code(409);
    echo json_encode([
        "status" => "error",
        "message" => "Your subject application is still pending approval"
    ]);
    exit;
}

$selected = array_values(array_unique(array_map('intval', array_filter($selected))));

$allowed = [];
$allowedResult = $conn->query("
    SELECT id, is_compulsory
    FROM subjects
    WHERE grade_id = $grade_id
");
$compulsory_ids = [];
while ($row = $allowedResult->fetch_assoc()) {
    $allowed[] = (int) $row['id'];
    if ((int)$row['is_compulsory'] === 1) {
        $compulsory_ids[] = (int) $row['id'];
    }
}

// Auto-include compulsory subjects
$all_selected = array_unique(array_merge($selected, $compulsory_ids));

foreach ($all_selected as $subject_id) {
    if (!in_array($subject_id, $allowed, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "One or more selected subjects are invalid for your grade"
        ]);
        exit;
    }
}

$optional_selected = array_diff($selected, $compulsory_ids);
$max_setting = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_optional_subjects'")->fetch_assoc();
$max_optional = (int)($max_setting['setting_value'] ?? 7);

if (count($optional_selected) > $max_optional) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "You can select at most $max_optional optional subjects"
    ]);
    exit;
}

if (empty($all_selected)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Select at least one subject before submitting"
    ]);
    exit;
}

$conn->query("DELETE FROM student_subject WHERE student_id = $user_id");

$stmt = $conn->prepare("
    INSERT INTO student_subject (student_id, subject_id, status)
    VALUES (?, ?, 'pending')
");

foreach ($all_selected as $subject_id) {
    $stmt->bind_param("ii", $user_id, $subject_id);
    $stmt->execute();
}

echo json_encode([
    "status" => "success",
    "message" => "Subjects submitted successfully. They are now waiting for approval."
]);
?>
