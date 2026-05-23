<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
require __DIR__ . '/../config.php';

$result = [];
$result['session'] = isset($_SESSION['user_id']) ? 'logged in' : 'not logged in';
$result['php_version'] = phpversion();

// Test query
$q = $conn->query("SELECT COUNT(*) as cnt FROM settings");
if ($q) {
    $result['settings_table'] = 'exists, count: ' . $q->fetch_assoc()['cnt'];
} else {
    $result['settings_table'] = 'missing or error: ' . $conn->error;
}

echo json_encode($result);
