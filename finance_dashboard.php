<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'finance_manager') {
    header("Location: login.html");
    exit();
}

require_once 'system_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finance Dashboard</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
    <aside class="app-sidebar">
        <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
<ul class="app-nav">
<li class="active">Dashboard</li>
<li><a href="finance.php">Finance</a></li>
<li><a href="financial_reports.php">Financial Reports</a></li>
<li><a href="fees_structure.php">Fee Structures</a></li>
<li><a href="student_fees.php">Student Fees</a></li>
<li><a href="fees.php">My Fees</a></li>
<li><a href="expenses.php">Expenses</a></li>
<li><a href="record_payment.php">Record Payment</a></li>
<li><a href="budgets.php">Budgets</a></li>
<li><a href="pending_approvals.php">Approvals</a></li>
<li><a href="suppliers.php">Suppliers</a></li>
<li><a href="donations.php">Donations</a></li>
<li><a href="reports.php">Reports</a></li>
<li><a href="employee_profile.php">My Profile</a></li>
<li><a href="employee_salary.php">My Salary</a></li>
<li>
<form action="logout.php" method="post">
<button type="submit">Logout</button>
</form>
</li>
</ul>
    </aside>

    <main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Finance Dashboard</div>
                <div class="app-header-subtitle">Track fees, payments, expenses, and financial reports.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Finance Manager') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Welcome back</h2>
                <p>Use the shortcuts below to manage school finances.</p>
            </div>

            <div class="cards-grid">
                <div class="quick-card" onclick="window.location.href='finance.php'">
                    <div>Finance Overview</div>
                    <strong>Open</strong>
                    <p>Track fee collection, payments, and account balance.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='financial_reports.php'">
                    <div>Financial Reports</div>
                    <strong>Reports</strong>
                    <p>View monthly and yearly financial summaries.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='fees_structure.php'">
                    <div>Fee Structures</div>
                    <strong>Set Fees</strong>
                    <p>Define fee amounts per grade and academic year.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='student_fees.php'">
                    <div>Student Fees</div>
                    <strong>Generate</strong>
                    <p>Create and manage fee records for students.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='expenses.php'">
                    <div>Expenses</div>
                    <strong>Track</strong>
                    <p>Record and manage school expenses.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='record_payment.php'">
                    <div>Record Payment</div>
                    <strong>New</strong>
                    <p>Record a student fee payment.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='reports.php'">
                    <div>Reports</div>
                    <strong>Review</strong>
                    <p>View financial and operational reports.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='pending_approvals.php'">
                    <div>Pending Approvals</div>
                    <strong id="pendingApprovalCount" style="font-size:32px">...</strong>
                    <p>Review and approve financial requests</p>
                </div>
                <div class="quick-card" onclick="window.location.href='budgets.php'">
                    <div>Budgets</div>
                    <strong>Manage</strong>
                    <p>Allocate and track departmental budgets</p>
                </div>
                <div class="quick-card" onclick="window.location.href='suppliers.php'">
                    <div>Suppliers</div>
                    <strong>Contracts</strong>
                    <p>Manage supplier contracts and agreements.</p>
                </div>
                <div class="quick-card" onclick="window.location.href='donations.php'">
                    <div>Donations</div>
                    <strong>Track</strong>
                    <p>Record and review donor contributions.</p>
                </div>
            </div>
        </section>
        <script>
        fetch('API/approval_api.php?action=count_pending').then(r=>r.json()).then(d => {
            document.getElementById('pendingApprovalCount').textContent = d.count || 0;
        }).catch(() => { document.getElementById('pendingApprovalCount').textContent = '0'; });
        </script>
    </main>
</div>
</body>
</html>
