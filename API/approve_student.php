<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../email_helper.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$userId = (int) ($data['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "User ID is required"]);
    exit;
}

$stmt = $conn->prepare("UPDATE user SET status = 'approved', result = 'accepted', rejection_reason = NULL WHERE id = ?");
$stmt->bind_param("i", $userId);
if ($stmt->execute()) {
    log_audit($conn, $_SESSION['user_id'] ?? 0, 'student_approved', 'registration', 'student', $userId, []);

    $u = $conn->query("SELECT u.email, COALESCE(s.fullname, u.email) AS fullname FROM user u LEFT JOIN students s ON s.user_id = u.id WHERE u.id = $userId")->fetch_assoc();
    if ($u) email_student_approved($conn, $u['email'], $u['fullname']);

    echo json_encode(["status" => "success", "message" => "Student approved successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to approve student"]);
}
