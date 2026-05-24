<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? '';

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

$assignments_enabled = true;
$en_r = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'assignments_enabled'");
if ($en_r && ($en_row = $en_r->fetch_assoc()) && $en_row['setting_value'] === 'off') {
    $assignments_enabled = false;
}

$upload_dir = __DIR__ . '/../uploads/submissions/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$allowed_exts = ['pdf','doc','docx','ppt','pptx','xls','xlsx','txt','zip','rar','7z','jpg','jpeg','png','gif','csv','odt','ods'];
$max_file_size = 10 * 1024 * 1024; // 10MB

/* ================= SUBMIT ASSIGNMENT (STUDENT) ================= */
if ($action === 'submit' && $role === 'student') {
    if (!$assignments_enabled) {
        echo json_encode(["status" => "error", "message" => "Assignments module is disabled"]);
        exit;
    }
    $assignment_id = (int)($_POST['assignment_id'] ?? 0);

    if (!$assignment_id) {
        echo json_encode(["status" => "error", "message" => "Assignment ID required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $assignment_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        echo json_encode(["status" => "error", "message" => "You already submitted this assignment"]);
        exit;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id, due_date, due_time FROM assignments WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $assignment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$assignment) {
        echo json_encode(["status" => "error", "message" => "Assignment not found"]);
        exit;
    }

    $file_path = null;
    $file_name = null;
    $file_size = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts)) {
            echo json_encode(["status" => "error", "message" => "File type .$ext is not allowed"]);
            exit;
        }
        if ($_FILES['file']['size'] > $max_file_size) {
            echo json_encode(["status" => "error", "message" => "File exceeds maximum size of 10MB"]);
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
        finfo_close($finfo);
        $blocked = ['text/x-php', 'application/x-httpd-php', 'application/x-httpd-php-source', 'text/javascript', 'application/javascript'];
        if (in_array($mime, $blocked)) {
            echo json_encode(["status" => "error", "message" => "Executable files are not allowed"]);
            exit;
        }
        $safe_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $upload_dir . $safe_name;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $file_path = 'uploads/submissions/' . $safe_name;
            $file_name = $_FILES['file']['name'];
            $file_size = $_FILES['file']['size'];
        }
    }

    $stmt = $conn->prepare("INSERT INTO assignment_submissions (assignment_id, student_id, file_path, file_name, file_size) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $assignment_id, $user_id, $file_path, $file_name, $file_size);

    if ($stmt->execute()) {
        log_audit($conn, $user_id, 'assignment_submitted', 'academics', 'assignment_submissions', $stmt->insert_id, ['assignment_id' => $assignment_id]);
        echo json_encode(["status" => "success", "message" => "Assignment submitted successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* ================= LIST SUBMISSIONS FOR AN ASSIGNMENT (TEACHER) ================= */
if ($action === 'submissions' && $role === 'teacher') {
    $assignment_id = (int)($_GET['assignment_id'] ?? 0);
    if (!$assignment_id) {
        echo json_encode(["status" => "error", "message" => "Assignment ID required"]);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT sub.*, s.fullname AS student_name, s.student_number
        FROM assignment_submissions sub
        JOIN students s ON sub.student_id = s.user_id
        WHERE sub.assignment_id = ?
        ORDER BY sub.submitted_at DESC
    ");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT s.user_id AS student_id, s.fullname AS student_name, s.student_number
        FROM student_subject ss
        JOIN students s ON ss.student_id = s.user_id
        JOIN assignments a ON a.subject_id = ss.subject_id AND (a.class_id IS NULL OR a.class_id = s.class_id)
        WHERE a.id = ? AND ss.status = 'approved'
        ORDER BY s.fullname
    ");
    $stmt->bind_param("i", $assignment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $all_students = [];
    while ($row = $result->fetch_assoc()) {
        $all_students[] = $row;
    }
    $stmt->close();

    echo json_encode(["submissions" => $submissions, "all_students" => $all_students]);
    exit;
}

/* ================= GRADE SUBMISSION (TEACHER) ================= */
if ($action === 'grade' && $role === 'teacher') {
    $data = json_decode(file_get_contents("php://input"), true);
    $submission_id = (int)($data['submission_id'] ?? 0);
    $score = (int)($data['score'] ?? 0);
    $feedback = $data['feedback'] ?? '';

    if (!$submission_id) {
        echo json_encode(["status" => "error", "message" => "Submission ID required"]);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE assignment_submissions SET score = ?, feedback = ?, graded_by = ?, graded_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("isii", $score, $feedback, $user_id, $submission_id);
    if ($stmt->execute()) {
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT sub.assignment_id, sub.student_id, a.subject_id
            FROM assignment_submissions sub
            JOIN assignments a ON sub.assignment_id = a.id
            WHERE sub.id = ?
        ");
        $stmt->bind_param("i", $submission_id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($r) {
            log_audit($conn, $user_id, 'assignment_graded', 'academics', 'assignment_submissions', $submission_id, ['assignment_id' => $r['assignment_id'], 'student_id' => $r['student_id'], 'score' => $score]);
        } else {
            log_audit($conn, $user_id, 'assignment_graded', 'academics', 'assignment_submissions', $submission_id, ['score' => $score]);
        }
        echo json_encode(["status" => "success", "message" => "Grade saved"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action or access denied"]);
