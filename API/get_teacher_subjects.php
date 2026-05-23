<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$teacher_id = (int)($_GET['teacher_id'] ?? $_SESSION['user_id'] ?? 0);
if (!$teacher_id || ($_SESSION['role'] ?? '') !== 'teacher') {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.subject_name
    FROM teacher_subject ts
    JOIN subjects s ON ts.subject_id = s.id
    WHERE ts.teacher_id = ?
    ORDER BY s.subject_name
");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
$subjects = [];
while ($row = $result->fetch_assoc()) {
    $subjects[] = $row;
}
$stmt->close();
echo json_encode($subjects);
