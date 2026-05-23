<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../email_helper.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$student_id = (int) ($data["student_id"] ?? 0);
$action = trim($data["action"] ?? "");

if ($student_id <= 0 || !in_array($action, ["approve", "reject"])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Student and action are required"]);
    exit;
}

function createStudentFees($student_id) {
    global $conn;

    $student = $conn->query("
        SELECT s.id, g.name AS grade_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE s.user_id = $student_id
    ")->fetch_assoc();
    if (!$student) return;

    $year = date("Y");
    $grade_name = $student["grade_name"];
    if (!$grade_name) return;

    $existing = $conn->query("SELECT id FROM student_fees WHERE student_id = $student_id AND academic_year = '$year'")->fetch_assoc();
    if ($existing) return;

    $fs = $conn->query("SELECT * FROM fee_structures WHERE grade_name = '$grade_name' AND academic_year = '$year'")->fetch_assoc();
    if (!$fs) return;

    $total = ($fs["tuition_fee"] ?? 0) + ($fs["registration_fee"] ?? 0) + ($fs["exam_fee"] ?? 0) + ($fs["library_fee"] ?? 0) + ($fs["sports_fee"] ?? 0) + ($fs["transport_fee"] ?? 0) + ($fs["other_fee"] ?? 0);

    $conn->query("INSERT INTO student_fees (student_id, academic_year, fee_structure_id, total_amount, paid_amount, balance, status, due_date)
        VALUES ($student_id, '$year', " . ($fs["id"] ?? 0) . ", $total, 0, $total, 'pending', '" . ($fs["due_date"] ?? "") . "')");
}

if ($action === "approve") {
    $stmt = $conn->prepare("UPDATE student_subject SET status = 'approved' WHERE student_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    
    createStudentFees($student_id);

    $u = $conn->query("SELECT u.email, COALESCE(s.fullname, u.email) AS fullname FROM user u LEFT JOIN students s ON s.user_id = u.id WHERE s.id = $student_id")->fetch_assoc();
    if ($u) email_subjects_approved($conn, $u['email'], $u['fullname']);

    echo json_encode(["status" => "success", "message" => "Subject request approved and fees applied."]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM student_subject WHERE student_id = ? AND status = 'pending'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
echo json_encode(["status" => "success", "message" => "Pending subject request rejected."]);