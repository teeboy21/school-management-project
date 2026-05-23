<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$action = $_GET['action'] ?? '';

$public_actions = ['get_projects', 'get_project_details', 'get_project_expenditure', 'save_project', 'delete_project', 'save_expenditure', 'add_project_expense', 'get_project_expenses', 'get_milestones'];
if (empty($_SESSION['user_id']) && !in_array($action, $public_actions)) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$userRole = $_SESSION['role'] ?? '';
$isAdmin = !empty($userRole) && in_array($userRole, ['admin', 'finance_manager', 'principal']);

function get_account_balance() {
    global $conn;
    $current = $conn->query("SELECT balance FROM school_account LIMIT 1")->fetch_assoc();
    return $current['balance'] ?? 0;
}

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

/* ================= FINANCIAL SUMMARY ================= */
if ($action === 'summary') {
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied"]);
        exit;
    }
    
    $year = $_GET['year'] ?? date('Y');
    
    $total_revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE YEAR(payment_date) = '$year'")->fetch_assoc()['total'];
    $total_fees = $conn->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM student_fees WHERE academic_year = '$year'")->fetch_assoc()['total'];
    $total_donations = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE YEAR(donation_date) = '$year'")->fetch_assoc()['total'];
    $total_expenses = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE YEAR(expense_date) = '$year'")->fetch_assoc()['total'];
    $total_salaries = $conn->query("SELECT COALESCE(SUM(net_salary), 0) as total FROM salary_payments WHERE month LIKE '$year%' AND status = 'paid'")->fetch_assoc()['total'];
    $total_project_spend = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM project_expenditure WHERE YEAR(expenditure_date) = '$year'")->fetch_assoc()['total'];
    $total_contract_payments = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM contract_payments WHERE YEAR(payment_date) = '$year'")->fetch_assoc()['total'];
    
    $gross_income = $total_revenue + $total_donations;
    $total_expenditure = $total_expenses + $total_salaries + $total_project_spend + $total_contract_payments;
    $net_profit = $gross_income - $total_expenditure;
    $account_balance = get_account_balance();
    
    echo json_encode([
        "total_revenue" => (float)$total_revenue,
        "total_fees_expected" => (float)$total_fees,
        "total_donations" => (float)$total_donations,
        "gross_income" => (float)$gross_income,
        "total_expenses" => (float)$total_expenses,
        "total_salaries" => (float)$total_salaries,
        "total_project_spend" => (float)$total_project_spend,
        "total_contract_payments" => (float)$total_contract_payments,
        "total_expenditure" => (float)$total_expenditure,
        "net_profit" => (float)$net_profit,
        "account_balance" => (float)$account_balance,
        "outstanding_fees" => (float)($total_fees - $total_revenue)
    ]);
    exit;
}

/* ================= MONTHLY BREAKDOWN ================= */
if ($action === 'monthly_breakdown') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $year = $_GET['year'] ?? date('Y');
    
    $monthly = [];
    for ($m = 1; $m <= 12; $m++) {
        $month = sprintf('%s-%02d', $year, $m);
        $month_name = date('M', strtotime($month . '-01'));
        
        $revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$month'")->fetch_assoc()['total'];
        $expenses = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = '$month'")->fetch_assoc()['total'];
        $salaries = $conn->query("SELECT COALESCE(SUM(net_salary), 0) as total FROM salary_payments WHERE month = '$month' AND status = 'paid'")->fetch_assoc()['total'];
        
        $monthly[] = [
            'month' => $month_name,
            'revenue' => (float)$revenue,
            'expenses' => (float)$expenses,
            'salaries' => (float)$salaries,
            'net' => (float)($revenue - $expenses - $salaries)
        ];
    }
    
    echo json_encode($monthly);
    exit;
}

/* ================= YEARLY COMPARISON ================= */
if ($action === 'yearly_comparison') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $years = [];
    for ($y = date('Y') - 4; $y <= date('Y'); $y++) {
        $revenue = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE YEAR(payment_date) = '$y'")->fetch_assoc()['total'];
        $expenses = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM expenses WHERE YEAR(expense_date) = '$y'")->fetch_assoc()['total'];
        
        $years[] = [
            'year' => $y,
            'revenue' => (float)$revenue,
            'expenses' => (float)$expenses,
            'profit' => (float)($revenue - $expenses)
        ];
    }
    
    echo json_encode($years);
    exit;
}

/* ================= EXPENSE BREAKDOWN BY CATEGORY ================= */
if ($action === 'expense_breakdown') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $year = $_GET['year'] ?? date('Y');
    
    $categories = $conn->query("SELECT category, COALESCE(SUM(amount), 0) as total 
                              FROM expenses WHERE YEAR(expense_date) = '$year'
                              GROUP BY category ORDER BY total DESC");
    
    $breakdown = [];
    while ($row = $categories->fetch_assoc()) {
        $breakdown[] = [
            'category' => $row['category'],
            'amount' => (float)$row['total']
        ];
    }
    
    echo json_encode($breakdown);
    exit;
}

/* ================= UPDATE SCHOOL ACCOUNT ================= */
if ($action === 'update_account') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $amount = $data['amount'];
    $operation = $data['operation'];
    $description = $data['description'] ?? 'Account update';
    
    $account = $conn->query("SELECT balance FROM school_account LIMIT 1")->fetch_assoc();
    $current_balance = $account['balance'] ?? 0;
    
    if ($operation === 'add') {
        $new_balance = $current_balance + $amount;
    } else {
        $new_balance = $current_balance - $amount;
    }
    
    $conn->query("UPDATE school_account SET balance = $new_balance WHERE id = 1");
    log_audit($conn, $_SESSION['user_id'] ?? 0, 'account_adjusted', 'finance', 'account', 1, ['type' => $operation, 'amount' => $amount, 'description' => $description]);
    echo json_encode(["status" => "success", "new_balance" => $new_balance]);
    exit;
}

/* ================= GET SCHOOL ACCOUNT ================= */
if ($action === 'get_account') {
    $result = $conn->query("SELECT * FROM school_account LIMIT 1");
    echo json_encode($result->fetch_assoc());
    exit;
}

/* ================= DEVELOPMENT PROJECTS ================= */
if ($action === 'get_projects') {
    $status = $_GET['status'] ?? '';
    $sql = "SELECT * FROM development_projects WHERE 1=1";
    if ($status) $sql .= " AND status = '$status'";
    $sql .= " ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    if (!$result) {
        echo json_encode(["error" => $conn->error]);
        exit;
    }
    $projects = [];
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
    echo json_encode($projects);
    exit;
}

if ($action === 'save_project') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? null;
    
    $project_name = $data['project_name'];
    $description = $data['description'] ?? '';
    $project_type = $data['project_type'] ?? 'other';
    $budget = floatval($data['budget'] ?? 0);
    $start_date = $data['start_date'] ?? null;
    $expected_end_date = $data['expected_end_date'] ?? null;
    $status = $data['status'] ?? 'planning';
    $priority = $data['priority'] ?? 'medium';
    $notes = $data['notes'] ?? '';
    
    if ($id) {
        if (!empty($status) && empty($project_name)) {
            $conn->query("UPDATE development_projects SET status='$status' WHERE id=$id");
            echo json_encode(["status" => "success", "message" => "Status updated"]);
            exit;
        }
        $stmt = $conn->prepare("UPDATE development_projects SET 
            project_name=?, description=?, project_type=?, budget=?, 
            start_date=?, expected_end_date=?, status=?, priority=?, notes=?
            WHERE id=?");
        $stmt->bind_param("sssdissssi", $project_name, $description, $project_type, $budget,
                          $start_date, $expected_end_date, $status, $priority, $notes, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO development_projects 
            (project_name, description, project_type, budget, start_date, expected_end_date, status, priority, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdissss", $project_name, $description, $project_type, $budget,
                          $start_date, $expected_end_date, $status, $priority, $notes);
        $stmt->execute();
    }
    
    if ($stmt->error) {
        echo json_encode(["status" => "error", "message" => $stmt->error]);
        exit;
    }
    
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_project') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    $id = $_GET['id'];
    $conn->query("DELETE FROM development_projects WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

/* ================= PROJECT EXPENDITURE ================= */
if ($action === 'save_expenditure' || $action === 'add_project_expense') {
    $data = json_decode(file_get_contents("php://input"), true);
    $project_id = intval($data['project_id']);
    $amount = floatval($data['amount']);
    $user_id = $_SESSION['user_id'] ?? 0;
    
    $stmt = $conn->prepare("INSERT INTO project_expenditure (project_id, description, amount, expenditure_date, vendor_name, invoice_number, recorded_by)
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdsssi", $project_id, $data['description'], $amount, $data['expenditure_date'], 
                       $data['vendor_name'], $data['invoice_number'], $user_id);
    $stmt->execute();
    
    $conn->query("UPDATE development_projects SET spent = spent + $amount WHERE id = $project_id");
    update_account_balance($amount, 'subtract');
    
    echo json_encode(["status" => "success", "message" => "Expenditure added, balance updated"]);
    exit;
}

if ($action === 'get_project_expenses') {
    $project_id = $_GET['project_id'] ?? 0;
    $result = $conn->query("SELECT * FROM project_expenditure WHERE project_id = $project_id ORDER BY expenditure_date DESC");
    $expenses = [];
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    echo json_encode($expenses);
    exit;
}

/* ================= PROJECT MILESTONES ================= */
if ($action === 'get_milestones') {
    $project_id = $_GET['project_id'] ?? 0;
    $result = $conn->query("SELECT * FROM project_milestones WHERE project_id = $project_id ORDER BY due_date");
    $milestones = [];
    while ($row = $result->fetch_assoc()) {
        $milestones[] = $row;
    }
    echo json_encode($milestones);
    exit;
}

if ($action === 'save_milestone') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $stmt = $conn->prepare("INSERT INTO project_milestones (project_id, milestone_name, description, due_date)
                           VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $data['project_id'], $data['milestone_name'], $data['description'], $data['due_date']);
    $stmt->execute();
    
    echo json_encode(["status" => "success"]);
    exit;
}

/* ================= MANUAL ACCOUNT ADJUSTMENT ================= */
if ($action === 'adjust_account') {
    if (!$isAdmin) {
        http_response_code(403);
        exit;
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $amount = floatval($data['amount']);
    $type = $data['type'] ?? 'add';
    
    $new_balance = update_account_balance($amount, $type);
    
    echo json_encode(["status" => "success", "new_balance" => $new_balance]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);