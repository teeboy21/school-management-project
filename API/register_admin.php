<?php
header("Content-Type: application/json");
ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

try {
    require __DIR__ . '/../config.php';

    if (!isset($conn)) {
        echo json_encode(["status" => "error", "message" => "Config issue"]);
        exit();
    }

    if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        echo json_encode(["status" => "error", "message" => "Access denied"]);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $email = $data["email"] ?? null;
    $password = $data["password"] ?? null;
    $fullname = $data["fullname"] ?? null;
    $access_level = $data["access_level"] ?? 'full_admin';

    if (empty($email) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Email and password are required."]);
        exit();
    }

    if (empty($fullname)) {
        echo json_encode(["status" => "error", "message" => "Full name is required."]);
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Invalid email format."]);
        exit();
    }

    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/[0-9]/', $password);
    $hasSymbol = preg_match('/[^A-Za-z0-9]/', $password);

    if (!$hasUpper || !$hasLower || !$hasNumber || !$hasSymbol || strlen($password) < 8) {
        echo json_encode([
            "status" => "error",
            "message" => "Password must be 8+ characters and include uppercase, lowercase, number, and symbol."
        ]);
        exit();
    }

    $stmt = $conn->prepare("SELECT id FROM `user` WHERE email = ?");
    if (!$stmt) throw new Exception("Prepare failed");

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Email already exists."]);
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("INSERT INTO `user` (email, password, fullname) VALUES (?, ?, ?)");
    if (!$stmt) throw new Exception("Insert prepare failed");

    $stmt->bind_param("sss", $email, $hashedPassword, $fullname);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;

        $roleMap = [
            'full_admin' => 'admin',
            'academic_admin' => 'academic_admin',
            'operations_admin' => 'operations_admin',
            'finance_admin' => 'finance_admin'
        ];

        $roleName = $roleMap[$access_level] ?? 'admin';

        $roleStmt = $conn->prepare("SELECT id FROM roles WHERE role_name = ?");
        $roleStmt->bind_param("s", $roleName);
        $roleStmt->execute();
        $roleResult = $roleStmt->get_result();
        $roleRow = $roleResult->fetch_assoc();

        $role_id = $roleRow ? $roleRow['id'] : 1;

        $stmt2 = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        $stmt2->bind_param("ii", $user_id, $role_id);
        $stmt2->execute();

        echo json_encode(["status" => "success", "message" => "Admin account created successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create account."]);
    }
} catch (Throwable $e) {
    log_error($conn, $e);
    echo json_encode([
        "status" => "error",
        "message" => "Server error: " . $e->getMessage()
    ]);
}