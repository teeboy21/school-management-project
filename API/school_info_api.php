<?php
header("Content-Type: application/json");
error_reporting(0);
ini_set('display_errors', 0);
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','principal'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get') {
    $result = $conn->query("SELECT setting_key, setting_value FROM school_info ORDER BY id");
    $rows = [];
    while ($r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    echo json_encode($rows);
    exit;
}

if ($action === 'save') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !is_array($data)) {
        echo json_encode(["status" => "error", "message" => "Invalid data"]);
        exit;
    }
    $updated = 0;
    foreach ($data as $key => $value) {
        $k = $conn->real_escape_string($key);
        $v = $conn->real_escape_string($value);
        if ($conn->query("INSERT INTO school_info (setting_key, setting_value) VALUES ('$k', '$v') ON DUPLICATE KEY UPDATE setting_value = '$v', updated_at = NOW()")) {
            $updated++;
        }
    }
    log_audit($conn, $_SESSION['user_id'], 'school_info_updated', 'system', 'school_info', 0, ['updated' => $updated]);
    echo json_encode(["status" => "success", "message" => "$updated setting(s) saved"]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
