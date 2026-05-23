<?php
header("Content-Type: application/json");
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

$allowed_roles = ['admin','it_technician'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$action = $_GET['action'] ?? '';

/* ================= LIST ERRORS ================= */
if ($action === 'list') {
    $limit = min((int)($_GET['limit'] ?? 100), 500);
    $offset = max((int)($_GET['offset'] ?? 0), 0);
    $search = $conn->real_escape_string($_GET['search'] ?? '');

    $where = '';
    if ($search !== '') {
        $where = "WHERE e.error_message LIKE '%$search%' OR e.error_file LIKE '%$search%' OR e.request_url LIKE '%$search%'";
    }

    $total = $conn->query("SELECT COUNT(*) cnt FROM error_log e $where")->fetch_assoc()['cnt'] ?? 0;

    $result = $conn->query("
        SELECT e.*, u.email AS user_email
        FROM error_log e
        LEFT JOIN user u ON e.user_id = u.id
        $where
        ORDER BY e.created_at DESC
        LIMIT $limit OFFSET $offset
    ");

    $rows = [];
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }

    echo json_encode(["total" => (int)$total, "rows" => $rows]);
    exit;
}

/* ================= GET SINGLE ERROR ================= */
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $conn->prepare("
        SELECT e.*, u.email AS user_email
        FROM error_log e
        LEFT JOIN user u ON e.user_id = u.id
        WHERE e.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    if (!$r) {
        echo json_encode(["status" => "error", "message" => "Not found"]);
        exit;
    }
    echo json_encode($r);
    exit;
}

/* ================= CLEAR ERRORS ================= */
if ($action === 'clear') {
    $days = max((int)($_GET['older_than_days'] ?? 30), 1);
    $q = $conn->query("DELETE FROM error_log WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
    log_audit($conn, $_SESSION['user_id'] ?? 0, 'error_log_cleared', 'system', 'error_log', null, ['older_than_days' => $days]);
    echo json_encode(["status" => "success", "message" => "Errors older than $days days cleared"]);
    exit;
}

if ($action === 'delete_all') {
    if ($conn->query("TRUNCATE TABLE error_log")) {
        log_audit($conn, $_SESSION['user_id'] ?? 0, 'error_log_truncated', 'system', 'error_log');
        echo json_encode(["status" => "success", "message" => "All error logs cleared"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to clear"]);
    }
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
