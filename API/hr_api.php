<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$action = $_GET['action'] ?? '';

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        echo json_encode(["status" => "error", "message" => $error['message']]);
    }
});

$public_actions = ['get_employees', 'hr_stats', 'get_contracts', 'get_donations', 'save_salary', 'delete_salary', 'record_donation', 'save_contract', 'get_salary', 'get_salaries', 'get_salary_payments'];
if (!isset($_SESSION['role']) && !in_array($action, $public_actions)) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$isAdmin = in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager', 'principal']);

function update_account_balance($amount, $type = 'add') {
    global $conn;
    $current = $conn->query("SELECT balance FROM school_account LIMIT 1")->fetch_assoc();
    $balance = $current['balance'] ?? 0;
    
    if ($type === 'add') {
        $new_balance = $balance + $amount;
    } else {
        $new_balance = $balance - $amount;
    }
    
    $conn->query("UPDATE school_account SET balance = $new_balance WHERE id = 1");
    return $new_balance;
}

/* ================= GET EMPLOYEES WITH SALARIES ================= */
if ($action === 'get_employees') {
    if (!$isAdmin && !empty($_SESSION['role'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied"]);
        exit;
    }
    
    $sql = "SELECT t.user_id as id, t.fullname, t.employee_number, u.email, 'teacher' as employee_type,
            es.basic_salary, es.housing_allowance, es.transport_allowance, es.medical_allowance,
            es.other_allowances, es.deductions, es.effective_date, es.is_active
            FROM teachers t
            LEFT JOIN user u ON u.id = t.user_id
            LEFT JOIN employee_salaries es ON t.user_id = es.employee_id AND es.is_active = 1
            WHERE 1=1
            UNION ALL
            SELECT e.user_id as id, e.fullname, e.employee_number, u.email, 'employee' as employee_type,
            es.basic_salary, es.housing_allowance, es.transport_allowance, es.medical_allowance,
            es.other_allowances, es.deductions, es.effective_date, es.is_active
            FROM employees e
            LEFT JOIN user u ON u.id = e.user_id
            LEFT JOIN employee_salaries es ON e.user_id = es.employee_id AND es.is_active = 1
            WHERE 1=1
            ORDER BY fullname";
    
    $result = $conn->query($sql);
    $employees = [];
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
    echo json_encode($employees);
    exit;
}

/* ================= SAVE EMPLOYEE SALARY ================= */
if ($action === 'save_salary') {
    header("Content-Type: application/json");
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data || !isset($data['employee_id'])) {
        echo json_encode(["status" => "error", "message" => "No data received: " . file_get_contents("php://input")]);
        exit;
    }
    
    $employee_id = intval($data['employee_id']);
    $basic_salary = floatval($data['basic_salary']);
    $housing = floatval($data['housing_allowance'] ?? 0);
    $transport = floatval($data['transport_allowance'] ?? 0);
    $medical = floatval($data['medical_allowance'] ?? 0);
    $other = floatval($data['other_allowances'] ?? 0);
    $deductions = floatval($data['deductions'] ?? 0);
    $effective_date = $data['effective_date'] ?? date('Y-m-d');
    $id = $data['id'] ?? null;
    
    if ($id) {
        $stmt = $conn->prepare("UPDATE employee_salaries SET 
            basic_salary = ?, housing_allowance = ?, transport_allowance = ?,
            medical_allowance = ?, other_allowances = ?, deductions = ?, effective_date = ?
            WHERE id = ?");
        $stmt->bind_param("ddddddds", $basic_salary, $housing, $transport, $medical, $other, $deductions, $effective_date, $id);
    } else {
        $conn->query("UPDATE employee_salaries SET is_active = 0 WHERE employee_id = $employee_id");
        
        $stmt = $conn->prepare("INSERT INTO employee_salaries 
            (employee_id, employee_type, basic_salary, housing_allowance, transport_allowance,
            medical_allowance, other_allowances, deductions, effective_date, is_active)
            VALUES (?, 'teacher', ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("iddddddd", $employee_id, $basic_salary, $housing, $transport, $medical, $other, $deductions, $effective_date);
    }
    $stmt->execute();
    
    if ($stmt->error) {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
        exit;
    }
    
    echo json_encode(["status" => "success", "id" => $conn->insert_id]);
    exit;
}

/* ================= DELETE SALARY ================= */
if ($action === 'delete_salary') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM employee_salaries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["status" => "deleted"]);
    exit;
}

/* ================= GET SINGLE SALARY ================= */
if ($action === 'get_salary') {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT es.*, t.fullname, t.user_id as employee_id FROM employee_salaries es JOIN teachers t ON es.employee_id = t.user_id WHERE es.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $salary = $result->fetch_assoc();
    if (!$salary) { echo json_encode(["error" => "Not found"]); }
    else { echo json_encode($salary); }
    exit;
}

/* ================= GET SALARY PAYMENTS ================= */
if ($action === 'get_salary_payments') {
    $month = $_GET['month'] ?? null;
    
    $baseQuery = "SELECT sp.*, combined.fullname, combined.employee_number
        FROM salary_payments sp
        LEFT JOIN (
            SELECT user_id, fullname, employee_number FROM teachers
            UNION ALL
            SELECT user_id, fullname, employee_number FROM employees
        ) combined ON sp.employee_id = combined.user_id";
    
    if ($month) {
        $stmt = $conn->prepare("$baseQuery WHERE sp.month = ? ORDER BY combined.fullname");
        $stmt->bind_param("s", $month);
    } else {
        $stmt = $conn->prepare("$baseQuery ORDER BY sp.month DESC, combined.fullname");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    echo json_encode($payments);
    exit;
}

/* ================= PROCESS MONTHLY SALARIES ================= */
if ($action === 'process_salaries') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $month = $data['month'];
    
    $result = $conn->query("SELECT * FROM employee_salaries WHERE is_active = 1");
    $processed = 0;
    $total_salary = 0;
    
    while ($salary = $result->fetch_assoc()) {
        $gross = floatval($salary['basic_salary']) + floatval($salary['housing_allowance']) + 
                 floatval($salary['transport_allowance']) + floatval($salary['medical_allowance']) + 
                 floatval($salary['other_allowances']);
        $net = $gross - floatval($salary['deductions']);
        $total_salary += $net;
        
        $stmt = $conn->prepare("INSERT INTO salary_payments 
            (employee_id, salary_id, month, gross_salary, total_deductions, net_salary, status, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, 'paid', ?)");
        $stmt->bind_param("iisdddd", $salary['employee_id'], $salary['id'], $month, $gross, $salary['deductions'], $net, $_SESSION['user_id']);
        $stmt->execute();
        $processed++;
    }
    
    if ($processed > 0) {
        update_account_balance($total_salary, 'subtract');
    }
    
    echo json_encode(["status" => "success", "processed" => $processed, "total_deducted" => $total_salary]);
    exit;
}

/* ================= GET SUPPLIER CONTRACTS ================= */
if ($action === 'get_contracts') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $status = $_GET['status'] ?? '';
    $sql = "SELECT * FROM supplier_contracts WHERE 1=1";
    if ($status) $sql .= " AND status = '$status'";
    $sql .= " ORDER BY end_date ASC";
    
    $result = $conn->query($sql);
    $contracts = [];
    while ($row = $result->fetch_assoc()) {
        $contracts[] = $row;
    }
    echo json_encode($contracts);
    exit;
}

if ($action === 'save_contract') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    
    $fields = ['supplier_name', 'supplier_contact', 'supplier_email', 'contract_type', 
               'contract_value', 'start_date', 'end_date', 'terms', 'renewal_date', 
               'status', 'document_ref'];
    
    if ($id) {
        $setParts = [];
        foreach ($fields as $field) {
            $setParts[] = "$field = ?";
        }
        $sql = "UPDATE supplier_contracts SET " . implode(', ', $setParts) . " WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $params = [];
        foreach ($fields as $field) {
            $params[] = $data[$field] ?? null;
        }
        $params[] = $id;
        $types = str_repeat('s', count($fields)) . 'i';
        $stmt->bind_param($types, ...$params);
    } else {
        $sql = "INSERT INTO supplier_contracts (" . implode(', ', $fields) . ", created_by) 
                VALUES (" . str_repeat('?,', count($fields)) . "?, ?)";
        $stmt = $conn->prepare($sql);
        $params = [];
        foreach ($fields as $field) {
            $params[] = $data[$field] ?? null;
        }
        $params[] = $_SESSION['user_id'];
        $types = str_repeat('s', count($fields) + 1);
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_contract') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM supplier_contracts WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["status" => "deleted"]);
    exit;
}

/* ================= RECORD CONTRACT PAYMENT ================= */
if ($action === 'record_contract_payment') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $amount = floatval($data['amount']);
    
    $stmt = $conn->prepare("INSERT INTO contract_payments 
        (contract_id, amount, payment_date, payment_method, reference, notes, recorded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idsssss", $data['contract_id'], $amount, $data['payment_date'], 
                       $data['payment_method'], $data['reference'], $data['notes'], $_SESSION['user_id']);
    $stmt->execute();
    
    update_account_balance($amount, 'subtract');
    
    echo json_encode(["status" => "success"]);
    exit;
}

/* ================= GET DONATIONS ================= */
if ($action === 'get_donations') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $result = $conn->query("SELECT * FROM donations ORDER BY donation_date DESC");
    $donations = [];
    while ($row = $result->fetch_assoc()) {
        $donations[] = $row;
    }
    echo json_encode($donations);
    exit;
}

/* ================= RECORD DONATION ================= */
if ($action === 'record_donation') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $amount = floatval($data['amount']);
    
    $stmt = $conn->prepare("INSERT INTO donations 
        (donor_name, donor_type, donor_contact, donor_email, amount, donation_type,
         donation_date, purpose, receipt_number, recorded_by, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssdsssss", 
        $data['donor_name'], $data['donor_type'], $data['donor_contact'], $data['donor_email'],
        $amount, $data['donation_type'], $data['donation_date'], $data['purpose'],
        $data['receipt_number'], $_SESSION['user_id'], $data['notes']);
    $stmt->execute();
    
    update_account_balance($amount, 'add');
    
    echo json_encode(["status" => "success"]);
    exit;
}

/* ================= DELETE DONATION ================= */
if ($action === 'delete_donation') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("DELETE FROM donations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    echo json_encode(["status" => "deleted"]);
    exit;
}

/* ================= HR DASHBOARD STATS ================= */
if ($action === 'hr_stats') {
    $total_salaries = $conn->query("SELECT COALESCE(SUM(basic_salary), 0) as total FROM employee_salaries WHERE is_active = 1")->fetch_assoc()['total'];
    $monthly_payroll = $conn->query("SELECT COALESCE(SUM(net_salary), 0) as total FROM salary_payments WHERE month = '" . date('Y-m') . "' AND status = 'paid'")->fetch_assoc()['total'];
    $active_contracts = $conn->query("SELECT COUNT(*) as count FROM supplier_contracts WHERE status = 'active'")->fetch_assoc()['count'];
    $total_donations = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations")->fetch_assoc()['total'];
    $pending_renewals = $conn->query("SELECT COUNT(*) as count FROM supplier_contracts WHERE status = 'pending_renewal'")->fetch_assoc()['count'];
    $account_balance = $conn->query("SELECT balance FROM school_account LIMIT 1")->fetch_assoc()['balance'] ?? 0;
    
    echo json_encode([
        "total_salaries" => (float)$total_salaries,
        "monthly_payroll" => (float)$monthly_payroll,
        "active_contracts" => (int)$active_contracts,
        "total_donations" => (float)$total_donations,
        "pending_renewals" => (int)$pending_renewals,
        "account_balance" => (float)$account_balance
    ]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);