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
    echo json_encode(["status" => "error", "message" => "User and rejection reason are required"]);
    exit;
}

$checkStudent = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$checkStudent->bind_param("i", $userId);
$checkStudent->execute();
$checkStudent->store_result();
if ($checkStudent->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Student not found"]);
    exit;
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("UPDATE user SET status = 'rejected', rejection_reason = ?, result = 'rejected' WHERE id = ?");
    $stmt->bind_param("si", $reason, $userId);
    $stmt->execute();

    $stmt2 = $conn->prepare("UPDATE students SET class_id = NULL WHERE user_id = ?");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();

    $conn->commit();
    log_audit($conn, $_SESSION['user_id'], 'student_rejected', 'registration', 'student', $userId, ['reason' => $reason]);
    echo json_encode(["status" => "success", "message" => "Student rejected successfully"]);
} catch (Throwable $e) {
    $conn->rollback();
    log_error($conn, $e);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to reject student"]);
}
?>
