<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$role = $_SESSION['role'] ?? '';
$allowed = ['admin'];
$setting = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'principal_can_edit_subjects'")->fetch_assoc();
if ($role === 'principal' && ($setting['setting_value'] ?? 'off') === 'on') {
    $allowed[] = 'principal';
}

if (!in_array($role, $allowed)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

/* ================= SEARCH STUDENTS ================= */
if ($action === 'search_students') {
    $q = $_GET['q'] ?? '';
    $q = $conn->real_escape_string($q);
    $result = $conn->query("
        SELECT s.user_id AS id, s.fullname, s.student_number, g.name AS grade
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE s.fullname LIKE '%$q%' OR s.student_number LIKE '%$q%'
        ORDER BY s.fullname LIMIT 20
    ");
    $students = [];
    while ($r = $result->fetch_assoc()) {
        $students[] = $r;
    }
    echo json_encode($students);
    exit;
}

/* ================= GET STUDENT SUBJECTS ================= */
if ($action === 'get_subjects') {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) {
        echo json_encode(["status" => "error", "message" => "Student ID required"]);
        exit;
    }

    $grade_result = $conn->query("SELECT grade FROM students WHERE user_id = $student_id");
    $grade_id = 0;
    if ($gr = $grade_result->fetch_assoc()) {
        $grade_id = (int)$gr['grade'];
    }

    $subjects = $conn->query("
        SELECT s.id, s.subject_name, ss.status, ss.id AS link_id
        FROM subjects s
        LEFT JOIN student_subject ss ON ss.subject_id = s.id AND ss.student_id = $student_id
        WHERE s.grade_id = $grade_id
        ORDER BY s.subject_name
    ");
    $list = [];
    while ($r = $subjects->fetch_assoc()) {
        $list[] = $r;
    }

    $stmt = $conn->prepare("SELECT fullname, student_number FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(["subjects" => $list, "student" => $student]);
    exit;
}

/* ================= TOGGLE SUBJECT ================= */
if ($action === 'toggle') {
    $data = json_decode(file_get_contents("php://input"), true);
    $student_id = (int)($data['student_id'] ?? 0);
    $subject_id = (int)($data['subject_id'] ?? 0);
    $add = !empty($data['add']);

    if (!$student_id || !$subject_id) {
        echo json_encode(["status" => "error", "message" => "Student and subject required"]);
        exit;
    }

    if ($add) {
        $conn->query("INSERT IGNORE INTO student_subject (student_id, subject_id, status) VALUES ($student_id, $subject_id, 'approved')");
        log_audit($conn, $user_id, 'student_subject_added', 'academics', 'student_subject', 0, ['student_id' => $student_id, 'subject_id' => $subject_id]);
    } else {
        $conn->query("DELETE FROM student_subject WHERE student_id = $student_id AND subject_id = $subject_id");
        log_audit($conn, $user_id, 'student_subject_removed', 'academics', 'student_subject', 0, ['student_id' => $student_id, 'subject_id' => $subject_id]);
    }

    echo json_encode(["status" => "success", "message" => $add ? "Subject added" : "Subject removed"]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
