<?php
header("Content-Type: application/json");
require __DIR__ . '/../config.php';

$sql = "SELECT s.id, s.fullname, s.student_number, s.phone, g.name AS grade_name, c.class_name
        FROM students s
        LEFT JOIN classes c ON c.id = s.class_id
        LEFT JOIN grades g ON c.grade_id = g.id
        ORDER BY s.fullname";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode($students);