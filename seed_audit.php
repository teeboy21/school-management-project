<?php
session_start();
require_once 'config.php';
$allowed_roles = ['admin', 'it_technician'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.html");
    exit();
}
$uid = (int)$_SESSION['user_id'];
$seed = [
    [$uid, 'login_success', 'auth', null, null, ['email' => $_SESSION['email'] ?? 'admin@school.com', 'role' => $_SESSION['role'] ?? 'admin']],
    [0, 'login_failed', 'auth', null, null, ['email' => 'unknown@test.com', 'reason' => 'User not found']],
    [$uid, 'student_registered', 'registration', 'student', 1, ['fullname' => 'John Doe']],
    [$uid, 'fee_payment', 'finance', 'payment', 100, ['student_id' => 5, 'amount' => 2500, 'method' => 'EFT']],
    [$uid, 'expense_created', 'finance', 'expense', 10, ['category' => 'Utilities', 'amount' => 3450.00, 'vendor' => 'Eskom']],
    [$uid, 'salary_saved', 'finance', 'salary', 3, ['employee_id' => 12, 'basic' => 15000]],
    [$uid, 'payroll_processed', 'finance', 'payroll', 0, ['month' => '2026-05', 'count' => 8, 'total' => 120000]],
    [$uid, 'submitted_for_approval', 'approval', 'expense', 10, ['request_id' => 1, 'amount' => 3450.00]],
    [$uid, 'fully_approved', 'approval', 'expense', 10, ['request_id' => 1, 'comment' => 'Approved']],
    [$uid, 'donation_created', 'finance', 'donation', 5, ['donor' => 'Anonymous', 'amount' => 5000, 'type' => 'cash']],
    [$uid, 'contract_saved', 'finance', 'contract', 2, ['supplier' => 'Catering Co', 'value' => 25000, 'status' => 'active']],
    [$uid, 'setting_updated', 'system', 'setting', 0, ['key' => 'school_name', 'value_preview' => get_school_info($conn, 'school_name')]],
];
$inserted = 0;
foreach ($seed as $row) {
    log_audit($conn, $row[0], $row[1], $row[2], $row[3], $row[4], $row[5]);
    $inserted++;
}
echo "<h2>Seeded $inserted audit log entries</h2>";
echo "<p><a href='audit_log.php'>View Audit Log</a></p>";
