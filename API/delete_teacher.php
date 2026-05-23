<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr_manager'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$userId = (int) ($data['user_id'] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid teacher id"]);
    exit;
}

$check = $conn->prepare("SELECT user_id FROM teachers WHERE user_id = ?");
$check->bind_param("i", $userId);
$check->execute();
$check->store_result();
if ($check->num_rows === 0) {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Teacher not found"]);
    exit;
}

$conn->begin_transaction();
try {
    $stmt1 = $conn->prepare("DELETE FROM teacher_subject WHERE teacher_id = ?");
    $stmt1->bind_param("i", $userId);
    $stmt1->execute();

    $stmt2 = $conn->prepare("DELETE FROM teachers WHERE user_id = ?");
    $stmt2->bind_param("i", $userId);
    $stmt2->execute();

    $stmt3 = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $stmt3->bind_param("i", $userId);
    $stmt3->execute();

    $stmt4 = $conn->prepare("DELETE FROM user WHERE id = ?");
    $stmt4->bind_param("i", $userId);
    $stmt4->execute();

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Teacher deleted successfully"]);
} catch (Throwable $e) {
    $conn->rollback();
    log_error($conn, $e);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to delete teacher"]);
}
?>
