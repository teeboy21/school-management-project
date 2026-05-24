<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.html");
    exit();
}

$message = '';
$message_type = 'success';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email'] ?? '');
    $rawPassword = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $fullname = trim(($_POST['fullnames'] ?? '') . " " . ($_POST['surname'] ?? ''));
    $race = trim($_POST['race'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $kinphone = trim($_POST['kinphone'] ?? '');
    $kinrelationship = trim($_POST['kinrelationship'] ?? '');
    $identification = trim($_POST['identification'] ?? '');
    $grade_applying = trim($_POST['grade_applying'] ?? '');
    $stream = trim($_POST['Stream'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($email === '' || $rawPassword === '' || $confirmPassword === '' || $fullname === '' || $dob === '' || $gender === '' || $grade_applying === '') {
        $message = "Please complete all required fields.";
        $message_type = "warning";
    } elseif ($rawPassword !== $confirmPassword) {
        $message = "Passwords do not match.";
        $message_type = "warning";
    } else {
        $check = $conn->prepare("SELECT id FROM user WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "That email address already exists.";
            $message_type = "warning";
        } else {
            $password = password_hash($rawPassword, PASSWORD_DEFAULT);
            $conn->begin_transaction();

            try {
                $stmt = $conn->prepare("INSERT INTO user (email, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $email, $password);
                $stmt->execute();
                $user_id = $stmt->insert_id;

                $role_id = 3;
                $stmtRole = $conn->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                $stmtRole->bind_param("ii", $user_id, $role_id);
                $stmtRole->execute();

                $stmt2 = $conn->prepare("
                    INSERT INTO students (user_id, fullname, race, dob, gender, phone, identity_number, grade, stream, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt2->bind_param("isssssssss", $user_id, $fullname, $race, $dob, $gender, $phone, $identification, $grade_applying, $stream, $address);
                $stmt2->execute();

                $studentid = $stmt2->insert_id;

                $stmt3 = $conn->prepare("INSERT INTO parents (student_id, phone, relationship) VALUES (?, ?, ?)");
                $stmt3->bind_param("iss", $studentid, $kinphone, $kinrelationship);
                $stmt3->execute();

                $stmt4 = $conn->prepare("UPDATE user SET status = 'profile_completed' WHERE id = ?");
                $stmt4->bind_param("i", $user_id);
                $stmt4->execute();

                $conn->commit();

                // Auto-assign student number and class
                $sid = (int)$stmt2->insert_id;
                if ($sid) {
                    $updates = [];
                    do {
                        $student_number = 'STU' . mt_rand(100000000000, 999999999999);
                        $chk = $conn->query("SELECT id FROM students WHERE student_number = '$student_number'");
                    } while ($chk && $chk->num_rows > 0);
                    $updates[] = "student_number = '" . $conn->real_escape_string($student_number) . "'";

                    $grade_id = null;
                    if (is_numeric($grade_applying)) {
                        $grade_id = intval($grade_applying);
                    } else {
                        $g = $conn->query("SELECT id FROM grades WHERE name = '" . $conn->real_escape_string($grade_applying) . "'")->fetch_assoc();
                        if ($g) $grade_id = (int)$g['id'];
                    }
                    if ($grade_id) {
                        $c = $conn->query("SELECT id FROM classes WHERE grade_id = $grade_id LIMIT 1")->fetch_assoc();
                        if ($c) $updates[] = "class_id = {$c['id']}";
                    }

                    if (!empty($updates)) {
                        $conn->query("UPDATE students SET " . implode(', ', $updates) . " WHERE id = $sid");
                    }
                }

                $message = "Student registered successfully.";
            } catch (Throwable $e) {
                $conn->rollback();
                log_error($conn, $e);
                $message = "Failed to register student.";
                $message_type = "danger";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Student</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Register Student</div>
                <div class="app-header-subtitle">Create a student account and admission profile in one step.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <?php if ($message !== ''): ?>
                <div class="alert alert-<?= $message_type === 'warning' ? 'warning' : ($message_type === 'danger' ? 'danger' : 'success') ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="panel">
                <form method="POST" class="form-grid">
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div>
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <div>
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <div></div>
                    <div>
                        <label>First Names</label>
                        <input type="text" name="fullnames" required>
                    </div>
                    <div>
                        <label>Surname</label>
                        <input type="text" name="surname" required>
                    </div>
                    <div>
                        <label>Race</label>
                        <select name="race">
                            <option value="">Select Race</option>
                            <option value="white">White</option>
                            <option value="black">Black</option>
                            <option value="asian">Asian</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label>Date of Birth</label>
                        <input type="date" name="dob" required>
                    </div>
                    <div>
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label>Phone Number</label>
                        <input type="text" name="phone">
                    </div>
                    <div>
                        <label>Parent / Guardian Phone</label>
                        <input type="text" name="kinphone">
                    </div>
                    <div>
                        <label>Relationship</label>
                        <select name="kinrelationship">
                            <option value="">Select Relationship</option>
                            <option value="parent">Parent</option>
                            <option value="guardian">Guardian</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label>Identification</label>
                        <input type="text" name="identification">
                    </div>
                    <div>
                        <label>Grade Applying</label>
                        <select name="grade_applying" required>
                            <option value="">Select Grade</option>
                            <?php
                            $grades_query = $conn->query("SELECT id, name FROM grades ORDER BY name");
                            if ($grades_query) {
                                while ($g = $grades_query->fetch_assoc()) {
                                    echo '<option value="' . (int)$g['id'] . '">' . htmlspecialchars($g['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label>Stream</label>
                        <select name="Stream">
                            <option value="">Select Stream</option>
                            <option value="science">Science</option>
                            <option value="arts">Arts</option>
                            <option value="commerce">Commerce</option>
                        </select>
                    </div>
                    <div class="full">
                        <label>Address</label>
                        <textarea name="address"></textarea>
                    </div>
                    <div class="page-actions">
                        <button type="submit" class="btn btn-primary">Submit Registration</button>
                        <a class="btn btn-secondary" href="admindashboard.php">Back To Dashboard</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
</body>
</html>
