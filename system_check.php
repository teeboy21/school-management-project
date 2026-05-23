<?php
/**
 * System Check Middleware
 * Include at the top of any protected page (before any output)
 * Usage: require_once 'system_check.php';
 */

require_once __DIR__ . '/config.php';

function check_system_status() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return;
    }
    
    if (($_SESSION['role'] ?? '') === 'admin') {
        return;
    }
    
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'system_enabled'");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row && $row['setting_value'] === 'off') {
        session_destroy();
        header("Location: login.html?system=off");
        exit();
    }
}

check_system_status();