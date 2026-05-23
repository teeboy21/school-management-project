<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode([
        "status" => "error",
        "message" => "Access denied"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$studentId = (int) ($data['student_id'] ?? 0);

if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid student id"
    ]);
    exit;
}

$lookup = $conn->prepare("SELECT user_id FROM students WHERE id = ?");
$lookup->bind_param("i", $studentId);
$lookup->execute();
$result = $lookup->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    http_response_code(404);
    echo json_encode([
        "status" => "error",
        "message" => "Student not found"
    ]);
    exit;
}

$userId = (int) $student['user_id'];

$conn->begin_transaction();

try {
    $deleteParents = $conn->prepare("DELETE FROM parents WHERE student_id = ?");
    $deleteParents->bind_param("i", $studentId);
    $deleteParents->execute();

    $deleteStudent = $conn->prepare("DELETE FROM students WHERE id = ?");
    $deleteStudent->bind_param("i", $studentId);
    $deleteStudent->execute();

    $deleteRoles = $conn->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $deleteRoles->bind_param("i", $userId);
    $deleteRoles->execute();

    $deleteUser = $conn->prepare("DELETE FROM user WHERE id = ?");
    $deleteUser->bind_param("i", $userId);
    $deleteUser->execute();

    $conn->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Student deleted successfully"
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    log_error($conn, $e);
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to delete student"
    ]);
}
?>
