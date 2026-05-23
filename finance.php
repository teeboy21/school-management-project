<?php
session_start();
$allowed_roles = ['admin', 'finance_manager', 'principal'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.html");
    exit();
}
require_once 'config.php';

$current_year = date('Y');
$academic_years = [];
for ($y = $current_year; $y >= $current_year - 5; $y--) {
    $academic_years[] = "$y-" . ($y + 1);
}
$dash_map = ['admin'=>'admindashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','hr_manager'=>'hr_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Finance - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
    .stat-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); }
    .stat-card .label { font-size: 12px; color: var(--app-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-card .value { font-size: 24px; font-weight: 700; color: var(--app-text); margin-top: 5px; }
    .menu-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
    .menu-card { background: var(--app-card); padding: 25px; border-radius: 8px; border: 1px solid var(--app-border); text-decoration: none; display: block; transition: all 0.2s; }
    .menu-card:hover { border-color: var(--app-primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    .menu-card h3 { margin: 0 0 8px; font-size: 18px; color: var(--app-text); }
    .menu-card p { margin: 0; color: var(--app-muted); font-size: 14px; }
    .panel { margin-bottom: 30px; }
    .panel h2 { margin: 0 0 15px; font-size: 20px; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--app-border); }
    .data-table th { background: var(--app-bg); font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--app-muted); }
    .data-table tr:hover { background: var(--app-bg); }
    .search-input { padding: 10px 15px; border: 1px solid var(--app-border); border-radius: 6px; width: 100%; max-width: 400px; }
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
    .content-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); cursor: pointer; }
    .content-card h3 { margin: 0 0 10px; font-size: 16px; }
    .content-card p { margin: 5px 0; color: var(--app-muted); font-size: 14px; }
    .empty-state { text-align: center; padding: 40px; color: var(--app-muted); }
    .page-actions { margin-top: 15px; display: flex; gap: 10px; }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Finance Department</div>
                <div class="app-header-subtitle">Overview of school finances</div>
            </div>
            <div style="margin-left: auto;">
                <select id="dashboardYear" onchange="loadDashboard()" style="padding: 8px 12px; border-radius: 6px; border: 1px solid var(--app-border);">
                    <?php foreach ($academic_years as $y): ?>
                    <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </header>
        <section class="app-content">
            <div class="stats-grid" id="dashboardStats">
                <div class="stat-card"><div class="label">Total Fees</div><div class="value" id="statFees">R 0.00</div></div>
                <div class="stat-card"><div class="label">Collected</div><div class="value" id="statPaid">R 0.00</div></div>
                <div class="stat-card"><div class="label">Account Balance</div><div class="value" id="statBalance">R 0.00</div></div>
                <div class="stat-card"><div class="label">Pending Fees</div><div class="value" id="statPending">0</div></div>
                <div class="stat-card"><div class="label">Expenses</div><div class="value" id="statExpenses">R 0.00</div></div>
                <div class="stat-card"><div class="label">Salaries</div><div class="value" id="statSalaries">R 0.00</div></div>
                <div class="stat-card"><div class="label">Donations</div><div class="value" id="statDonations">R 0.00</div></div>
            </div>
            
            <div class="panel">
                <h2>Finance Modules</h2>
                <div class="menu-grid">
                    <a href="fees_structure.php" class="menu-card">
                        <h3>Fee Structures</h3>
                        <p>Set fee amounts per grade and academic year</p>
                    </a>
                    <a href="student_fees.php" class="menu-card">
                        <h3>Student Fees</h3>
                        <p>Generate and manage student fee records</p>
                    </a>
                    <a href="record_payment.php" class="menu-card">
                        <h3>Record Payment</h3>
                        <p>Record student fee payments</p>
                    </a>
                    <a href="expenses.php" class="menu-card">
                        <h3>Expenses</h3>
                        <p>Track school expenses by category</p>
                    </a>
                    <a href="salaries.php" class="menu-card">
                        <h3>Salaries & Payroll</h3>
                        <p>Manage salaries and process monthly payroll</p>
                    </a>
                    <a href="donations.php" class="menu-card">
                        <h3>Donations</h3>
                        <p>Track donor contributions</p>
                    </a>
                    <a href="suppliers.php" class="menu-card">
                        <h3>Suppliers</h3>
                        <p>Manage supplier contracts</p>
                    </a>
                    <a href="budgets.php" class="menu-card">
                        <h3>Budgets</h3>
                        <p>Allocate and track departmental budgets</p>
                    </a>
                    <a href="pending_approvals.php" class="menu-card">
                        <h3>Approvals</h3>
                        <p>Review and manage approval requests</p>
                    </a>
                    <a href="audit_log.php" class="menu-card">
                        <h3>Audit Log</h3>
                        <p>View financial audit trail</p>
                    </a>
                </div>
            </div>

            <div class="panel" id="pendingApprovalsPanel" style="display:none">
                <h2>Pending Approvals <span id="pendingBadge" class="badge badge-pending">0</span></h2>
                <div id="pendingApprovalsList" class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Type</th><th>Item</th><th>Amount</th><th>Requester</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div style="margin-top:10px"><a href="pending_approvals.php" style="color:var(--app-primary)">View all approvals &rarr;</a></div>
            </div>

            <div class="panel">
                <h2>Recent Fee Payments</h2>
                <div id="recentPayments" class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Student</th><th>Amount</th><th>Method</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            
            <div class="panel">
                <h2>Recent Salary Payments</h2>
                <div id="recentSalaries" class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Month</th><th>Employee</th><th>Net Salary</th><th>Status</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }

async function loadDashboard() {
    const year = document.getElementById('dashboardYear').value;
    const res = await fetch('API/finance_api.php?action=dashboard_stats&year=' + year);
    const data = await res.json();
    document.getElementById('statFees').textContent = formatCurrency(data.total_fees);
    document.getElementById('statPaid').textContent = formatCurrency(data.total_paid);
    document.getElementById('statBalance').textContent = formatCurrency(data.account_balance);
    document.getElementById('statPending').textContent = data.pending_fees;
    document.getElementById('statExpenses').textContent = formatCurrency(data.total_expenses);
    document.getElementById('statSalaries').textContent = formatCurrency(data.total_salaries);
    document.getElementById('statDonations').textContent = formatCurrency(data.total_donations);
    
    loadRecentPayments();
}

async function loadRecentPayments() {
    const year = document.getElementById('dashboardYear').value;
    const res = await fetch('API/finance_api.php?action=get_payments&year=' + year);
    const data = await res.json();
    if(!data.length) {
        document.querySelector('#recentPayments tbody').innerHTML = '<tr><td colspan="4" class="empty-state">No payments found</td></tr>';
        return;
    }
    document.querySelector('#recentPayments tbody').innerHTML = data.slice(0, 10).map(p => `
        <tr>
            <td>${p.payment_date}</td>
            <td>${p.fullname}</td>
            <td>${formatCurrency(p.amount)}</td>
            <td>${p.payment_method}</td>
        </tr>
    `).join('');
}

async function loadRecentSalaries() {
    try {
        const res = await fetch('API/hr_api.php?action=get_salary_payments');
        if (!res.ok) {
            document.querySelector('#recentSalaries tbody').innerHTML = '<tr><td colspan="4" class="empty-state">Error loading</td></tr>';
            return;
        }
        const data = await res.json();
        if(!data || !data.length) {
            document.querySelector('#recentSalaries tbody').innerHTML = '<tr><td colspan="4" class="empty-state">No salary payments</td></tr>';
            return;
        }
        document.querySelector('#recentSalaries tbody').innerHTML = data.slice(0, 10).map(p => `
            <tr>
                <td>${p.month}</td>
                <td>${p.fullname || 'N/A'}</td>
                <td>${formatCurrency(p.net_salary)}</td>
                <td><span class="badge badge-${p.status}">${p.status}</span></td>
            </tr>
        `).join('');
    } catch(e) {
        document.querySelector('#recentSalaries tbody').innerHTML = '<tr><td colspan="4" class="empty-state">Error: '+e.message+'</td></tr>';
    }
}

async function loadPendingApprovals() {
    try {
        const res = await fetch('API/approval_api.php?action=get_approval_summary');
        const s = await res.json();
        if (s.pending_for_me > 0 || s.total_pending > 0) {
            document.getElementById('pendingApprovalsPanel').style.display = 'block';
            document.getElementById('pendingBadge').textContent = s.pending_for_me;
        }
        const res2 = await fetch('API/approval_api.php?action=get_pending_approvals&limit=5');
        const data = await res2.json();
        if (data.length) {
            document.querySelector('#pendingApprovalsList tbody').innerHTML = data.slice(0, 5).map(r => `
                <tr>
                    <td>${r.reference_type.toUpperCase()}</td>
                    <td>#${r.reference_id} - ${r.requester_name || ''}</td>
                    <td>${formatCurrency(r.amount)}</td>
                    <td>${r.requester_name || 'N/A'}</td>
                </tr>
            `).join('');
        }
    } catch(e) {}
}

loadDashboard();
loadRecentSalaries();
loadPendingApprovals();
</script>
</body>
</html>