<?php
ob_start();
header("Content-Type: application/json");
error_reporting(0);
ini_set('display_errors', 0);

register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "Server error"]);
    }
});

session_start();
require __DIR__ . '/../config.php';

$admin_actions = ['save', 'save_one'];
if (in_array($_GET['action'] ?? '', $admin_actions)) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','it_technician'])) {
        ob_end_clean();
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied"]);
        exit;
    }
} elseif (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Not authenticated"]);
    exit;
}

try {
    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) UNIQUE,
        setting_value TEXT,
        category VARCHAR(50),
        setting_type VARCHAR(50),
        description TEXT,
        options TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) { log_error($conn, $e); }

try {
    $conn->query("INSERT INTO settings (setting_key, setting_value, category, setting_type, description, options) VALUES 
        ('student_registration_enabled', 'closed', 'registration', 'select', 'Enable student self-registration', '{\"open\":\"Open\",\"closed\":\"Closed\"}'),
        ('teacher_registration_enabled', 'closed', 'registration', 'select', 'Enable teacher self-registration', '{\"open\":\"Open\",\"closed\":\"Closed\"}'),
        ('registration_start_date', '" . date('Y-m-d') . "', 'registration', 'date', 'Registration start date', ''),
        ('registration_end_date', '" . date('Y-12-31') . "', 'registration', 'date', 'Registration end date', ''),
        ('teacher_ratings_enabled', 'off', 'modules', 'select', 'Enable teacher ratings by students', '{\"on\":\"On\",\"off\":\"Off\"}'),
        ('max_login_attempts', '5', 'security', 'number', 'Max failed login attempts before account lock', ''),
        ('block_reasons', 'Violation of school policy\nUnauthorized access attempt\nAccount compromise\nSuspicious activity\nHarassment or abuse\nOther', 'security', 'textarea', 'Block reasons (one per line) for the account management dropdown', '')
    ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), category=VALUES(category), setting_type=VALUES(setting_type), description=VALUES(description), options=VALUES(options)");
} catch (Exception $e) { log_error($conn, $e); }

$action = $_GET['action'] ?? '';

try {

/* ================= GET ALL SETTINGS ================= */
if ($action === 'get') {
    $result = $conn->query("SELECT * FROM settings ORDER BY category, id");
    $settings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if ($row['options']) {
                $row['options'] = json_decode($row['options'], true);
            }
            $settings[] = $row;
        }
    }
    ob_end_clean();
    echo json_encode($settings);
    exit();
}

/* ================= GET SETTING BY KEY ================= */
if ($action === 'get_one') {
    $key = $_GET['key'] ?? '';
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    ob_end_clean();
    echo json_encode(["value" => $row['setting_value'] ?? null]);
    exit();
}

/* ================= SAVE ALL SETTINGS ================= */
if ($action === 'save') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $updated = 0;
    foreach ($data as $key => $value) {
        $keyEsc = $conn->real_escape_string($key);
        $valEsc = $conn->real_escape_string($value);
        $conn->query("INSERT INTO settings (setting_key, setting_value) VALUES ('$keyEsc', '$valEsc') ON DUPLICATE KEY UPDATE setting_value='$valEsc', updated_at=NOW()");
        $updated++;
    }
    
    ob_end_clean();
    echo json_encode(["status" => "success", "message" => "$updated settings saved"]);
    exit();
}

/* ================= SAVE SINGLE SETTING ================= */
if ($action === 'save_one') {
    $data = json_decode(file_get_contents("php://input"), true);
    $key = $data['key'] ?? '';
    $value = $data['value'] ?? '';
    
    $stmt = $conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->bind_param("ss", $value, $key);
    $stmt->execute();
    
    log_audit($conn, $_SESSION['user_id'] ?? 0, 'setting_updated', 'system', 'setting', 0, ['key' => $key, 'value_preview' => substr($value, 0, 50)]);
    ob_end_clean();
    echo json_encode(["status" => "success"]);
    exit();
}

/* ================= GET CATEGORIES ================= */
if ($action === 'categories') {
    $result = $conn->query("SELECT DISTINCT category FROM settings ORDER BY category");
    $categories = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row['category'];
        }
    }
    ob_end_clean();
    echo json_encode($categories);
    exit();
}

ob_end_clean();
echo json_encode(["status" => "error", "message" => "Invalid action"]);

} catch (Throwable $e) {
    log_error($conn, $e);
    ob_end_clean();
    echo json_encode(["status" => "error", "message" => "Server error"]);
}