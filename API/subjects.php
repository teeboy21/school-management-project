<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
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

    $subjects = [];
    $result = $conn->query("
        SELECT s.id, s.subject_name, s.grade_id, s.is_compulsory, g.name AS grade_name
        FROM subjects s
        JOIN grades g ON s.grade_id = g.id
        ORDER BY g.name, s.subject_name
    ");
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }

    echo json_encode(["status" => "success", "data" => $subjects, "grades" => $grades]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true) ?? [];

if ($method === 'POST') {
    $subject_name = trim($data['subject_name'] ?? '');
    $grade_id = (int) ($data['grade_id'] ?? 0);
    $is_compulsory = (int) ($data['is_compulsory'] ?? 0);
    if ($subject_name === '' || $grade_id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Subject name and grade are required"]);
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO subjects (subject_name, grade_id, is_compulsory) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $subject_name, $grade_id, $is_compulsory);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Subject added successfully"]);
    exit;
}

if ($method === 'PUT') {
    $id = (int) ($data['id'] ?? 0);
    $subject_name = trim($data['subject_name'] ?? '');
    $grade_id = (int) ($data['grade_id'] ?? 0);
    $is_compulsory = (int) ($data['is_compulsory'] ?? 0);
    if ($id <= 0 || $subject_name === '' || $grade_id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Subject id, name and grade are required"]);
        exit;
    }
    $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, grade_id = ?, is_compulsory = ? WHERE id = ?");
    $stmt->bind_param("siii", $subject_name, $grade_id, $is_compulsory, $id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Subject updated successfully"]);
    exit;
}

if ($method === 'DELETE') {
    $id = (int) ($data['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Subject id is required"]);
        exit;
    }
    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["status" => "success", "message" => "Subject deleted successfully"]);
    exit;
}

http_response_code(405);
echo json_encode(["status" => "error", "message" => "Method not allowed"]);
?>
