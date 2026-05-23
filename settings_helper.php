<?php
/**
 * Settings Helper Functions
 * Usage: 
 *   require_once 'settings_helper.php';
 *   $systemEnabled = get_setting('system_enabled');
 *   if ($systemEnabled === 'off') { ... }
 */

require_once __DIR__ . '/config.php';

function get_setting($key, $default = null) {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? ($row['setting_value'] ?: $default) : $default;
}

function set_setting($key, $value) {
    global $conn;
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);
    return $stmt->execute();
}

function is_module_enabled($module) {
    return get_setting($module . '_module_enabled') === 'enabled';
}

function is_registration_open($type = 'student') {
    $key = $type . '_registration_enabled';
    if (get_setting($key) !== 'open') return false;
    
    $start = get_setting('registration_start_date');
    $end = get_setting('registration_end_date');
    $now = date('Y-m-d');
    
    if ($start && $now < $start) return false;
    if ($end && $now > $end) return false;
    
    return true;
}