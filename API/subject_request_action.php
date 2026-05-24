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

    $grade_name = $student["grade_name"];
    if (!$grade_name) return;

    // Try both academic year formats
    $year_range = date('Y') . '-' . (date('Y') + 1);
    $year_single = date('Y');
    $fs = $conn->query("SELECT * FROM fee_structures WHERE grade_name = '$grade_name' AND (academic_year = '$year_range' OR academic_year = '$year_single') LIMIT 1")->fetch_assoc();
    if (!$fs) return;

    $academic_year = $fs['academic_year'];
    $existing = $conn->query("SELECT id FROM student_fees WHERE student_id = {$student['id']} AND academic_year = '$academic_year'")->fetch_assoc();
    if ($existing) return;

    $total = (float)($fs["tuition_fee"] ?? 0) + (float)($fs["registration_fee"] ?? 0) + (float)($fs["exam_fee"] ?? 0) + (float)($fs["library_fee"] ?? 0) + (float)($fs["sports_fee"] ?? 0) + (float)($fs["transport_fee"] ?? 0) + (float)($fs["other_fee"] ?? 0);

    $due = $fs["due_date"] ? "'" . $conn->real_escape_string($fs["due_date"]) . "'" : 'NULL';
    $result = $conn->query("INSERT INTO student_fees (student_id, academic_year, fee_structure_id, total_amount, paid_amount, balance, status, due_date)
        VALUES ({$student['id']}, '$academic_year', {$fs['id']}, $total, 0, $total, 'pending', $due)");

    if ($conn->error) {
        error_log("createStudentFees failed: " . $conn->error);
    }
}

if ($action === "approve") {
    $stmt = $conn->prepare("UPDATE student_subject SET status = 'approved' WHERE student_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    
    createStudentFees($student_id);

    $u = $conn->query("SELECT u.email, COALESCE(s.fullname, u.email) AS fullname FROM user u LEFT JOIN students s ON s.user_id = u.id WHERE u.id = $student_id")->fetch_assoc();
    if ($u) {
        $fee = $conn->query("SELECT sf.total_amount, sf.due_date FROM student_fees sf JOIN students s ON sf.student_id = s.id WHERE s.user_id = $student_id AND sf.status = 'pending' ORDER BY sf.id DESC LIMIT 1")->fetch_assoc();
        email_subjects_approved($conn, $u['email'], $u['fullname'], $fee ? (float)$fee['total_amount'] : null, $fee ? $fee['due_date'] : null);
    }

    echo json_encode(["status" => "success", "message" => "Subject request approved and fees applied."]);
    exit;
}

$stmt = $conn->prepare("DELETE FROM student_subject WHERE student_id = ? AND status = 'pending'");
$stmt->bind_param("i", $student_id);
$stmt->execute();
echo json_encode(["status" => "success", "message" => "Pending subject request rejected."]);