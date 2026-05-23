<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Only admins can delete events"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];
$id = (int) ($data['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Event id is required"
    ]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete event"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Event deleted successfully"
]);
?>
