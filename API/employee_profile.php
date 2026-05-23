<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$allowedRoles = ['employee', 'admin', 'hr_manager', 'finance_manager'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowedRoles)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$employeeId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT u.email, e.employee_number, e.fullname, e.phone, e.address, e.gender, e.hire_date, e.department, e.job_title
    FROM user u
    JOIN employees e ON u.id = e.user_id
    WHERE u.id = ?
");
$stmt->bind_param("i", $employeeId);
$stmt->execute();
$employee = $stmt->get_result()->fetch_assoc();

if (!$employee) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Employee profile not found"]);
    exit;
}

echo json_encode([
    "status" => "success",
    "employee" => $employee
]);
