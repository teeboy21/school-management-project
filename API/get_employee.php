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
    echo json_encode(["status" => "error", "message" => "Invalid employee id"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        e.user_id,
        e.fullname,
        e.employee_number,
        e.phone,
        e.address,
        e.race,
        e.identity_number,
        e.gender,
        e.hire_date,
        e.dob,
        e.department,
        e.job_title,
        u.email
    FROM employees e
    JOIN user u ON u.id = e.user_id
    WHERE e.user_id = ?
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Employee not found"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "employee" => $employee
]);
