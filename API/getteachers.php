<?php
header("Content-Type: application/json");
require __DIR__ . '/../config.php';

$sql = "SELECT * FROM teachers ORDER BY fullname";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $conn->error]);
    exit;
}

$teachers = [];
while ($row = $result->fetch_assoc()) {
    $teachers[] = $row;
}

echo json_encode($teachers);