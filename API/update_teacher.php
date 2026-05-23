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
$userId = (int) ($data['user_id'] ?? 0);
$email = trim($data['email'] ?? '');
$fullname = trim($data['fullname'] ?? '');
$phone = trim($data['phone'] ?? '');
$dob = trim($data['dob'] ?? '');
$identitynumber = trim($data['identitynumber'] ?? '');
$race = trim($data['race'] ?? '');
$address = trim($data['address'] ?? '');
$gender = trim($data['gender'] ?? '');
$hireDate = trim($data['hire_date'] ?? '');

if ($userId <= 0 || $email === '' || $fullname === '' || $dob === '' || $race === '' || $gender === '' || $hireDate === '') {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please complete all required teacher fields"]);
    exit;
}

$check = $conn->prepare("SELECT id FROM user WHERE email = ? AND id != ?");
$check->bind_param("si", $email, $userId);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["status" => "error", "message" => "Email already exists"]);
    exit;
}

$conn->begin_transaction();
try {
    $stmt1 = $conn->prepare("UPDATE user SET email = ? WHERE id = ?");
    $stmt1->bind_param("si", $email, $userId);
    $stmt1->execute();

    $stmt2 = $conn->prepare("
        UPDATE teachers
        SET fullname = ?, dob = ?, phone = ?, address = ?, race = ?, identitynumber = ?, gender = ?, hire_date = ?
        WHERE user_id = ?
    ");
    $stmt2->bind_param("ssssssssi", $fullname, $dob, $phone, $address, $race, $identitynumber, $gender, $hireDate, $userId);
    $stmt2->execute();

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Teacher updated successfully"]);
} catch (Throwable $e) {
    $conn->rollback();
    log_error($conn, $e);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update teacher"]);
}
?>
