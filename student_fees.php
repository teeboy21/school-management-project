<?php
session_start();
$allowed_roles = ['admin', 'finance_manager'];
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
<title>Student Fees - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--app-border); }
    .data-table th { background: var(--app-bg); font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--app-muted); }
    .data-table tr:hover { background: var(--app-bg); }
    .badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
    .badge-paid { background: #d4edda; color: #155724; }
    .badge-pending { background: #fff3cd; color: #856404; }
    .badge-partial { background: #cce5ff; color: #004085; }
    .badge-overpaid { background: #e2e3e5; color: #383d41; }
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
                <div class="app-header-title">Student Fee Records</div>
                <div class="app-header-subtitle">Generate and manage individual student fee records</div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom: 30px;">
                <h2>Generate Student Fees</h2>
                <p style="color: var(--app-muted); margin-bottom: 15px;">Create fee records for all students based on fee structures.</p>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <select id="generateYear" style="padding: 8px 12px; border-radius: 6px;">
                        <?php foreach ($academic_years as $y): ?>
                        <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="btn btn-primary" onclick="generateFees()">Generate Fees</button>
                </div>
            </div>
            <div class="panel" style="margin-bottom: 30px;">
                <h2>Recent Payments</h2>
                <div id="recentPayments" class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Student</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="panel">
                <h2>Fee Records</h2>
                <div style="margin-bottom: 15px;">
                    <select id="filterYear" onchange="loadFees()" style="padding: 8px 12px; border-radius: 6px;">
                        <?php foreach ($academic_years as $y): ?>
                        <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="feesList" class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Student #</th><th>Name</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }

async function generateFees() {
    const year = document.getElementById('generateYear').value;
    if(!confirm('Generate fees for all students in ' + year + '?')) return;
    const res = await fetch('API/finance_api.php?action=create_student_fees&year=' + year);
    const data = await res.json();
    alert(data.status === 'success' ? 'Created ' + data.created + ' fee records' : data.message || 'Error');
    loadFees();
}

async function loadFees() {
    const year = document.getElementById('filterYear').value;
    const res = await fetch('API/finance_api.php?action=get_student_fees&year=' + year);
    const data = await res.json();
    if(!data.length) {
        document.querySelector('#feesList tbody').innerHTML = '<tr><td colspan="6" class="empty-state">No fee records found</td></tr>';
        return;
    }
    document.querySelector('#feesList tbody').innerHTML = data.map(f => `
        <tr>
            <td>${f.student_number || 'N/A'}</td>
            <td>${f.fullname}</td>
            <td>${formatCurrency(f.total_amount)}</td>
            <td>${formatCurrency(f.paid_amount)}</td>
            <td>${formatCurrency(f.balance)}</td>
            <td><span class="badge badge-${f.status}">${f.status}</span></td>
        </tr>
    `).join('');
}

async function loadRecentPayments() {
    const year = document.getElementById('filterYear').value;
    const res = await fetch('API/finance_api.php?action=get_payments&year=' + year);
    const data = await res.json();
    if(!data.length) {
        document.querySelector('#recentPayments tbody').innerHTML = '<tr><td colspan="5" class="empty-state">No payments recorded</td></tr>';
        return;
    }
    document.querySelector('#recentPayments tbody').innerHTML = data.slice(0, 20).map(p => `
        <tr>
            <td>${p.payment_date}</td>
            <td>${p.fullname || 'N/A'}</td>
            <td>${formatCurrency(p.amount)}</td>
            <td>${p.payment_method || 'cash'}</td>
            <td>${p.reference_number || '-'}</td>
        </tr>
    `).join('');
}

loadRecentPayments();
loadFees();