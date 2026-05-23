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
    $result = $conn->query("SELECT * FROM grades ORDER BY name");
    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }
    echo json_encode(["status" => "success", "data" => $grades]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method === 'POST') {
    $name = trim($data['name'] ?? '');
    if ($name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Grade name is required"]);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO grades (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Grade added successfully"]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    if ($id <= 0 || $name === '') {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Grade id and name are required"]);
        exit;
    }
    $stmt = $conn->prepare("UPDATE grades SET name = ? WHERE id = ?");
    $stmt->bind_param("si", $name, $id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Grade updated successfully"]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Grade id is required"]);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM grades WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Grade deleted successfully"]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
?>
