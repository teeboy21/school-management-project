<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager','principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$employees = [];
$result = $conn->query("
    SELECT
        e.user_id,
        e.fullname,
        e.employee_number,
        e.phone,
        e.hire_date,
        e.department,
        e.job_title,
        u.email
    FROM employees e
    JOIN user u ON u.id = e.user_id
    ORDER BY e.fullname
");
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $employees
]);
