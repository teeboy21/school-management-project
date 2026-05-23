<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $grades = [];
    $gradesResult = $conn->query("SELECT * FROM grades ORDER BY name");
    while ($row = $gradesResult->fetch_assoc()) {
        $grades[] = $row;
    }

    $classes = [];
    $result = $conn->query("
        SELECT c.id, c.class_name, c.grade_id, g.name AS grade
        FROM classes c
        JOIN grades g ON c.grade_id = g.id
        ORDER BY g.name, c.class_name
    ");
    while ($row = $result->fetch_assoc()) {
        $classes[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $classes, "grades" => $grades]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method === 'POST') {
    $grade_id = (int) ($data['grade_id'] ?? 0);
    $class_name = trim($data['class_name'] ?? '');
    if ($grade_id <= 0 || $class_name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Grade and class name are required"]);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO classes (grade_id, class_name) VALUES (?, ?)");
    $stmt->bind_param("is", $grade_id, $class_name);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Class added successfully"]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($data['id'] ?? 0);
    $grade_id = (int) ($data['grade_id'] ?? 0);
    $class_name = trim($data['class_name'] ?? '');
    if ($id <= 0 || $grade_id <= 0 || $class_name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Class id, grade and class name are required"]);
        exit;
    }
    $stmt = $conn->prepare("UPDATE classes SET grade_id = ?, class_name = ? WHERE id = ?");
    $stmt->bind_param("isi", $grade_id, $class_name, $id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Class updated successfully"]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Class id is required"]);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Class deleted successfully"]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
?>
