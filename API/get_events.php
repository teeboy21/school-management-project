<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Access denied"
    ]);
    exit;
}

$role = $_SESSION['role'];
$audiences = ['all'];

if ($role === 'student') {
    $audiences[] = 'students';
} elseif ($role === 'teacher') {
    $audiences[] = 'teachers';
} elseif ($role === 'admin') {
    $audiences[] = 'admins';
}

$placeholders = implode(',', array_fill(0, count($audiences), '?'));
$types = str_repeat('s', count($audiences));

$sql = "
    SELECT
        e.id,
        e.title,
        e.event_date,
        e.event_time,
        e.end_date,
        e.end_time,
        e.audience,
        e.category,
        e.priority,
        e.location,
        e.description,
        e.status,
        e.created_by,
        e.created_at,
        u.email AS created_by_email
    FROM events e
    LEFT JOIN user u ON u.id = e.created_by
    WHERE e.audience IN ($placeholders)
      AND e.status IN ('published', 'draft')
    ORDER BY e.event_date ASC, e.event_time ASC, e.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$audiences);
$stmt->execute();
$result = $stmt->get_result();

$events = [];
while ($row = $result->fetch_assoc()) {
    $events[] = $row;
}

echo json_encode([
    "status" => "success",
    "data" => $events
]);
?>
