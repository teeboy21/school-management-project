<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Only admins can create events"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];

$title = trim($data['title'] ?? '');
$event_date = trim($data['event_date'] ?? '');
$event_time = trim($data['event_time'] ?? '');
$end_date = trim($data['end_date'] ?? '') ?: null;
$end_time = trim($data['end_time'] ?? '') ?: null;
$audience = trim($data['audience'] ?? 'all');
$category = trim($data['category'] ?? 'general');
$priority = trim($data['priority'] ?? 'normal');
$location = trim($data['location'] ?? '');
$description = trim($data['description'] ?? '');
$status = trim($data['status'] ?? 'published');
$created_by = (int) $_SESSION['user_id'];

$valid_audiences = ['all', 'students', 'teachers', 'admins'];
$valid_categories = ['general', 'academic', 'sports', 'meeting', 'holiday', 'exam'];
$valid_priorities = ['normal', 'important', 'urgent'];
$valid_statuses = ['draft', 'published', 'cancelled'];

if ($title === '' || $event_date === '' || $event_time === '') {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Title, event date, and event time are required"
    ]);
    exit;
}

if (!in_array($audience, $valid_audiences, true) ||
    !in_array($category, $valid_categories, true) ||
    !in_array($priority, $valid_priorities, true) ||
    !in_array($status, $valid_statuses, true)) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "One or more event fields are invalid"
    ]);
    exit;
}

$stmt = $conn->prepare("
    INSERT INTO events (
        title,
        event_date,
        event_time,
        end_date,
        end_time,
        audience,
        category,
        priority,
        location,
        description,
        status,
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "sssssssssssi",
    $title,
    $event_date,
    $event_time,
    $end_date,
    $end_time,
    $audience,
    $category,
    $priority,
    $location,
    $description,
    $status,
    $created_by
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to create event"
    ]);
    exit;
}

echo json_encode([
    "status" => "success",
    "message" => "Event created successfully",
    "event_id" => $stmt->insert_id
]);
?>
