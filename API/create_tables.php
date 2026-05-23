<?php
require __DIR__ . '/../config.php';

// ===== ORIGINAL TABLES (unchanged) =====
$tables = [
    "CREATE TABLE IF NOT EXISTS employee_salaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        employee_type VARCHAR(50) DEFAULT 'teacher',
        basic_salary DECIMAL(12,2) DEFAULT 0,
        housing_allowance DECIMAL(12,2) DEFAULT 0,
        transport_allowance DECIMAL(12,2) DEFAULT 0,
        medical_allowance DECIMAL(12,2) DEFAULT 0,
        other_allowances DECIMAL(12,2) DEFAULT 0,
        deductions DECIMAL(12,2) DEFAULT 0,
        effective_date DATE,
        is_active TINYINT(1) DEFAULT 1,
        approval_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS salary_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        salary_id INT DEFAULT NULL,
        month VARCHAR(7) NOT NULL,
        gross_salary DECIMAL(12,2) DEFAULT 0,
        total_deductions DECIMAL(12,2) DEFAULT 0,
        net_salary DECIMAL(12,2) DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        approval_id INT DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        payment_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS supplier_contracts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_name VARCHAR(200) NOT NULL,
        supplier_contact VARCHAR(100),
        supplier_email VARCHAR(100),
        contract_type VARCHAR(50),
        contract_value DECIMAL(12,2) DEFAULT 0,
        start_date DATE,
        end_date DATE,
        renewal_date DATE,
        document_ref VARCHAR(100),
        terms TEXT,
        approval_id INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS donations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        donor_name VARCHAR(200),
        donor_type VARCHAR(50),
        donor_contact VARCHAR(100),
        donor_email VARCHAR(100),
        donation_type VARCHAR(50),
        amount DECIMAL(12,2) DEFAULT 0,
        donation_date DATE,
        purpose VARCHAR(200),
        receipt_number VARCHAR(50),
        notes TEXT,
        recorded_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS fee_structures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        grade_name VARCHAR(50) NOT NULL,
        academic_year VARCHAR(10) NOT NULL,
        tuition_fee DECIMAL(10,2) DEFAULT 0,
        registration_fee DECIMAL(10,2) DEFAULT 0,
        exam_fee DECIMAL(10,2) DEFAULT 0,
        library_fee DECIMAL(10,2) DEFAULT 0,
        sports_fee DECIMAL(10,2) DEFAULT 0,
        transport_fee DECIMAL(10,2) DEFAULT 0,
        other_fee DECIMAL(10,2) DEFAULT 0,
        due_date DATE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS student_fees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        academic_year VARCHAR(10) NOT NULL,
        fee_structure_id INT DEFAULT NULL,
        total_amount DECIMAL(10,2) DEFAULT 0,
        paid_amount DECIMAL(10,2) DEFAULT 0,
        balance DECIMAL(10,2) DEFAULT 0,
        status ENUM('pending','partial','paid','overpaid') DEFAULT 'pending',
        due_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS fee_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_fee_id INT NOT NULL,
        amount DECIMAL(12,2) DEFAULT 0,
        payment_date DATE,
        payment_method VARCHAR(50),
        reference_number VARCHAR(50),
        notes TEXT,
        recorded_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50) NOT NULL,
        description TEXT,
        vendor_name VARCHAR(200),
        amount DECIMAL(12,2) DEFAULT 0,
        expense_date DATE,
        receipt_number VARCHAR(100),
        budget_id INT DEFAULT NULL,
        approval_id INT DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        paid_at DATETIME DEFAULT NULL,
        paid_by INT DEFAULT NULL,
        status VARCHAR(30) DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS employees (
        user_id INT PRIMARY KEY,
        fullname VARCHAR(100) NOT NULL,
        employee_number VARCHAR(50) NOT NULL,
        phone VARCHAR(20) DEFAULT '',
        address VARCHAR(255) DEFAULT '',
        identity_number BIGINT(20) DEFAULT NULL,
        race ENUM('black','white','asian','other') DEFAULT NULL,
        dob DATE DEFAULT NULL,
        gender ENUM('male','female') DEFAULT NULL,
        hire_date DATE DEFAULT NULL,
        department VARCHAR(100) DEFAULT '',
        job_title VARCHAR(100) DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
    )",

    // ===== NEW APPROVAL SYSTEM TABLES =====

    "CREATE TABLE IF NOT EXISTS approval_workflows (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module VARCHAR(50) NOT NULL,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS approval_step_definitions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        workflow_id INT NOT NULL,
        step_order INT NOT NULL DEFAULT 0,
        role_required VARCHAR(50) NOT NULL,
        min_amount DECIMAL(14,2) DEFAULT 0,
        max_amount DECIMAL(14,2) DEFAULT 999999999.99,
        label VARCHAR(100),
        FOREIGN KEY (workflow_id) REFERENCES approval_workflows(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS approval_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        module VARCHAR(50) NOT NULL,
        reference_type VARCHAR(50) NOT NULL,
        reference_id INT NOT NULL,
        workflow_id INT DEFAULT NULL,
        current_step INT DEFAULT NULL,
        amount DECIMAL(14,2) DEFAULT 0,
        status ENUM('draft','pending_approval','partially_approved','approved','rejected','cancelled') DEFAULT 'draft',
        requested_by INT DEFAULT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS approval_actions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        actor_id INT NOT NULL,
        step_definition_id INT DEFAULT NULL,
        action ENUM('approved','rejected','returned','escalated') NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES approval_requests(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS budgets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(100) NOT NULL,
        fiscal_year VARCHAR(10) NOT NULL,
        allocated_amount DECIMAL(14,2) DEFAULT 0,
        spent_amount DECIMAL(14,2) DEFAULT 0,
        status VARCHAR(30) DEFAULT 'draft',
        approval_id INT DEFAULT NULL,
        created_by INT DEFAULT NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS account_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('income','expense','transfer','adjustment') NOT NULL,
        category VARCHAR(50) DEFAULT NULL,
        description TEXT,
        amount DECIMAL(14,2) NOT NULL DEFAULT 0,
        balance_before DECIMAL(14,2) DEFAULT 0,
        balance_after DECIMAL(14,2) DEFAULT 0,
        reference_type VARCHAR(50) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        recorded_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        module VARCHAR(50) DEFAULT NULL,
        reference_type VARCHAR(50) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        details TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS teacher_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        student_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_rating (teacher_id, student_id)
    )",

    "CREATE TABLE IF NOT EXISTS approvals_view (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_id INT NOT NULL,
        seen TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES approval_requests(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    if ($conn->query($sql)) {
        echo "OK: " . substr($sql, 21, strpos($sql, "(") - 22) . "\n";
    } else {
        echo "ERR: " . $conn->error . "\n";
    }
}

// ===== MIGRATIONS: Add missing columns to existing tables =====
$migrations = [
    // Donations - add recorded_by if missing
    "ALTER TABLE donations ADD COLUMN IF NOT EXISTS recorded_by INT DEFAULT NULL AFTER notes",
    // Fee_payments - add recorded_by if missing
    "ALTER TABLE fee_payments ADD COLUMN IF NOT EXISTS recorded_by INT DEFAULT NULL AFTER notes",
    // Salary_payments - add approval_id, recorded_by if missing
    "ALTER TABLE salary_payments ADD COLUMN IF NOT EXISTS approval_id INT DEFAULT NULL AFTER status",
    "ALTER TABLE salary_payments ADD COLUMN IF NOT EXISTS recorded_by INT DEFAULT NULL AFTER approval_id",
    "ALTER TABLE salary_payments ADD COLUMN IF NOT EXISTS salary_id INT DEFAULT NULL AFTER employee_id",
    // Employee_salaries - add approval_id if missing
    "ALTER TABLE employee_salaries ADD COLUMN IF NOT EXISTS approval_id INT DEFAULT NULL AFTER is_active",
    // Supplier_contracts - add approval_id, created_by if missing
    "ALTER TABLE supplier_contracts ADD COLUMN IF NOT EXISTS approval_id INT DEFAULT NULL AFTER terms",
    "ALTER TABLE supplier_contracts ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER approval_id",
    "ALTER TABLE supplier_contracts ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    // Account lock columns
    "ALTER TABLE user ADD COLUMN IF NOT EXISTS locked TINYINT(1) DEFAULT 0",
    "ALTER TABLE user ADD COLUMN IF NOT EXISTS login_attempts INT DEFAULT 0",
    "ALTER TABLE user ADD COLUMN IF NOT EXISTS locked_at DATETIME DEFAULT NULL",
    "ALTER TABLE user ADD COLUMN IF NOT EXISTS locked_reason TEXT DEFAULT NULL",
];

foreach ($migrations as $sql) {
    if ($conn->query($sql)) {
        echo "MIG: " . substr($sql, 0, 60) . "\n";
    } else {
        // "IF NOT EXISTS" not supported in MySQL - column may already exist
        if ($conn->errno != 1060) { // 1060 = duplicate column
            echo "MIG ERR: " . $conn->error . " [$sql]\n";
        }
    }
}

// ===== SEED DEFAULT APPROVAL WORKFLOWS =====
$workflow_check = $conn->query("SELECT COUNT(*) as c FROM approval_workflows")->fetch_assoc();
if ($workflow_check['c'] == 0) {
    echo "\n--- Seeding default approval workflows ---\n";

    // Expense workflow
    $conn->query("INSERT INTO approval_workflows (module, name, description) VALUES ('expense', 'Expense Approval', 'Approval chain for school expenses')");
    $wf_id = $conn->insert_id;
    $conn->query("INSERT INTO approval_step_definitions (workflow_id, step_order, role_required, min_amount, max_amount, label) VALUES
        ($wf_id, 1, 'finance_manager', 0, 4999.99, 'Finance Manager Review'),
        ($wf_id, 2, 'finance_manager', 5000, 999999999.99, 'Finance Manager Review'),
        ($wf_id, 3, 'principal', 5000, 49999.99, 'Principal Approval'),
        ($wf_id, 4, 'principal', 50000, 999999999.99, 'Principal Review'),
        ($wf_id, 5, 'admin', 50000, 999999999.99, 'Executive Approval')");
    echo "  Created expense workflow ($wf_id)\n";

    // Budget workflow
    $conn->query("INSERT INTO approval_workflows (module, name, description) VALUES ('budget', 'Budget Approval', 'Approval chain for budget allocations')");
    $wf_id = $conn->insert_id;
    $conn->query("INSERT INTO approval_step_definitions (workflow_id, step_order, role_required, min_amount, max_amount, label) VALUES
        ($wf_id, 1, 'finance_manager', 0, 999999999.99, 'Finance Manager Review'),
        ($wf_id, 2, 'principal', 0, 999999999.99, 'Principal Approval')");
    echo "  Created budget workflow ($wf_id)\n";

    // Contract workflow
    $conn->query("INSERT INTO approval_workflows (module, name, description) VALUES ('contract', 'Contract Approval', 'Approval chain for supplier contracts')");
    $wf_id = $conn->insert_id;
    $conn->query("INSERT INTO approval_step_definitions (workflow_id, step_order, role_required, min_amount, max_amount, label) VALUES
        ($wf_id, 1, 'finance_manager', 0, 999999999.99, 'Finance Manager Review'),
        ($wf_id, 2, 'principal', 10000, 999999999.99, 'Principal Approval'),
        ($wf_id, 3, 'admin', 50000, 999999999.99, 'Executive Approval')");
    echo "  Created contract workflow ($wf_id)\n";

    // Salary change workflow
    $conn->query("INSERT INTO approval_workflows (module, name, description) VALUES ('salary_change', 'Salary Change Approval', 'Approval chain for salary rate changes')");
    $wf_id = $conn->insert_id;
    $conn->query("INSERT INTO approval_step_definitions (workflow_id, step_order, role_required, min_amount, max_amount, label) VALUES
        ($wf_id, 1, 'hr_manager', 0, 999999999.99, 'HR Manager Review'),
        ($wf_id, 2, 'principal', 0, 999999999.99, 'Principal Approval')");
    echo "  Created salary_change workflow ($wf_id)\n";

    // Payroll approval workflow
    $conn->query("INSERT INTO approval_workflows (module, name, description) VALUES ('payroll', 'Payroll Approval', 'Approval chain for monthly payroll processing')");
    $wf_id = $conn->insert_id;
    $conn->query("INSERT INTO approval_step_definitions (workflow_id, step_order, role_required, min_amount, max_amount, label) VALUES
        ($wf_id, 1, 'finance_manager', 0, 999999999.99, 'Finance Manager Review'),
        ($wf_id, 2, 'principal', 0, 999999999.99, 'Principal Approval')");
    echo "  Created payroll workflow ($wf_id)\n";
}

// ===== ENSURE school_account table exists =====
$conn->query("CREATE TABLE IF NOT EXISTS school_account (
    id INT PRIMARY KEY DEFAULT 1,
    balance DECIMAL(14,2) DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");
$conn->query("INSERT IGNORE INTO school_account (id, balance) VALUES (1, 0)");

// Ensure employee role exists
$conn->query("INSERT IGNORE INTO roles (id, role_name, dashboard_url) VALUES (8, 'employee', 'employeedashboard.php')");

echo "\nDone!";
