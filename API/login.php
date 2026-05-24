<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);
error_reporting(E_ALL);

require __DIR__ . '/../config.php';

if (!isset($conn)) {
    echo json_encode([
        "status" => "error",
        "message" => "Database connection not initialized"
    ]);
    exit();
}

session_start();

try {
    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data["email"] ?? null;
    $password = $data["password"] ?? null;

    if (empty($email) || empty($password)) {
        echo json_encode([
            "status" => "error",
            "message" => "Email and password are required"
        ]);
        exit();
    }

    // Get user with role from roles table via user_roles
    $stmt = $conn->prepare("
        SELECT u.id, u.email, u.status, u.locked, u.password, u.login_attempts, u.locked_reason, u.rejection_reason, r.role_name 
        FROM user u 
        JOIN user_roles ur ON u.id = ur.user_id 
        JOIN roles r ON ur.role_id = r.id 
        WHERE u.email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        log_audit($conn, 0, 'login_failed', 'auth', null, null, ['email' => $email, 'reason' => 'User not found']);
        echo json_encode([
            "status" => "error",
            "message" => "User not found"
        ]);
        exit();
    }

    $user = $result->fetch_assoc();
    $role = $user['role_name'];

    // Get max login attempts from settings
    $max_attempts = 5;
    $att_r = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'max_failed_login_attempts'");
    if ($att_r && ($att_row = $att_r->fetch_assoc())) $max_attempts = (int)$att_row['setting_value'];

    // Check if account is locked
    if (!empty($user['locked'])) {
        $reason = $user['locked_reason'] ?: 'Too many failed login attempts';
        log_audit($conn, $user['id'], 'login_blocked', 'auth', null, null, ['email' => $email, 'reason' => "Account locked: $reason"]);
        echo json_encode(["status" => "error", "message" => "Account is locked. Reason: $reason. Contact administration."]);
        exit();
    }

    // Check system status for non-admin users
    if ($role !== 'admin') {
        $sysStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'system_enabled'");
        $sysStmt->execute();
        $sysResult = $sysStmt->get_result();
        $sysRow = $sysResult->fetch_assoc();
        
        if ($sysRow && $sysRow['setting_value'] === 'off') {
            $msgStmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_message'");
            $msgStmt->execute();
            $msgResult = $msgStmt->get_result();
            $msgRow = $msgResult->fetch_assoc();
            
            $messages = json_decode($msgRow['setting_value'] ?? '{}', true);
            $message = $messages['maintenance_default'] ?? 'The system is currently under maintenance.';
            
            log_audit($conn, $user['id'], 'login_blocked', 'auth', null, null, ['email' => $email, 'reason' => 'System disabled']);
            echo json_encode([
                "status" => "error",
                "message" => $message,
                "system_off" => true
            ]);
            exit();
        }
    }

    if (!password_verify($password, $user['password'])) {
        $attempts = (int)($user['login_attempts'] ?? 0) + 1;
        $q = $conn->query("UPDATE user SET login_attempts = $attempts WHERE id = {$user['id']}");
        if (!$q) { $conn->query("UPDATE user SET locked = 1 WHERE id = {$user['id']}"); }
        log_audit($conn, $user['id'], 'login_failed', 'auth', null, null, ['email' => $email, 'reason' => 'Incorrect password', 'attempts' => $attempts]);
        if ($attempts >= $max_attempts) {
            $q2 = $conn->query("UPDATE user SET locked = 1, locked_at = NOW(), locked_reason = 'Too many failed login attempts' WHERE id = {$user['id']}");
            if (!$q2) { $conn->query("UPDATE user SET locked = 1 WHERE id = {$user['id']}"); }
            log_audit($conn, $user['id'], 'login_blocked', 'auth', null, null, ['email' => $email, 'reason' => 'Account locked after ' . $attempts . ' failed attempts']);
            $name_r = $conn->query("SELECT COALESCE(s.fullname, t.fullname, e.fullname, 'User') AS fullname FROM user u LEFT JOIN students s ON s.user_id = u.id LEFT JOIN teachers t ON t.user_id = u.id LEFT JOIN employees e ON e.user_id = u.id WHERE u.id = {$user['id']}");
            $name_row = $name_r ? $name_r->fetch_assoc() : null;
            $fullname = $name_row['fullname'] ?? 'User';
            require_once __DIR__ . '/../email_helper.php';
            email_account_blocked($conn, $email, $fullname, 'Too many failed login attempts');
            echo json_encode(["status" => "error", "message" => "Account has been locked due to too many failed login attempts. Contact administration."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Incorrect password. " . ($max_attempts - $attempts) . " attempt(s) remaining."]);
        }
        exit();
    }

    // Handle student status - allow account_created to login for profile completion
    if ($role === 'student' && $user['status'] !== 'approved' && $user['status'] !== 'account_created') {
        $message = match($user['status']) {
            'profile_completed' => 'Your profile is pending admin approval. Please wait or contact the administration.',
            default => 'Your account status is: ' . $user['status'] . '. Please contact administration.'
        };
        
        log_audit($conn, $user['id'], 'login_blocked', 'auth', null, null, ['email' => $email, 'reason' => 'Student status: ' . $user['status']]);
        echo json_encode([
            "status" => "error",
            "message" => $message,
            "accntstatus" => $user['status'],
            "role" => $role
        ]);
        exit();
    }

    // Block pending employees from logging in
    if ($role !== 'student' && $user['status'] === 'pending') {
        log_audit($conn, $user['id'], 'login_blocked', 'auth', null, null, ['email' => $email, 'reason' => 'Account pending approval']);
        echo json_encode([
            "status" => "error",
            "message" => "Your account is pending approval. Please wait for the principal or admin to approve your account.",
            "accntstatus" => $user['status'],
            "role" => $role
        ]);
        exit();
    }

    // Block rejected users
    if ($user['status'] === 'rejected') {
        log_audit($conn, $user['id'], 'login_blocked', 'auth', null, null, ['email' => $email, 'reason' => 'Account rejected']);
        echo json_encode([
            "status" => "error",
            "message" => "Your account has been rejected. Reason: " . ($user['rejection_reason'] ?? 'Not specified') . ". Contact administration.",
            "accntstatus" => $user['status'],
            "role" => $role
        ]);
        exit();
    }

    // Store session data
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $role;
    $_SESSION['status'] = $user['status'];

    // Get additional profile data
    if ($role === 'teacher') {
        $profileStmt = $conn->prepare("SELECT fullname, employee_number FROM teachers WHERE user_id = ?");
        $profileStmt->bind_param("i", $user['id']);
        $profileStmt->execute();
        $profile = $profileStmt->get_result()->fetch_assoc();
        $_SESSION['fullname'] = $profile['fullname'] ?? $user['email'];
        $_SESSION['employee_number'] = $profile['employee_number'] ?? '';
    } elseif ($role === 'employee' || $role === 'hr_manager' || $role === 'finance_manager' || $role === 'it_technician') {
        $profileStmt = $conn->prepare("SELECT fullname, employee_number, department, job_title FROM employees WHERE user_id = ?");
        $profileStmt->bind_param("i", $user['id']);
        $profileStmt->execute();
        $profile = $profileStmt->get_result()->fetch_assoc();
        $_SESSION['fullname'] = $profile['fullname'] ?? $user['email'];
        $_SESSION['employee_number'] = $profile['employee_number'] ?? '';
        $_SESSION['department'] = $profile['department'] ?? '';
        $_SESSION['job_title'] = $profile['job_title'] ?? '';
    } elseif ($role === 'student') {
        $profileStmt = $conn->prepare("SELECT fullname, student_number FROM students WHERE user_id = ?");
        $profileStmt->bind_param("i", $user['id']);
        $profileStmt->execute();
        $profile = $profileStmt->get_result()->fetch_assoc();
        $_SESSION['fullname'] = $profile['fullname'] ?? $user['email'];
        $_SESSION['student_number'] = $profile['student_number'] ?? '';
    } else {
        $_SESSION['fullname'] = $user['email'];
    }

    // Determine dashboard URL based on role
    $dashboard_url = match($role) {
        'teacher' => 'teacherdashboard.php',
        'student' => 'userdashboard.php',
        'hr_manager' => 'hr_dashboard.php',
        'finance_manager' => 'finance_dashboard.php',
        'principal' => 'principal_dashboard.php',
        'it_technician' => 'it_dashboard.php',
        'employee' => 'hr_dashboard.php',
        default => 'admindashboard.php'
    };

    // Reset login attempts and clear lock state on success
    if (($user['login_attempts'] ?? 0) > 0 || !empty($user['locked'])) {
        $q = $conn->query("UPDATE user SET locked = 0, login_attempts = 0, locked_at = NULL, locked_reason = NULL WHERE id = {$user['id']}");
        if (!$q) { $conn->query("UPDATE user SET login_attempts = 0 WHERE id = {$user['id']}"); }
    }

    log_audit($conn, $user['id'], 'login_success', 'auth', null, null, ['email' => $email, 'role' => $role]);

    echo json_encode([
        "status" => "success",
        "accntstatus" => $user['status'],
        "message" => "Login successful",
        "role" => $role,
        "dashboard_url" => $dashboard_url
    ]);

} catch (Throwable $e) {
    log_error($conn, $e);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}



