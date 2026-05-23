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

$data = json_decode(file_get_contents("php://input"), true);

$studentId = (int) ($data['student_id'] ?? 0);
$studentNumber = trim($data['student_number'] ?? '');
$fullname = trim($data['fullname'] ?? '');
$phone = trim($data['phone'] ?? '');
$classId = (int) ($data['class_id'] ?? 0);

if ($studentId <= 0 || $studentNumber === '' || $fullname === '' || $classId <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Student id, student number, full name and class are required"
    ]);
    exit;
}

$checkClass = $conn->prepare("SELECT id FROM classes WHERE id = ?");
$checkClass->bind_param("i", $classId);
$checkClass->execute();
$checkClass->store_result();

if ($checkClass->num_rows === 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Selected class does not exist"
    ]);
    exit;
}

$checkDuplicate = $conn->prepare("
    SELECT id
    FROM students
    WHERE student_number = ? AND id != ?
");
$checkDuplicate->bind_param("si", $studentNumber, $studentId);
$checkDuplicate->execute();
$checkDuplicate->store_result();

if ($checkDuplicate->num_rows > 0) {
    http_response_code(409);
    echo json_encode([
        "status" => "error",
        "message" => "Student number already exists"
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE students
    SET student_number = ?, fullname = ?, phone = ?, class_id = ?
    WHERE id = ?
");
$stmt->bind_param("sssii", $studentNumber, $fullname, $phone, $classId, $studentId);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to update student"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Student updated successfully"
]);
?>
