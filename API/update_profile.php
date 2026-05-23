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
$phone = $data['phone'] ?? '';
$address = $data['address'] ?? '';

$phone = $conn->real_escape_string($phone);
$address = $conn->real_escape_string($address);

$updated = false;

if (in_array($role, ['student'])) {
    $stmt = $conn->prepare("UPDATE students SET phone = ?, address = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $phone, $address, $user_id);
    if ($stmt->execute()) $updated = true;
    $stmt->close();
} elseif (in_array($role, ['teacher'])) {
    $stmt = $conn->prepare("UPDATE teachers SET phone = ?, address = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $phone, $address, $user_id);
    if ($stmt->execute()) $updated = true;
    $stmt->close();
} elseif (in_array($role, ['admin', 'principal', 'hr_manager', 'finance_manager', 'it_technician'])) {
    $stmt = $conn->prepare("UPDATE employees SET phone = ?, address = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $phone, $address, $user_id);
    if ($stmt->execute()) $updated = true;
    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Unsupported role"]);
    exit;
}

if ($updated) {
    log_audit($conn, $user_id, 'profile_updated', 'system', 'user', $user_id, ['fields' => 'phone,address']);
    echo json_encode(["status" => "success", "message" => "Profile updated"]);
} else {
    echo json_encode(["status" => "error", "message" => "No changes made or user not found"]);
}
