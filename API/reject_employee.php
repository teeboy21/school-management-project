<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$userId = (int) ($data['user_id'] ?? 0);
$reason = trim($data['rejection_reason'] ?? '');

if ($userId <= 0 || $reason === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID and rejection reason are required"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM user WHERE id = ? AND status = 'pending'");
$check->bind_param("i", $userId);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Employee not found or already processed"]);
    exit;
}

$stmt = $conn->prepare("UPDATE user SET status = 'rejected', rejection_reason = ?, result = 'rejected' WHERE id = ?");
$stmt->bind_param("si", $reason, $userId);
if ($stmt->execute()) {
    log_audit($conn, $_SESSION['user_id'] ?? 0, 'employee_rejected', 'registration', 'employee', $userId, ['reason' => $reason]);
    echo json_encode(["status" => "success", "message" => "Employee rejected"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to reject employee"]);
}
