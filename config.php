<?php
$host = "localhost";
$user ="root";
$pass ="";
$db = "users_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

/**
 * Log an action to the audit trail.
 * Call from any API/page that includes config.php.
 */
function log_audit($conn, $user_id, $action, $module, $ref_type = null, $ref_id = null, $details = []) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_id = (int)($user_id ?? 0);
    $ref_id = $ref_id ? (int)$ref_id : 'NULL';
    $action = $conn->real_escape_string($action);
    $module = $module ? $conn->real_escape_string($module) : 'NULL';
    $ref_type = $ref_type ? "'" . $conn->real_escape_string($ref_type) . "'" : 'NULL';
    $details = $conn->real_escape_string(json_encode($details));
    $conn->query("INSERT INTO audit_log (user_id, action, module, reference_type, reference_id, details, ip_address)
                  VALUES ($user_id, '$action', '$module', $ref_type, $ref_id, '$details', '$ip')");
}

/**
 * Log a caught exception to the error_log table.
 */
function log_error($conn, $e, $user_id = null) {
    $msg = $conn->real_escape_string($e->getMessage());
    $file = $conn->real_escape_string($e->getFile());
    $line = (int)$e->getLine();
    $trace = $conn->real_escape_string($e->getTraceAsString());
    $url = $conn->real_escape_string(($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''));
    $method = $conn->real_escape_string($_SERVER['REQUEST_METHOD'] ?? '');
    $uid = $user_id ? (int)$user_id : (int)($_SESSION['user_id'] ?? 0);
    $data = $conn->real_escape_string(json_encode($_POST ?: $_GET ?: []));
    $conn->query("INSERT INTO error_log (user_id, error_message, error_file, error_line, error_trace, request_url, request_method, request_data)
                  VALUES ($uid, '$msg', '$file', $line, '$trace', '$url', '$method', '$data')");
}

/**
 * Get a school info setting by key. Cached in static var for one query per request.
 */
function get_school_info($conn, $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $r = @$conn->query("SELECT setting_key, setting_value FROM school_info");
        if ($r && $r->num_rows > 0) {
            while ($row = $r->fetch_assoc()) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        }
    }
    return $cache[$key] ?? $default;
}