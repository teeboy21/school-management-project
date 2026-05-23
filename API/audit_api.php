<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_audit_log') {
    $limit = (int) ($_GET['limit'] ?? 100);
    $module = $_GET['module'] ?? '';
    $action_filter = $_GET['action_filter'] ?? '';

    $sql = "SELECT al.*, COALESCE(t.fullname, e.fullname, s.fullname) as user_name
            FROM audit_log al
            LEFT JOIN user u ON al.user_id = u.id
            LEFT JOIN teachers t ON t.user_id = u.id
            LEFT JOIN employees e ON e.user_id = u.id
            LEFT JOIN students s ON s.user_id = u.id
            WHERE 1=1";
    if ($module) $sql .= " AND al.module = '$module'";
    if ($action_filter) $sql .= " AND al.action = '$action_filter'";
    $sql .= " ORDER BY al.created_at DESC LIMIT $limit";

    $result = $conn->query($sql);
    if (!$result) {
        echo json_encode(["error" => "Query failed: " . $conn->error]);
        exit;
    }
    $logs = [];
    while ($row = $result->fetch_assoc()) $logs[] = $row;
    echo json_encode($logs);
    exit;
}

echo json_encode(["error" => "Invalid action"]);
