<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'hr_manager'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Access denied"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');
$password = trim($data['password'] ?? '');
$fullname = trim($data['fullname'] ?? '');
$phone = trim($data['phone'] ?? '');
$dob = trim($data['dob'] ?? '');
$identitynumber = trim($data['identitynumber'] ?? '');
$race = trim($data['race'] ?? '');
$address = trim($data['address'] ?? '');
$gender = trim($data['gender'] ?? '');
$hireDate = trim($data['hire_date'] ?? '');

if ($email === '' || $password === '' || $fullname === '' || $dob === '' || $race === '' || $gender === '' || $hireDate === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please complete all required teacher fields"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid email address"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM user WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "Email already exists"]);
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$employeeNumber = "EMP" . time() . rand(10, 99);

$conn->begin_transaction();
try {
    $stmt1 = $conn->prepare("INSERT INTO user (email, password, status) VALUES (?, ?, 'approved')");
    $stmt1->bind_param("ss", $email, $hashedPassword);
    $stmt1->execute();
    $userId = $conn->insert_id;

    $stmt2 = $conn->prepare("
        INSERT INTO teachers (
            user_id, fullname, dob, employee_number, phone, address, race, identitynumber, gender, hire_date
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt2->bind_param("isssssssss", $userId, $fullname, $dob, $employeeNumber, $phone, $address, $race, $identitynumber, $gender, $hireDate);
    $stmt2->execute();

    $roleId = 2;
    $stmt3 = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
    $stmt3->bind_param("ii", $userId, $roleId);
    $stmt3->execute();

    $conn->commit();
    log_audit($conn, $_SESSION['user_id'], 'teacher_registered', 'registration', 'teacher', $userId, ['fullname' => $fullname]);
    echo json_encode(["status" => "success", "message" => "Teacher registered successfully"]);
} catch (Throwable $e) {
    $conn->rollback();
    log_error($conn, $e);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to register teacher"]);
}
?>
