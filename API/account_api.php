<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../email_helper.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'it_technician'])) {
    echo json_encode(["error" => "Access denied"]);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = (int) $_SESSION['user_id'];

if ($action === 'list_accounts') {
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $sql = "SELECT u.id, u.email, u.status, u.locked, u.login_attempts, u.locked_at, u.locked_reason,
                   COALESCE(t.fullname, e.fullname, s.fullname) as fullname,
                   r.role_name
            FROM user u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r ON r.id = ur.role_id
            LEFT JOIN teachers t ON t.user_id = u.id
            LEFT JOIN employees e ON e.user_id = u.id
            LEFT JOIN students s ON s.user_id = u.id
            WHERE 1=1";
    if ($search) {
        $search = $conn->real_escape_string($search);
        $sql .= " AND (u.email LIKE '%$search%' OR COALESCE(t.fullname, e.fullname, s.fullname) LIKE '%$search%')";
    }
    if ($status_filter) {
        $status_filter = $conn->real_escape_string($status_filter);
        if ($status_filter === 'locked') $sql .= " AND u.locked = 1";
        elseif ($status_filter === 'active') $sql .= " AND u.locked = 0 AND u.status NOT IN ('pending','rejected','account_created','profile_completed')";
        elseif ($status_filter === 'pending') $sql .= " AND u.status IN ('pending','account_created','profile_completed')";
    }
    $sql .= " ORDER BY u.locked DESC, u.status DESC, fullname ASC LIMIT 200";
    $accounts = [];
    try {
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) $accounts[] = $row;
        }
    } catch (Throwable $e) {
        log_error($conn, $e);
        $base = "SELECT u.id, u.email, u.status, 0 AS locked, 0 AS login_attempts, NULL AS locked_at, NULL AS locked_reason,
                       COALESCE(t.fullname, e.fullname, s.fullname) AS fullname, r.role_name
                FROM user u
                JOIN user_roles ur ON ur.user_id = u.id
                JOIN roles r ON r.id = ur.role_id
                LEFT JOIN teachers t ON t.user_id = u.id
                LEFT JOIN employees e ON e.user_id = u.id
                LEFT JOIN students s ON s.user_id = u.id
                WHERE 1=1";
        if ($search) {
            $s = $conn->real_escape_string($search);
            $base .= " AND (u.email LIKE '%$s%' OR COALESCE(t.fullname, e.fullname, s.fullname) LIKE '%$s%')";
        }
        if ($status_filter) {
            $sf = $conn->real_escape_string($status_filter);
            if ($sf === 'locked') $base .= " AND u.locked = 1";
            elseif ($sf === 'active') $base .= " AND u.locked = 0 AND u.status NOT IN ('pending','rejected','account_created','profile_completed')";
            elseif ($sf === 'pending') $base .= " AND u.status IN ('pending','account_created','profile_completed')";
        }
        $base .= " ORDER BY u.locked DESC, u.status DESC, fullname ASC LIMIT 200";
        $result2 = $conn->query($base);
        if ($result2) {
            while ($row = $result2->fetch_assoc()) $accounts[] = $row;
        }
    }
    echo json_encode($accounts);
    exit;
}

if ($action === 'block_account') {
    $data = json_decode(file_get_contents("php://input"), true);
    $target_id = (int)($data['user_id'] ?? 0);
    if ($target_id <= 0) {
        echo json_encode(["status" => "error", "error" => "Invalid user ID"]);
        exit;
    }
    $reason = $conn->real_escape_string($data['reason'] ?? 'Manually blocked by administrator');
    $q1 = $conn->query("UPDATE user SET locked = 1, locked_at = NOW(), locked_reason = '$reason' WHERE id = $target_id");
    if (!$q1 || $conn->affected_rows === 0) {
        $q2 = $conn->query("UPDATE user SET locked = 1 WHERE id = $target_id");
        if (!$q2 || $conn->affected_rows === 0) {
            echo json_encode(["status" => "error", "error" => "Block failed: " . $conn->error]);
            exit;
        }
    }
    log_audit($conn, $user_id, 'account_blocked', 'auth', 'user', $target_id, ['reason' => $reason]);
    echo json_encode(["status" => "success", "message" => "Account blocked"]);
    exit;
}

if ($action === 'unblock_account') {
    $data = json_decode(file_get_contents("php://input"), true);
    $target_id = (int)($data['user_id'] ?? 0);
    if ($target_id <= 0) {
        echo json_encode(["status" => "error", "error" => "Invalid user ID"]);
        exit;
    }
    $reason = $conn->real_escape_string($data['reason'] ?? 'Unblocked by administrator');
    $q1 = $conn->query("UPDATE user SET locked = 0, login_attempts = 0, locked_at = NULL, locked_reason = NULL WHERE id = $target_id");
    if (!$q1 || $conn->affected_rows === 0) {
        $q2 = $conn->query("UPDATE user SET locked = 0 WHERE id = $target_id");
        if (!$q2 || $conn->affected_rows === 0) {
            echo json_encode(["status" => "error", "error" => "Unblock failed: " . $conn->error]);
            exit;
        }
    }
    log_audit($conn, $user_id, 'account_unblocked', 'auth', 'user', $target_id, ['reason' => $reason]);

    $u = $conn->query("SELECT email, COALESCE(t.fullname, e.fullname, s.fullname, email) AS fullname FROM user u LEFT JOIN teachers t ON t.user_id = u.id LEFT JOIN employees e ON e.user_id = u.id LEFT JOIN students s ON s.user_id = u.id WHERE u.id = $target_id")->fetch_assoc();
    if ($u) email_account_unblocked($conn, $u['email'], $u['fullname']);

    echo json_encode(["status" => "success", "message" => "Account unblocked"]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);
