<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require __DIR__ . '/../config.php';

    if (!isset($conn)) {
        echo json_encode(["status" => "error", "message" => "Database connection not initialized"]);
        exit();
    }

    // Check if registration is open
    $checkReg = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'student_registration_enabled'");
    $regRow = ($checkReg && $checkReg->num_rows > 0) ? $checkReg->fetch_assoc() : null;
    if (!$regRow || $regRow['setting_value'] !== 'open') {
        echo json_encode(["status" => "error", "message" => "Student registration is currently closed."]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data["email"] ?? '';
    $password = $data["password"] ?? '';

    // Validation
    if (empty($email) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Email and password are required."]);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Invalid email format."]);
        exit();
    }

    if (strlen($password) < 8) {
        echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters long."]);
        exit();
    }

    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSymbol = preg_match('/[^A-Za-z0-9]/', $password);

    if (!$hasUpper || !$hasLower || !$hasNumber || !$hasSymbol) {
        echo json_encode(["status" => "error", "message" => "Password must include uppercase, lowercase, number, and symbol."]);
        exit();
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM user WHERE email = ?");
    if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email already exists."]);
        exit();
    }

    // Create user with account_created status
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $status = 'account_created';

    $stmt = $conn->prepare("INSERT INTO user (email, password, status) VALUES (?, ?, ?)");
    if (!$stmt) throw new Exception("Insert prepare failed: " . $conn->error);

    $stmt->bind_param("sss", $email, $hashed_password, $status);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        
        // Assign student role (role_id = 3)
        $role_id = 3;
        $stmt2 = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt2->bind_param("ii", $user_id, $role_id);
        $stmt2->execute();

        log_audit($conn, $user_id, 'student_registered', 'registration', 'student', $user_id, ['email' => $email]);
        echo json_encode(["status" => "success", "message" => "Account created successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create account."]);
    }
} catch (Throwable $e) {
    log_error($conn, $e);
    echo json_encode(["status" => "error", "message" => "Server error: " . $e->getMessage()]);
}
