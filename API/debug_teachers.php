<?php
require __DIR__ . '/../config.php';

$result = $conn->query("SELECT t.id, t.fullname, t.employee_number, u.email FROM teachers t LEFT JOIN user u ON u.id = t.user_id LIMIT 5");
echo json_encode(['count' => $result->num_rows, 'data' => $result->fetch_all(MYSQLI_ASSOC)]);