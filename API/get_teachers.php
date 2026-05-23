<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager', 'principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$teachers = [];
$result = $conn->query("
    SELECT
        t.user_id,
        t.fullname,
        t.employee_number,
        t.phone,
        t.hire_date,
        u.email
    FROM teachers t
    JOIN user u ON u.id = t.user_id
    ORDER BY t.fullname
");
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $teachers
]);
?>
