<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../email_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        "status" => "error",
        "message" => "Please log in first"
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$userId = (int) $_SESSION['user_id'];
$fullnames = trim($data['fullnames'] ?? '');
$surname = trim($data['surname'] ?? '');
$race = trim($data['race'] ?? '');
$dob = trim($data['dob'] ?? '');
$gender = trim($data['gender'] ?? '');
$phone = trim($data['phone'] ?? '');
$kinphone = trim($data['kinphone'] ?? '');
$kinrelationship = trim($data['kinrelationship'] ?? '');
$identification = trim($data['identification'] ?? '');
$gradeApplying = trim($data['grade_applying'] ?? '');
$address = trim($data['address'] ?? '');

if ($fullnames === '' || $surname === '' || $race === '' || $dob === '' || $gender === '') {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Please complete all required student fields"
    ]);
    exit;
}

$fullname = trim($fullnames . ' ' . $surname);

$checkExisting = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$checkExisting->bind_param("i", $userId);
$checkExisting->execute();
$checkExisting->store_result();

if ($checkExisting->num_rows > 0) {
    http_response_code(409);
    echo json_encode([
        "status" => "error",
        "message" => "Student profile already exists"
    ]);
    exit;
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("
        INSERT INTO students (
            user_id,
            fullname,
            race,
            dob,
            gender,
            phone,
            identity_number,
            grade,
            address
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "issssssss",
        $userId,
        $fullname,
        $race,
        $dob,
        $gender,
        $phone,
        $identification,
        $gradeApplying,
        $address
    );
    $stmt->execute();

    $studentId = $stmt->insert_id;

    $stmt2 = $conn->prepare("
        INSERT INTO parents (student_id, phone, relationship)
        VALUES (?, ?, ?)
    ");
    $stmt2->bind_param("iss", $studentId, $kinphone, $kinrelationship);
    $stmt2->execute();

    $stmt3 = $conn->prepare("UPDATE user SET status = 'profile_completed' WHERE id = ?");
    $stmt3->bind_param("i", $userId);
    $stmt3->execute();

    $conn->commit();
    log_audit($conn, $userId, 'student_profile_completed', 'registration', 'student', $userId, ['fullname' => $fullname]);

    $user_res = $conn->query("SELECT email FROM user WHERE id = $userId");
    $user_row = $user_res->fetch_assoc();
    if ($user_row) {
        email_student_registration_complete($conn, $user_row['email'], $fullname);
    }

    echo json_encode([
        "status" => "success",
        "message" => "Profile completed successfully"
    ]);
} catch (Throwable $e) {
    $conn->rollback();
    log_error($conn, $e);
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Failed to save profile"
    ]);
}
?>
