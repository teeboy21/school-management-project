<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$employees = [];
$result = $conn->query("
    SELECT u.id AS user_id, u.email, e.fullname, e.department, e.job_title, r.role_name
    FROM user u
    JOIN employees e ON u.id = e.user_id
    JOIN user_roles ur ON u.id = ur.user_id
    JOIN roles r ON ur.role_id = r.id
    WHERE u.status = 'pending'
    ORDER BY e.fullname
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

echo json_encode(["status" => "success", "employees" => $employees]);
