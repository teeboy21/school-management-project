<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$students = [];
$sql = "
SELECT 
    u.id AS user_id,
    u.email,
    s.id AS student_id,
    s.fullname,
    COALESCE(g.name, s.grade) AS grade,
    p.phone AS parent_phone,
    p.relationship
FROM user u
JOIN students s ON u.id = s.user_id
LEFT JOIN parents p ON s.id = p.student_id
LEFT JOIN grades g ON s.grade = CAST(g.id AS CHAR)
WHERE u.status = 'profile_completed'
ORDER BY s.fullname
";

$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    $students[] = $row;
}

echo json_encode([
    "status" => "success",
    "students" => $students
]);
?>
