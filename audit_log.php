<?php
session_start();
$allowed_roles = ['admin', 'it_technician'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.html");
    exit();
}
require_once 'config.php';
$dash_map = ['admin'=>'admindashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Audit Log - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
.filter-bar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:20px; }
.filter-bar select, .filter-bar input { padding:8px 12px; border-radius:6px; border:1px solid var(--app-border); }
.data-table { width:100%; border-collapse:collapse; }
.data-table th, .data-table td { padding:12px; text-align:left; border-bottom:1px solid var(--app-border); }
.data-table th { background:var(--app-bg); font-weight:600; font-size:12px; text-transform:uppercase; color:var(--app-muted); }
.data-table tr:hover { background:var(--app-bg); }
.details-json { font-size:11px; color:var(--app-muted); max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Audit Log</div>
<div class="app-header-subtitle">Track all financial actions and approvals</div>
</div>
</header>
<section class="app-content">
<div class="toolbar">
<h2>Activity Log</h2>
<div class="filter-bar">
<select id="moduleFilter" onchange="loadLog()">
<option value="">All Modules</option>
<option value="auth">Authentication</option>
<option value="registration">Registrations</option>
<option value="approval">Approvals</option>
<option value="finance">Finance</option>
<option value="expense">Expenses</option>
<option value="budget">Budgets</option>
<option value="donation">Donations</option>
<option value="payroll">Payroll</option>
<option value="contract">Contracts</option>
</select>
<select id="actionFilter" onchange="loadLog()">
<option value="">All Actions</option>
<option value="login_success">Login Success</option>
<option value="login_failed">Login Failed</option>
<option value="login_blocked">Login Blocked</option>
<option value="student_registered">Student Registered</option>
<option value="teacher_registered">Teacher Registered</option>
<option value="employee_registered">Employee Registered</option>
<option value="fee_payment">Fee Payment</option>
<option value="expense_created">Expense Created</option>
<option value="expense_paid">Expense Paid</option>
<option value="salary_saved">Salary Saved</option>
<option value="payroll_processed">Payroll Processed</option>
<option value="salary_paid">Salary Paid</option>
<option value="donation_created">Donation Created</option>
<option value="contract_saved">Contract Saved</option>
<option value="budget_saved">Budget Saved</option>
<option value="submitted_for_approval">Submitted</option>
<option value="approved_step">Step Approved</option>
<option value="fully_approved">Fully Approved</option>
<option value="rejected">Rejected</option>
<option value="cancelled">Cancelled</option>
</select>
<button class="btn btn-small" onclick="loadLog()">Refresh</button>
</div>
</div>
<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Date/Time</th><th>User</th><th>Action</th><th>Module</th><th>Reference</th><th>Details</th></tr></thead>
<tbody id="logBody"></tbody>
</table>
</div>
</section>
</main>
</div>

<script>
async function loadLog() {
    const module = document.getElementById('moduleFilter').value;
    const action = document.getElementById('actionFilter').value;
    const params = new URLSearchParams({action: 'get_audit_log', limit: 200});
    if (module) params.set('module', module);
    if (action) params.set('action_filter', action);
    const res = await fetch('API/audit_api.php?' + params);
    const data = await res.json();
    const tbody = document.getElementById('logBody');
    if (!data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No audit records found</td></tr>';
        return;
    }
    tbody.innerHTML = data.map(r => {
        let details = '';
        try {
            const d = JSON.parse(r.details || '{}');
            details = Object.entries(d).map(([k,v]) => `${k}: ${v}`).join(', ');
        } catch(e) { details = r.details || ''; }
        return `<tr>
            <td style="white-space:nowrap;font-size:13px">${new Date(r.created_at).toLocaleString('en-ZA')}</td>
            <td><strong>${r.user_name || 'N/A'}</strong></td>
            <td><span class="badge badge-${r.action === 'fully_approved' || r.action === 'auto_approved' ? 'paid' : r.action === 'rejected' ? 'expired' : r.action === 'submitted_for_approval' ? 'pending' : 'pending'}">${r.action.replace(/_/g,' ')}</span></td>
            <td>${r.module || '-'}</td>
            <td>${r.reference_type || '-'} #${r.reference_id || '-'}</td>
            <td class="details-json" title="${details.replace(/"/g,'&quot;')}">${details || '-'}</td>
        </tr>`;
    }).join('');
}

loadLog();
setInterval(loadLog, 30000);
</script>
</body>
</html>
