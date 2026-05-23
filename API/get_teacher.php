<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid teacher id"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        t.user_id,
        t.fullname,
        t.employee_number,
        t.phone,
        t.address,
        t.race,
        t.identitynumber,
        t.gender,
        t.hire_date,
        t.dob,
        u.email
    FROM teachers t
    JOIN user u ON u.id = t.user_id
    WHERE t.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();

if (!$teacher) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Teacher not found"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "teacher" => $teacher
]);
?>
