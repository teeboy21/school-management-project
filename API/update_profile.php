<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid request data"]);
    exit;
}

$fields = [];
$params = [];
$types = "";

if (array_key_exists('phone', $data)) {
    $fields[] = "phone = ?";
    $params[] = $data['phone'];
    $types .= "s";
}
if (array_key_exists('address', $data)) {
    $fields[] = "address = ?";
    $params[] = $data['address'];
    $types .= "s";
}

if (empty($fields)) {
    echo json_encode(["status" => "error", "message" => "No fields to update"]);
    exit;
}

$params[] = $user_id;
$types .= "i";

if (in_array($role, ['student'])) {
    $table = 'students';
} elseif (in_array($role, ['teacher'])) {
    $table = 'teachers';
} elseif (in_array($role, ['admin', 'principal', 'hr_manager', 'finance_manager', 'it_technician'])) {
    $table = 'employees';
} else {
    echo json_encode(["status" => "error", "message" => "Unsupported role"]);
    exit;
}

$sql = "UPDATE $table SET " . implode(", ", $fields) . " WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    log_audit($conn, $user_id, 'profile_updated', 'system', 'user', $user_id, ['fields' => implode(',', array_keys($data))]);
    echo json_encode(["status" => "success", "message" => "Profile updated"]);
} else {
    echo json_encode(["status" => "error", "message" => "No changes made or user not found"]);
}
$stmt->close();
