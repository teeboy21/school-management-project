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

$upload_dir = __DIR__ . '/../uploads/assignments/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

/* ================= CREATE ASSIGNMENT ================= */
if ($action === 'create' && $role === 'teacher') {
    $title = $_POST['title'] ?? '';
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $description = $_POST['description'] ?? '';
    $due_date = $_POST['due_date'] ?? '';
    $due_time = $_POST['due_time'] ?? null;
    $max_score = (int)($_POST['max_score'] ?? 100);

    if (!$title || !$subject_id || !$due_date) {
        echo json_encode(["status" => "error", "message" => "Title, subject, and due date are required"]);
        exit;
    }

    $file_path = null;
    $file_name = null;
    $file_size = null;

    if (isset($_FILES['file']) && $_FILES['file']['error'] === 0) {
        $ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $safe_name = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $upload_dir . $safe_name;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
            $file_path = 'uploads/assignments/' . $safe_name;
            $file_name = $_FILES['file']['name'];
            $file_size = $_FILES['file']['size'];
        }
    }

    $stmt = $conn->prepare("INSERT INTO assignments (teacher_id, subject_id, class_id, title, description, due_date, due_time, max_score, file_path, file_name, file_size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiissssisss", $user_id, $subject_id, $class_id, $title, $description, $due_date, $due_time, $max_score, $file_path, $file_name, $file_size);

    if ($stmt->execute()) {
        $assignment_id = $stmt->insert_id;
        log_audit($conn, $user_id, 'assignment_created', 'academics', 'assignments', $assignment_id, ['title' => $title, 'subject_id' => $subject_id]);
        echo json_encode(["status" => "success", "message" => "Assignment created", "id" => $assignment_id]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $stmt->error]);
    }
    $stmt->close();
    exit;
}

/* ================= LIST TEACHER'S ASSIGNMENTS ================= */
if ($action === 'my_assignments' && $role === 'teacher') {
    $stmt = $conn->prepare("
        SELECT a.*, s.subject_name, c.class_name, g.name AS grade_name,
            (SELECT COUNT(*) FROM assignment_submissions WHERE assignment_id = a.id) AS submission_count
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE a.teacher_id = ?
        ORDER BY a.due_date DESC, a.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
    echo json_encode($assignments);
    exit;
}

/* ================= LIST STUDENT'S ASSIGNMENTS ================= */
if ($action === 'student_assignments' && $role === 'student') {
    $stmt = $conn->prepare("
        SELECT a.*, s.subject_name, t.fullname AS teacher_name,
            (SELECT score FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) AS my_score,
            (SELECT id FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) AS submission_id,
            (SELECT submitted_at FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) AS submitted_at,
            (SELECT feedback FROM assignment_submissions WHERE assignment_id = a.id AND student_id = ?) AS feedback
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN teachers t ON a.teacher_id = t.user_id
        JOIN student_subject ss ON ss.subject_id = a.subject_id AND ss.student_id = ? AND ss.status = 'approved'
        ORDER BY a.due_date ASC, a.created_at DESC
    ");
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = [];
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    $stmt->close();
    echo json_encode($assignments);
    exit;
}

/* ================= GET SINGLE ASSIGNMENT ================= */
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Assignment ID required"]);
        exit;
    }
    $stmt = $conn->prepare("
        SELECT a.*, s.subject_name, t.fullname AS teacher_name, g.name AS grade_name, c.class_name
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN teachers t ON a.teacher_id = t.user_id
        LEFT JOIN classes c ON a.class_id = c.id
        LEFT JOIN grades g ON c.grade_id = g.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $assignment = $result->fetch_assoc();
    $stmt->close();

    if (!$assignment) {
        echo json_encode(["status" => "error", "message" => "Assignment not found"]);
        exit;
    }

    echo json_encode($assignment);
    exit;
}

/* ================= DELETE ASSIGNMENT ================= */
if ($action === 'delete' && $role === 'teacher') {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if (!$id) {
        echo json_encode(["status" => "error", "message" => "Assignment ID required"]);
        exit;
    }

    $stmt = $conn->prepare("SELECT file_path FROM assignments WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$r) {
        echo json_encode(["status" => "error", "message" => "Assignment not found or access denied"]);
        exit;
    }

    if ($r['file_path'] && file_exists(__DIR__ . '/../' . $r['file_path'])) {
        unlink(__DIR__ . '/../' . $r['file_path']);
    }

    $subs = $conn->query("SELECT file_path FROM assignment_submissions WHERE assignment_id = $id");
    while ($s = $subs->fetch_assoc()) {
        if ($s['file_path'] && file_exists(__DIR__ . '/../' . $s['file_path'])) {
            unlink(__DIR__ . '/../' . $s['file_path']);
        }
    }

    $conn->query("DELETE FROM assignment_submissions WHERE assignment_id = $id");
    $conn->query("DELETE FROM assignments WHERE id = $id");

    log_audit($conn, $user_id, 'assignment_deleted', 'academics', 'assignments', $id, []);
    echo json_encode(["status" => "success", "message" => "Assignment deleted"]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action or access denied"]);
