<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

register_shutdown_function(function(){
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_end_clean();
        echo json_encode(["status" => "error", "message" => "Server error: " . $err['message']]);
    }
});

header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../email_helper.php';

$action = $_GET['action'] ?? '';

$public_actions = ['get_employees', 'get_salaries', 'get_fee_structures', 'get_expenses', 'get_contracts', 'get_donations', 'save_salary', 'delete_salary', 'dashboard_stats', 'get_payments', 'get_student_fees', 'get_salary_payments', 'hr_stats'];

function json_response($data, $code = 200) {
    ob_end_clean();
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if (empty($_SESSION['user_id']) && !in_array($action, $public_actions)) {
    json_response(["error" => "Not logged in"], 403);
}

$userRole = $_SESSION['role'] ?? '';
$isAdmin = !empty($userRole) && in_array($userRole, ['admin', 'finance_manager', 'principal', 'hr_manager']);

function esc($s) { global $conn; return "'" . $conn->real_escape_string($s) . "'"; }

function get_account_balance() {
    global $conn;
    $result = $conn->query("SELECT balance FROM school_account WHERE id = 1");
    if (!$result || $result->num_rows === 0) return 0;
    $row = $result->fetch_assoc();
    return $row ? floatval($row['balance']) : 0;
}

function update_account_balance($amount, $type = 'add') {
    global $conn;
    $stmt = $conn->prepare("SELECT balance FROM school_account WHERE id = 1");
    $stmt->execute();
    $current = $stmt->get_result();
    if (!$current || $current->num_rows === 0) {
        $stmt = $conn->prepare("INSERT INTO school_account (id, balance) VALUES (1, 0)");
        $stmt->execute();
        $balance = 0;
    } else {
        $balance = floatval($current->fetch_assoc()['balance'] ?? 0);
    }
    $new_balance = ($type === 'add') ? ($balance + $amount) : ($balance - $amount);
    $stmt = $conn->prepare("UPDATE school_account SET balance = ? WHERE id = 1");
    $stmt->bind_param("d", $new_balance);
    $stmt->execute();
    return $new_balance;
}

if ($action === 'dashboard_stats') {
    $year = esc($_GET['year'] ?? date('Y'));
    $total_fees = floatval($conn->query("SELECT COALESCE(SUM(total_amount), 0) as t FROM student_fees WHERE academic_year = $year")->fetch_assoc()['t'] ?? 0);
    $total_paid = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM payments WHERE YEAR(payment_date) = $year")->fetch_assoc()['t'] ?? 0);
    $total_expenses = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM expenses WHERE YEAR(expense_date) = $year")->fetch_assoc()['t'] ?? 0);
    $total_salaries = floatval($conn->query("SELECT COALESCE(SUM(net_salary), 0) as t FROM salary_payments WHERE month LIKE CONCAT($year, '%')")->fetch_assoc()['t'] ?? 0);
    $total_donations = floatval($conn->query("SELECT COALESCE(SUM(amount), 0) as t FROM donations WHERE YEAR(donation_date) = $year")->fetch_assoc()['t'] ?? 0);
    $pending_fees = intval($conn->query("SELECT COUNT(*) as c FROM student_fees WHERE status != 'paid'")->fetch_assoc()['c'] ?? 0);
    echo json_encode(["total_fees" => $total_fees, "total_paid" => $total_paid, "total_expenses" => $total_expenses, "total_salaries" => $total_salaries, "total_donations" => $total_donations, "pending_fees" => $pending_fees, "account_balance" => get_account_balance()]);
    exit;
}

if ($action === 'get_fee_structures') {
    $year = esc($_GET['year'] ?? date('Y'));
    $result = $conn->query("SELECT * FROM fee_structures WHERE academic_year = $year ORDER BY grade_name");
    $structures = [];
    while ($row = $result->fetch_assoc()) { $structures[] = $row; }
    echo json_encode($structures);
    exit;
}

if ($action === 'save_fee_structure') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $grade_name = esc($data['grade_name']);
    $academic_year = esc($data['academic_year']);
    $tuition_fee = floatval($data['tuition_fee'] ?? 0);
    $registration_fee = floatval($data['registration_fee'] ?? 0);
    $exam_fee = floatval($data['exam_fee'] ?? 0);
    $library_fee = floatval($data['library_fee'] ?? 0);
    $sports_fee = floatval($data['sports_fee'] ?? 0);
    $transport_fee = floatval($data['transport_fee'] ?? 0);
    $other_fee = floatval($data['other_fee'] ?? 0);
    $due_date = $data['due_date'] ? esc($data['due_date']) : 'NULL';
    $description = esc($data['description'] ?? '');
    if ($id) {
        $conn->query("UPDATE fee_structures SET grade_name=$grade_name, academic_year=$academic_year, tuition_fee=$tuition_fee, registration_fee=$registration_fee, exam_fee=$exam_fee, library_fee=$library_fee, sports_fee=$sports_fee, transport_fee=$transport_fee, other_fee=$other_fee, due_date=$due_date, description=$description WHERE id=$id");
    } else {
        $conn->query("INSERT INTO fee_structures (grade_name, academic_year, tuition_fee, registration_fee, exam_fee, library_fee, sports_fee, transport_fee, other_fee, due_date, description) VALUES ($grade_name, $academic_year, $tuition_fee, $registration_fee, $exam_fee, $library_fee, $sports_fee, $transport_fee, $other_fee, $due_date, $description)");
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
    $year = esc($_GET['year'] ?? date('Y'));
    $result = $conn->query("SELECT sf.*, s.student_number, s.fullname FROM student_fees sf JOIN students s ON sf.student_id = s.id WHERE sf.academic_year = $year ORDER BY s.fullname");
    $fees = [];
    while ($row = $result->fetch_assoc()) { $fees[] = $row; }
    echo json_encode($fees);
    exit;
}

if ($action === 'create_student_fees') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $year = esc($_GET['year'] ?? date('Y') . '-' . (date('Y') + 1));
    $structures = $conn->query("SELECT * FROM fee_structures WHERE academic_year = $year");
    $created = 0;
    while ($fs = $structures->fetch_assoc()) {
        $students = $conn->query("SELECT id FROM students WHERE grade = " . esc($fs['grade_name']));
        while ($student = $students->fetch_assoc()) {
            $total = floatval($fs['tuition_fee']) + floatval($fs['registration_fee']) + floatval($fs['exam_fee']) + floatval($fs['library_fee']) + floatval($fs['sports_fee']) + floatval($fs['transport_fee']) + floatval($fs['other_fee']);
            $due_date = $fs['due_date'] ? esc($fs['due_date']) : esc(date('Y-12-31'));
            $existing = $conn->query("SELECT id FROM student_fees WHERE student_id = {$student['id']} AND academic_year = $year");
            if ($existing->num_rows === 0) {
                $conn->query("INSERT INTO student_fees (student_id, academic_year, fee_structure_id, total_amount, paid_amount, balance, status, due_date) VALUES ({$student['id']}, $year, {$fs['id']}, $total, 0, $total, 'pending', $due_date)");
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
    $year_part = intval(explode('-', $year)[0]);
    $result = $conn->query("SELECT p.*, s.fullname, s.student_number FROM payments p JOIN students s ON p.student_id = s.id WHERE YEAR(p.payment_date) = $year_part ORDER BY p.payment_date DESC");
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
    $method = esc($data['payment_method'] ?? 'cash');
    $reference = esc($data['reference_number'] ?? '');
    $payment_date = esc($data['payment_date'] ?? date('Y-m-d'));
    $notes = esc($data['notes'] ?? '');
    $recorded_by = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, payment_method, reference_number, payment_date, recorded_by, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idsssis", $student_id, $amount, $method, $reference, $payment_date, $recorded_by, $notes);
    $stmt->execute();
    $payment_id = $conn->insert_id;

    $stmt = $conn->prepare("SELECT id, total_amount, paid_amount, balance FROM student_fees WHERE student_id = ? AND status != 'paid' ORDER BY academic_year DESC LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student_fee = $stmt->get_result();
    if ($sf = $student_fee->fetch_assoc()) {
        $new_paid = floatval($sf['paid_amount']) + $amount;
        $new_balance = floatval($sf['total_amount']) - $new_paid;
        if ($new_balance < 0) $new_balance = 0;
        $status = $new_balance <= 0 ? 'paid' : ($new_paid > 0 ? 'partial' : 'pending');
        $stmt = $conn->prepare("UPDATE student_fees SET paid_amount = ?, balance = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ddsi", $new_paid, $new_balance, $status, $sf['id']);
        $stmt->execute();
    }

    update_account_balance($amount, 'add');
    log_audit($conn, $_SESSION['user_id'], 'fee_payment', 'finance', 'payment', $payment_id, ['student_id' => $student_id, 'amount' => $amount, 'method' => $method]);

    $stmt = $conn->prepare("SELECT s.fullname, u.email FROM students s JOIN user u ON u.id = s.user_id WHERE s.id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stu = $stmt->get_result()->fetch_assoc();
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
    $year_part = intval(explode('-', $year)[0]);
    $result = $conn->query("SELECT * FROM expenses WHERE YEAR(expense_date) = $year_part ORDER BY expense_date DESC");
    $expenses = [];
    while ($row = $result->fetch_assoc()) { $expenses[] = $row; }
    echo json_encode($expenses);
    exit;
}

if ($action === 'save_expense') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $category = esc($data['category']);
    $description = esc($data['description']);
    $amount = floatval($data['amount']);
    $vendor = esc($data['vendor_name'] ?? '');
    $date = esc($data['expense_date']);
    $receipt = esc($data['receipt_number'] ?? '');
    $recorded_by = intval($_SESSION['user_id']);
    if ($id) {
        $conn->query("UPDATE expenses SET category=$category, description=$description, amount=$amount, vendor_name=$vendor, expense_date=$date, receipt_number=$receipt WHERE id=$id");
    } else {
        $conn->query("INSERT INTO expenses (category, description, amount, vendor_name, expense_date, receipt_number, recorded_by) VALUES ($category, $description, $amount, $vendor, $date, $receipt, $recorded_by)");
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
    $recorded_by = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("UPDATE school_account SET balance = ? WHERE id = 1");
    $stmt->bind_param("d", $balance_after);
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE expenses SET status = 'paid', paid_at = NOW(), paid_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $recorded_by, $id);
    $stmt->execute();
    $cat = esc($expense['category']);
    $desc = esc($expense['description']);
    $stmt = $conn->prepare("INSERT INTO account_transactions (type, category, description, amount, balance_before, balance_after, reference_type, reference_id, recorded_by) VALUES ('expense', ?, ?, ?, ?, ?, 'expense', ?, ?)");
    $stmt->bind_param("ssddiii", $cat, $desc, $amount, $balance_before, $balance_after, $id, $recorded_by);
    $stmt->execute();
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
    $donor_name = esc($data['donor_name']);
    $donor_type = esc($data['donor_type'] ?? 'individual');
    $donor_contact = esc($data['donor_contact'] ?? '');
    $donor_email = esc($data['donor_email'] ?? '');
    $amount = floatval($data['amount']);
    $donation_type = esc($data['donation_type'] ?? 'cash');
    $donation_date = esc($data['donation_date'] ?? date('Y-m-d'));
    $purpose = esc($data['purpose'] ?? '');
    $receipt_number = esc($data['receipt_number'] ?? '');
    $notes = esc($data['notes'] ?? '');
    $recorded_by = intval($_SESSION['user_id']);
    if ($id) {
        $conn->query("UPDATE donations SET donor_name=$donor_name, donor_type=$donor_type, donor_contact=$donor_contact, donor_email=$donor_email, amount=$amount, donation_type=$donation_type, donation_date=$donation_date, purpose=$purpose, receipt_number=$receipt_number, notes=$notes WHERE id=$id");
    } else {
        $conn->query("INSERT INTO donations (donor_name, donor_type, donor_contact, donor_email, amount, donation_type, donation_date, purpose, receipt_number, recorded_by, notes) VALUES ($donor_name, $donor_type, $donor_contact, $donor_email, $amount, $donation_type, $donation_date, $purpose, $receipt_number, $recorded_by, $notes)");
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
    if (!$isAdmin) { json_response(["error" => "Access denied"], 403); }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $employee_id = intval($data['employee_id']);
    if (!$employee_id) { json_response(["status" => "error", "message" => "Employee not found"]); }
    $basic_salary = floatval($data['basic_salary']);
    $housing = floatval($data['housing_allowance'] ?? 0);
    $transport = floatval($data['transport_allowance'] ?? 0);
    $medical = floatval($data['medical_allowance'] ?? 0);
    $other = floatval($data['other_allowances'] ?? 0);
    $deductions = floatval($data['deductions'] ?? 0);
    $effective_date = $data['effective_date'] ?? date('Y-m-d');
    if (!$basic_salary) { json_response(["status" => "error", "message" => "Basic salary is required"]); }

    $stmt = $conn->prepare("SELECT user_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $check_teacher = $stmt->get_result();
    $employee_type = ($check_teacher && $check_teacher->num_rows > 0) ? 'teacher' : 'employee';

    if ($id) {
        $stmt = $conn->prepare("UPDATE employee_salaries SET basic_salary=?, housing_allowance=?, transport_allowance=?, medical_allowance=?, other_allowances=?, deductions=?, effective_date=? WHERE id=?");
        if (!$stmt) { json_response(["status" => "error", "message" => "Prepare failed: " . $conn->error]); }
        $stmt->bind_param("ddddddsi", $basic_salary, $housing, $transport, $medical, $other, $deductions, $effective_date, $id);
    } else {
        $stmt = $conn->prepare("UPDATE employee_salaries SET is_active = 0 WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $stmt = $conn->prepare("INSERT INTO employee_salaries (employee_id, employee_type, basic_salary, housing_allowance, transport_allowance, medical_allowance, other_allowances, deductions, effective_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        if (!$stmt) { json_response(["status" => "error", "message" => "Prepare failed: " . $conn->error]); }
        $stmt->bind_param("isdddddds", $employee_id, $employee_type, $basic_salary, $housing, $transport, $medical, $other, $deductions, $effective_date);
    }
    try {
        if (!$stmt->execute()) {
            json_response(["status" => "error", "message" => "Execute failed: " . $stmt->error]);
        }
    } catch (Throwable $e) {
        json_response(["status" => "error", "message" => $e->getMessage()]);
    }
    $stmt->close();
    log_audit($conn, $_SESSION['user_id'], 'salary_saved', 'finance', 'salary', $id ?: $conn->insert_id, ['employee_id' => $employee_id, 'basic' => $basic_salary]);
    json_response(["status" => "success"]);
}

if ($action === 'delete_salary') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $id = intval($_GET['id']);
    $conn->query("DELETE FROM employee_salaries WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'process_salaries') {
    $month = esc($_GET['month'] ?? date('Y-m'));
    $result = $conn->query("SELECT * FROM employee_salaries WHERE is_active = 1");
    $processed = 0;
    $total = 0;
    $recorded_by = intval($_SESSION['user_id'] ?? 0);
    $payment_date = date('Y-m-d');
    while ($salary = $result->fetch_assoc()) {
        $employee_id = $salary['employee_id'];
        $gross = floatval($salary['basic_salary']) + floatval($salary['housing_allowance']) + floatval($salary['transport_allowance']) + floatval($salary['medical_allowance']) + floatval($salary['other_allowances']);
        $net = $gross - floatval($salary['deductions']);
        $total += $net;
        $stmt = $conn->prepare("SELECT id FROM salary_payments WHERE employee_id = ? AND month = ?");
        $stmt->bind_param("is", $employee_id, $month);
        $stmt->execute();
        $check = $stmt->get_result();
        if ($check->num_rows === 0) {
            $stmt = $conn->prepare("INSERT INTO salary_payments (employee_id, salary_id, month, gross_salary, total_deductions, net_salary, status, payment_date, recorded_by) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)");
            $ded = floatval($salary['deductions']);
            $stmt->bind_param("iisddddsi", $employee_id, $salary['id'], $month, $gross, $ded, $net, $payment_date, $recorded_by);
            $stmt->execute();
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
    $recorded_by = intval($_SESSION['user_id']);

    $stmt = $conn->prepare("UPDATE school_account SET balance = ? WHERE id = 1");
    $stmt->bind_param("d", $af);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE salary_payments SET status = 'paid', payment_date = CURDATE() WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $desc = "Salary payment #$id";
    $stmt = $conn->prepare("INSERT INTO account_transactions (type, category, description, amount, balance_before, balance_after, reference_type, reference_id, recorded_by) VALUES ('expense', 'Salaries', ?, ?, ?, ?, 'payroll', ?, ?)");
    $stmt->bind_param("sdddii", $desc, $amount, $bf, $af, $id, $recorded_by);
    $stmt->execute();

    log_audit($conn, $_SESSION['user_id'], 'salary_paid', 'finance', 'payroll', $id, ['net_salary' => $amount]);

    $emp_id = intval($pay['employee_id']);
    $month = $conn->real_escape_string($pay['month']);
    $stmt = $conn->prepare("SELECT u.email, COALESCE(t.fullname, e.fullname, u.email) AS fullname FROM user u LEFT JOIN teachers t ON t.user_id = u.id LEFT JOIN employees e ON e.user_id = u.id WHERE u.id = ?");
    $stmt->bind_param("i", $emp_id);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    if ($u) email_salary_paid($conn, $u['email'], $u['fullname'], $amount, $month);

    echo json_encode(["status" => "success", "message" => "Salary paid"]);
    exit;
}

if ($action === 'get_salary_payments') {
    $month = esc($_GET['month'] ?? date('Y-m'));
    $result = $conn->query("SELECT sp.*, combined.fullname, combined.employee_number
        FROM salary_payments sp
        LEFT JOIN (
            SELECT user_id, fullname, employee_number FROM teachers
            UNION ALL
            SELECT user_id, fullname, employee_number FROM employees
        ) combined ON sp.employee_id = combined.user_id
        WHERE sp.month = $month
        ORDER BY combined.fullname");
    $payments = [];
    while ($row = $result->fetch_assoc()) { $payments[] = $row; }
    echo json_encode($payments);
    exit;
}

if ($action === 'get_contracts') {
    $status = $_GET['status'] ?? '';
    $sql = "SELECT * FROM supplier_contracts";
    if ($status) $sql .= " WHERE status = " . esc($status);
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
    $supplier_name = esc($data['supplier_name']);
    $contact = esc($data['supplier_contact'] ?? '');
    $email = esc($data['supplier_email'] ?? '');
    $type = esc($data['contract_type'] ?? '');
    $value = floatval($data['contract_value'] ?? 0);
    $start = esc($data['start_date']);
    $end = esc($data['end_date']);
    $terms = esc($data['terms'] ?? '');
    $renewal = esc($data['renewal_date'] ?? '');
    $status_contract = esc($data['status'] ?? 'active');
    $doc_ref = esc($data['document_ref'] ?? '');
    $created_by = intval($_SESSION['user_id']);
    if ($id) {
        $conn->query("UPDATE supplier_contracts SET supplier_name=$supplier_name, supplier_contact=$contact, supplier_email=$email, contract_type=$type, contract_value=$value, start_date=$start, end_date=$end, terms=$terms, renewal_date=$renewal, status=$status_contract, document_ref=$doc_ref WHERE id=$id");
    } else {
        $conn->query("INSERT INTO supplier_contracts (supplier_name, supplier_contact, supplier_email, contract_type, contract_value, start_date, end_date, terms, renewal_date, status, document_ref, created_by) VALUES ($supplier_name, $contact, $email, $type, $value, $start, $end, $terms, $renewal, $status_contract, $doc_ref, $created_by)");
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
    $status = esc($_GET['status']);
    $conn->query("UPDATE supplier_contracts SET status = $status WHERE id = $id");
    echo json_encode(["status" => "success"]);
    exit;
}

// ===== BUDGET CRUD =====
if ($action === 'get_budgets') {
    $year = esc($_GET['year'] ?? date('Y'));
    $result = $conn->query("SELECT * FROM budgets WHERE fiscal_year = $year ORDER BY category");
    $budgets = [];
    while ($row = $result->fetch_assoc()) $budgets[] = $row;
    echo json_encode($budgets);
    exit;
}

if ($action === 'save_budget') {
    if (!$isAdmin) { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    $category = esc($data['category']);
    $fiscal_year = esc($data['fiscal_year']);
    $amount = floatval($data['allocated_amount']);
    $notes = esc($data['notes'] ?? '');
    $created_by = intval($_SESSION['user_id']);
    if ($id) {
        $conn->query("UPDATE budgets SET category=$category, fiscal_year=$fiscal_year, allocated_amount=$amount, notes=$notes WHERE id=$id");
    } else {
        $conn->query("INSERT INTO budgets (category, fiscal_year, allocated_amount, status, created_by, notes) VALUES ($category, $fiscal_year, $amount, 'draft', $created_by, $notes)");
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
    $status = esc($_GET['status']);
    $conn->query("UPDATE budgets SET status = $status WHERE id = $id");
    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);