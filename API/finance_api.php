<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../email_helper.php';

$action = $_GET['action'] ?? '';

$public_actions = ['get_employees', 'get_salaries', 'get_fee_structures', 'get_expenses', 'get_contracts', 'get_donations', 'save_salary', 'delete_salary', 'dashboard_stats', 'get_payments', 'get_student_fees', 'get_salary_payments', 'hr_stats'];
if (empty($_SESSION['user_id']) && !in_array($action, $public_actions)) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$userRole = $_SESSION['role'] ?? '';
$isAdmin = !empty($userRole) && in_array($userRole, ['admin', 'finance_manager', 'principal', 'hr_manager']);

function get_account_balance() {
    global $conn;
    $result = $conn->query("SELECT balance FROM school_account WHERE id = 1");
    if (!$result || $result->num_rows === 0) return 0;
    $row = $result->fetch_assoc();
    return $row ? floatval($row['balance']) : 0;
}

function update_account_balance($amount, $type = 'add') {
    global $conn;
    $current = $conn->query("SELECT balance FROM school_account WHERE id = 1");
    if (!$current || $current->num_rows === 0) {
        $conn->query("INSERT INTO school_account (id, balance) VALUES (1, 0)");
        $balance = 0;
    } else {
        $balance = floatval($current->fetch_assoc()['balance'] ?? 0);
    }
    $new_balance = ($type === 'add') ? ($balance + $amount) : ($balance - $amount);
    $conn->query("UPDATE school_account SET balance = $new_balance WHERE id = 1");
    return $new_balance;
}

if ($action === 'dashboard_stats') {
    $year = $_GET['year'] ?? date('Y');
    $total_fees = floatval($conn->query("SELECT COALESCE(SUM(total_amount), 0) as t FROM student_fees WHERE academic_year = '$year'")->fetch_assoc()['t'] ?? 0);
    $total_paid = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM payments WHERE YEAR(payment_date) = '$year'")->fetch_assoc()['t'] ?? 0);
    $total_expenses = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM expenses WHERE YEAR(expense_date) = '$year'")->fetch_assoc()['t'] ?? 0);
    $total_salaries = floatval($conn->query("SELECT COALESCE(SUM(net_salary), 0) as t FROM salary_payments WHERE month LIKE '$year%'")->fetch_assoc()['t'] ?? 0);
    $total_donations = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM donations WHERE YEAR(donation_date) = '$year'")->fetch_assoc()['t'] ?? 0);
    $pending_fees = intval($conn->query("SELECT COUNT(*) as c FROM student_fees WHERE status != 'paid'")->fetch_assoc()['c'] ?? 0);
    echo json_encode(["total_fees" => $total_fees, "total_paid" => $total_paid, "total_expenses" => $total_expenses, "total_salaries" => $total_salaries, "total_donations" => $total_donations, "pending_fees" => $pending_fees, "account_balance" => get_account_balance()]);
    exit;
}

if ($action === 'get_fee_structures') {
    $year = $_GET['year'] ?? date('Y');
    $result = $conn->query("SELECT * FROM fee_structures WHERE academic_year = '$year' ORDER BY grade_name");
    $structures = [];
    while ($row = $result->fetch_assoc()) { $structures[] = $row; }
    echo json_encode($structures);
    exit;
}

if ($action === 'save_fee_structure') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $grade_name = $data['grade_name'];
    $academic_year = $data['academic_year'];
    $tuition_fee = floatval($data['tuition_fee'] ?? 0);
    $registration_fee = floatval($data['registration_fee'] ?? 0);
    $exam_fee = floatval($data['exam_fee'] ?? 0);
    $library_fee = floatval($data['library_fee'] ?? 0);
    $sports_fee = floatval($data['sports_fee'] ?? 0);
    $transport_fee = floatval($data['transport_fee'] ?? 0);
    $other_fee = floatval($data['other_fee'] ?? 0);
    $due_date = $data['due_date'] ?? null;
    $description = $data['description'] ?? '';
    if ($id) {
        $conn->query("UPDATE fee_structures SET grade_name='$grade_name', academic_year='$academic_year', tuition_fee=$tuition_fee, registration_fee=$registration_fee, exam_fee=$exam_fee, library_fee=$library_fee, sports_fee=$sports_fee, transport_fee=$transport_fee, other_fee=$other_fee, due_date='$due_date', description='$description' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO fee_structures (grade_name, academic_year, tuition_fee, registration_fee, exam_fee, library_fee, sports_fee, transport_fee, other_fee, due_date, description) VALUES ('$grade_name', '$academic_year', $tuition_fee, $registration_fee, $exam_fee, $library_fee, $sports_fee, $transport_fee, $other_fee, '$due_date', '$description')");
    }
    if ($conn->error) { echo json_encode(["status" => "error", "message" => $conn->error]); exit; }
    log_audit($conn, $_SESSION['user_id'], 'fee_structure_saved', 'finance', 'fee_structure', $id ?: $conn->insert_id, ['grade' => $grade_name, 'year' => $academic_year]);
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_fee_structure') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM fee_structures WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'get_student_fees') {
    $year = $_GET['year'] ?? date('Y');
    $sql = "SELECT sf.*, s.student_number, s.fullname FROM student_fees sf JOIN students s ON sf.student_id = s.id WHERE sf.academic_year = '$year' ORDER BY s.fullname";
    $result = $conn->query($sql);
    $fees = [];
    while ($row = $result->fetch_assoc()) { $fees[] = $row; }
    echo json_encode($fees);
    exit;
}

if ($action === 'create_student_fees') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $year = $_GET['year'] ?? date('Y') . '-' . (date('Y') + 1);
    $structures = $conn->query("SELECT * FROM fee_structures WHERE academic_year = '$year'");
    $created = 0;
    while ($fs = $structures->fetch_assoc()) {
        // Match students by grade field
        $students = $conn->query("SELECT id FROM students WHERE grade = '{$fs['grade_name']}'");
        while ($student = $students->fetch_assoc()) {
            $total = floatval($fs['tuition_fee']) + floatval($fs['registration_fee']) + floatval($fs['exam_fee']) + floatval($fs['library_fee']) + floatval($fs['sports_fee']) + floatval($fs['transport_fee']) + floatval($fs['other_fee']);
            $due_date = $fs['due_date'] ?: date('Y-12-31');
            $existing = $conn->query("SELECT id FROM student_fees WHERE student_id = {$student['id']} AND academic_year = '$year'");
            if ($existing->num_rows === 0) {
                $conn->query("INSERT INTO student_fees (student_id, academic_year, fee_structure_id, total_amount, paid_amount, balance, status, due_date) VALUES ({$student['id']}, '$year', {$fs['id']}, $total, 0, $total, 'pending', '$due_date')");
                $created++;
            }
        }
    }
    log_audit($conn, $_SESSION['user_id'], 'student_fees_generated', 'finance', 'fee_generation', 0, ['year' => $year, 'count' => $created]);
    echo json_encode(["status" => "success", "created" => $created]);
    exit;
}

if ($action === 'get_payments') {
    $year = $_GET['year'] ?? date('Y');
    $year_part = explode('-', $year)[0];
    $sql = "SELECT p.*, s.fullname, s.student_number FROM payments p JOIN students s ON p.student_id = s.id WHERE YEAR(p.payment_date) = '$year_part' ORDER BY p.payment_date DESC";
    $result = $conn->query($sql);
    $payments = [];
    while ($row = $result->fetch_assoc()) { $payments[] = $row; }
    echo json_encode($payments);
    exit;
}

if ($action === 'record_payment') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $student_id = intval($data['student_id']);
    $amount = floatval($data['amount']);
    $method = $data['payment_method'] ?? 'cash';
    $reference = $data['reference_number'] ?? '';
    $payment_date = $data['payment_date'] ?? date('Y-m-d');
    $notes = $data['notes'] ?? '';
    $recorded_by = $_SESSION['user_id'];
    
    // Insert the payment
    $conn->query("INSERT INTO payments (student_id, amount, payment_method, reference_number, payment_date, recorded_by, notes) VALUES ($student_id, $amount, '$method', '$reference', '$payment_date', $recorded_by, '$notes')");
    $payment_id = $conn->insert_id;
    
    // Find the student's pending fee
    $student_fee = $conn->query("SELECT id, total_amount, paid_amount, balance FROM student_fees WHERE student_id = $student_id AND status != 'paid' ORDER BY academic_year DESC LIMIT 1");
    if ($sf = $student_fee->fetch_assoc()) {
        $new_paid = floatval($sf['paid_amount']) + $amount;
        $new_balance = floatval($sf['total_amount']) - $new_paid;
        if ($new_balance < 0) $new_balance = 0;
        $status = $new_balance <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'pending');
        $conn->query("UPDATE student_fees SET paid_amount = $new_paid, balance = $new_balance, status = '$status' WHERE id = {$sf['id']}");
    }
    
    // Add to school account
    update_account_balance($amount, 'add');
    log_audit($conn, $_SESSION['user_id'], 'fee_payment', 'finance', 'payment', $payment_id, ['student_id' => $student_id, 'amount' => $amount, 'method' => $method]);

    // Send fee payment email
    $stu = $conn->query("SELECT s.fullname, u.email FROM students s JOIN user u ON u.id = s.user_id WHERE s.id = $student_id")->fetch_assoc();
    if ($stu) email_fees_paid($conn, $stu['email'], $stu['fullname'], $amount, date('Y'));

    echo json_encode(["status" => "success", "message" => "Payment recorded and R $amount added to school account"]);
    exit;
}

if ($action === 'delete_payment') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM payments WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'get_expenses') {
    $year = $_GET['year'] ?? date('Y');
    $year_part = explode('-', $year)[0];
    $result = $conn->query("SELECT * FROM expenses WHERE YEAR(expense_date) = '$year_part' ORDER BY expense_date DESC");
    $expenses = [];
    while ($row = $result->fetch_assoc()) { $expenses[] = $row; }
    echo json_encode($expenses);
    exit;
}

if ($action === 'save_expense') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $category = $data['category'];
    $description = $data['description'];
    $amount = floatval($data['amount']);
    $vendor = $data['vendor_name'] ?? '';
    $date = $data['expense_date'];
    $receipt = $data['receipt_number'] ?? '';
    $recorded_by = $_SESSION['user_id'];
    if ($id) {
        $conn->query("UPDATE expenses SET category='$category', description='$description', amount=$amount, vendor_name='$vendor', expense_date='$date', receipt_number='$receipt' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO expenses (category, description, amount, vendor_name, expense_date, receipt_number, recorded_by) VALUES ('$category', '$description', $amount, '$vendor', '$date', '$receipt', $recorded_by)");
    }
    if ($conn->error) { echo json_encode(["status" => "error", "message" => $conn->error]); exit; }
    if (!$id) {
        update_account_balance($amount, 'subtract');
        log_audit($conn, $_SESSION['user_id'], 'expense_created', 'finance', 'expense', $conn->insert_id, ['category' => $category, 'amount' => $amount, 'vendor' => $vendor]);
    } else {
        log_audit($conn, $_SESSION['user_id'], 'expense_updated', 'finance', 'expense', $id, ['category' => $category, 'amount' => $amount]);
    }
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_expense') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $amount = floatval($conn->query("SELECT amount FROM expenses WHERE id = $id")->fetch_assoc()['amount'] ?? 0);
    $conn->query("DELETE FROM expenses WHERE id = $id");
    if ($amount > 0) { update_account_balance($amount, 'add'); }
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'pay_expense') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $expense = $conn->query("SELECT * FROM expenses WHERE id = $id")->fetch_assoc();
    if (!$expense) { echo json_encode(["status" => "error", "message" => "Expense not found"]); exit; }
    if ($expense['status'] !== 'approved') { echo json_encode(["status" => "error", "message" => "Expense must be approved before payment"]); exit; }
    $amount = floatval($expense['amount']);
    $current_bal = $conn->query("SELECT balance FROM school_account WHERE id = 1")->fetch_assoc();
    $balance_before = floatval($current_bal['balance'] ?? 0);
    $balance_after = $balance_before - $amount;
    $conn->query("UPDATE school_account SET balance = $balance_after WHERE id = 1");
    $conn->query("UPDATE expenses SET status = 'paid', paid_at = NOW(), paid_by = {$_SESSION['user_id']} WHERE id = $id");
    $conn->query("INSERT INTO account_transactions (type, category, description, amount, balance_before, balance_after, reference_type, reference_id, recorded_by)
                  VALUES ('expense', '{$expense['category']}', '{$expense['description']}', $amount, $balance_before, $balance_after, 'expense', $id, {$_SESSION['user_id']})");
    log_audit($conn, $_SESSION['user_id'], 'expense_paid', 'finance', 'expense', $id, ['amount' => $amount, 'category' => $expense['category']]);
    echo json_encode(["status" => "success", "message" => "Expense paid. R $amount deducted from account"]);
    exit;
}

if ($action === 'get_donations') {
    $result = $conn->query("SELECT * FROM donations ORDER BY donation_date DESC");
    $donations = [];
    while ($row = $result->fetch_assoc()) { $donations[] = $row; }
    echo json_encode($donations);
    exit;
}

if ($action === 'save_donation') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $donor_name = $data['donor_name'];
    $donor_type = $data['donor_type'] ?? 'individual';
    $donor_contact = $data['donor_contact'] ?? '';
    $donor_email = $data['donor_email'] ?? '';
    $amount = floatval($data['amount']);
    $donation_type = $data['donation_type'] ?? 'cash';
    $donation_date = $data['donation_date'] ?? date('Y-m-d');
    $purpose = $data['purpose'] ?? '';
    $receipt_number = $data['receipt_number'] ?? '';
    $notes = $data['notes'] ?? '';
    $recorded_by = $_SESSION['user_id'];
    if ($id) {
        $conn->query("UPDATE donations SET donor_name='$donor_name', donor_type='$donor_type', donor_contact='$donor_contact', donor_email='$donor_email', amount=$amount, donation_type='$donation_type', donation_date='$donation_date', purpose='$purpose', receipt_number='$receipt_number', notes='$notes' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO donations (donor_name, donor_type, donor_contact, donor_email, amount, donation_type, donation_date, purpose, receipt_number, recorded_by, notes) VALUES ('$donor_name', '$donor_type', '$donor_contact', '$donor_email', $amount, '$donation_type', '$donation_date', '$purpose', '$receipt_number', $recorded_by, '$notes')");
    }
    if ($conn->error) { echo json_encode(["status" => "error", "message" => $conn->error]); exit; }
    if (!$id) {
        update_account_balance($amount, 'add');
        log_audit($conn, $_SESSION['user_id'], 'donation_created', 'finance', 'donation', $conn->insert_id, ['donor' => $donor_name, 'amount' => $amount, 'type' => $donation_type]);
    } else {
        log_audit($conn, $_SESSION['user_id'], 'donation_updated', 'finance', 'donation', $id, ['donor' => $donor_name, 'amount' => $amount]);
    }
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_donation') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $amount = floatval($conn->query("SELECT amount FROM donations WHERE id = $id")->fetch_assoc()['amount'] ?? 0);
    $conn->query("DELETE FROM donations WHERE id = $id");
    if ($amount > 0) { update_account_balance($amount, 'subtract'); }
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'get_employees') {
    $result = $conn->query("
        SELECT user_id as id, fullname, employee_number FROM teachers
        UNION ALL
        SELECT user_id as id, fullname, employee_number FROM employees
        ORDER BY fullname
    ");
    $employees = [];
    while ($row = $result->fetch_assoc()) { $employees[] = $row; }
    echo json_encode($employees);
    exit;
}

if ($action === 'get_salaries') {
    $result = $conn->query("
        SELECT es.*, t.fullname FROM employee_salaries es JOIN teachers t ON es.employee_id = t.user_id WHERE es.is_active = 1
        UNION ALL
        SELECT es.*, e.fullname FROM employee_salaries es JOIN employees e ON es.employee_id = e.user_id WHERE es.is_active = 1
        ORDER BY fullname
    ");
    $salaries = [];
    while ($row = $result->fetch_assoc()) { $salaries[] = $row; }
    echo json_encode($salaries);
    exit;
}

if ($action === 'save_salary') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $employee_id = intval($data['employee_id']);
    $basic_salary = floatval($data['basic_salary']);
    $housing = floatval($data['housing_allowance'] ?? 0);
    $transport = floatval($data['transport_allowance'] ?? 0);
    $medical = floatval($data['medical_allowance'] ?? 0);
    $other = floatval($data['other_allowances'] ?? 0);
    $deductions = floatval($data['deductions'] ?? 0);
    $effective_date = $data['effective_date'] ?? date('Y-m-d');
    if (!$employee_id || !$basic_salary) { echo json_encode(["status" => "error", "message" => "Employee and basic salary required"]); exit; }
    if ($id) {
        $conn->query("UPDATE employee_salaries SET basic_salary=$basic_salary, housing_allowance=$housing, transport_allowance=$transport, medical_allowance=$medical, other_allowances=$other, deductions=$deductions, effective_date='$effective_date' WHERE id=$id");
    } else {
        $conn->query("UPDATE employee_salaries SET is_active = 0 WHERE employee_id = $employee_id");
        $conn->query("INSERT INTO employee_salaries (employee_id, employee_type, basic_salary, housing_allowance, transport_allowance, medical_allowance, other_allowances, deductions, effective_date, is_active) VALUES ($employee_id, 'teacher', $basic_salary, $housing, $transport, $medical, $other, $deductions, '$effective_date', 1)");
    }
    if ($conn->error) { echo json_encode(["status" => "error", "message" => $conn->error]); exit; }
    log_audit($conn, $_SESSION['user_id'], 'salary_saved', 'finance', 'salary', $id ?: $conn->insert_id, ['employee_id' => $employee_id, 'basic' => $basic_salary]);
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_salary') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM employee_salaries WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'process_salaries') {
    $month = $_GET['month'] ?? date('Y-m');
    $result = $conn->query("SELECT * FROM employee_salaries WHERE is_active = 1");
    $processed = 0;
    $total = 0;
    $recorded_by = $_SESSION['user_id'] ?? 0;
    $payment_date = date('Y-m-d');
    while ($salary = $result->fetch_assoc()) {
        $employee_id = $salary['employee_id'];
        $gross = floatval($salary['basic_salary']) + floatval($salary['housing_allowance']) + floatval($salary['transport_allowance']) + floatval($salary['medical_allowance']) + floatval($salary['other_allowances']);
        $net = $gross - floatval($salary['deductions']);
        $total += $net;
        $check = $conn->query("SELECT id FROM salary_payments WHERE employee_id = $employee_id AND month = '$month'");
        if ($check->num_rows === 0) {
            $conn->query("INSERT INTO salary_payments (employee_id, salary_id, month, gross_salary, total_deductions, net_salary, status, payment_date, recorded_by) VALUES ($employee_id, {$salary['id']}, '$month', $gross, {$salary['deductions']}, $net, 'pending', '$payment_date', $recorded_by)");
            $processed++;
        }
    }
    log_audit($conn, $_SESSION['user_id'], 'payroll_processed', 'finance', 'payroll', 0, ['month' => $month, 'count' => $processed, 'total' => $total]);
    echo json_encode(["status" => "success", "processed" => $processed, "total" => $total, "month" => $month]);
    exit;
}

if ($action === 'pay_salary') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $pay = $conn->query("SELECT * FROM salary_payments WHERE id = $id")->fetch_assoc();
    if (!$pay) { echo json_encode(["status" => "error", "message" => "Payment not found"]); exit; }
    if ($pay['status'] !== 'approved') { echo json_encode(["status" => "error", "message" => "Payment must be approved first"]); exit; }
    $amount = floatval($pay['net_salary']);
    $current = $conn->query("SELECT balance FROM school_account WHERE id = 1")->fetch_assoc();
    $bf = floatval($current['balance'] ?? 0);
    $af = $bf - $amount;
    $conn->query("UPDATE school_account SET balance = $af WHERE id = 1");
    $conn->query("UPDATE salary_payments SET status = 'paid', payment_date = CURDATE() WHERE id = $id");
    $conn->query("INSERT INTO account_transactions (type, category, description, amount, balance_before, balance_after, reference_type, reference_id, recorded_by)
                  VALUES ('expense', 'Salaries', CONCAT('Salary payment #$id'), $amount, $bf, $af, 'payroll', $id, {$_SESSION['user_id']})");
    log_audit($conn, $_SESSION['user_id'], 'salary_paid', 'finance', 'payroll', $id, ['net_salary' => $amount]);

    $emp_id = intval($pay['employee_id']);
    $month = $conn->real_escape_string($pay['month']);
    $u = $conn->query("SELECT u.email, COALESCE(t.fullname, e.fullname, u.email) AS fullname FROM user u LEFT JOIN teachers t ON t.user_id = u.id LEFT JOIN employees e ON e.user_id = u.id WHERE u.id = $emp_id")->fetch_assoc();
    if ($u) email_salary_paid($conn, $u['email'], $u['fullname'], $amount, $month);

    echo json_encode(["status" => "success", "message" => "Salary paid"]);
    exit;
}

if ($action === 'get_salary_payments') {
    $month = $_GET['month'] ?? date('Y-m');
    $sql = "SELECT sp.*, combined.fullname, combined.employee_number
        FROM salary_payments sp
        LEFT JOIN (
            SELECT user_id, fullname, employee_number FROM teachers
            UNION ALL
            SELECT user_id, fullname, employee_number FROM employees
        ) combined ON sp.employee_id = combined.user_id
        WHERE sp.month = '$month'
        ORDER BY combined.fullname";
    $result = $conn->query($sql);
    $payments = [];
    while ($row = $result->fetch_assoc()) { $payments[] = $row; }
    echo json_encode($payments);
    exit;
}

if ($action === 'get_contracts') {
    $status = $_GET['status'] ?? '';
    $sql = "SELECT * FROM supplier_contracts";
    if ($status) $sql .= " WHERE status = '$status'";
    $sql .= " ORDER BY end_date ASC";
    $result = $conn->query($sql);
    $contracts = [];
    while ($row = $result->fetch_assoc()) { $contracts[] = $row; }
    echo json_encode($contracts);
    exit;
}

if ($action === 'save_contract') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $supplier_name = $data['supplier_name'];
    $contact = $data['supplier_contact'] ?? '';
    $email = $data['supplier_email'] ?? '';
    $type = $data['contract_type'] ?? '';
    $value = floatval($data['contract_value'] ?? 0);
    $start = $data['start_date'];
    $end = $data['end_date'];
    $terms = $data['terms'] ?? '';
    $renewal = $data['renewal_date'] ?? '';
    $status_contract = $data['status'] ?? 'active';
    $doc_ref = $data['document_ref'] ?? '';
    $created_by = $_SESSION['user_id'];
    if ($id) {
        $conn->query("UPDATE supplier_contracts SET supplier_name='$supplier_name', supplier_contact='$contact', supplier_email='$email', contract_type='$type', contract_value=$value, start_date='$start', end_date='$end', terms='$terms', renewal_date='$renewal', status='$status_contract', document_ref='$doc_ref' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO supplier_contracts (supplier_name, supplier_contact, supplier_email, contract_type, contract_value, start_date, end_date, terms, renewal_date, status, document_ref, created_by) VALUES ('$supplier_name', '$contact', '$email', '$type', $value, '$start', '$end', '$terms', '$renewal', '$status_contract', '$doc_ref', $created_by)");
    }
    if ($conn->error) { echo json_encode(["status" => "error", "message" => $conn->error]); exit; }
    log_audit($conn, $_SESSION['user_id'], 'contract_saved', 'finance', 'contract', $id ?: $conn->insert_id, ['supplier' => $supplier_name, 'value' => $value, 'status' => $status_contract]);
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_contract') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM supplier_contracts WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'update_contract_status') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $status = $_GET['status'];
    $conn->query("UPDATE supplier_contracts SET status = '$status' WHERE id = $id");
    echo json_encode(["status" => "success"]);
    exit;
}

// ===== BUDGET CRUD =====
if ($action === 'get_budgets') {
    $year = $_GET['year'] ?? date('Y');
    $result = $conn->query("SELECT * FROM budgets WHERE fiscal_year = '$year' ORDER BY category");
    $budgets = [];
    while ($row = $result->fetch_assoc()) $budgets[] = $row;
    echo json_encode($budgets);
    exit;
}

if ($action === 'save_budget') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $category = $data['category'];
    $fiscal_year = $data['fiscal_year'];
    $amount = floatval($data['allocated_amount']);
    $notes = $data['notes'] ?? '';
    if ($id) {
        $conn->query("UPDATE budgets SET category='$category', fiscal_year='$fiscal_year', allocated_amount=$amount, notes='$notes' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO budgets (category, fiscal_year, allocated_amount, status, created_by, notes) VALUES ('$category', '$fiscal_year', $amount, 'draft', {$_SESSION['user_id']}, '$notes')");
    }
    log_audit($conn, $_SESSION['user_id'], 'budget_saved', 'finance', 'budget', $id ?: $conn->insert_id, ['category' => $category, 'amount' => $amount, 'year' => $fiscal_year]);
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_budget') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM budgets WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'update_budget_status') {
    $id = intval($_GET['id']);
    $status = $_GET['status'];
    $conn->query("UPDATE budgets SET status = '$status' WHERE id = $id");
    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);