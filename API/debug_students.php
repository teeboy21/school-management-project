<?php
require __DIR__ . '/../config.php';

$result = $conn->query("SELECT s.id, s.fullname, s.student_number, u.email FROM students s LEFT JOIN user u ON u.id = s.user_id LIMIT 5");
echo json_encode(['count' => $result->num_rows, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);