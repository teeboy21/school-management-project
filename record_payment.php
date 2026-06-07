<?php
session_start();
$allowed_roles = ['admin', 'finance_manager'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.php");
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
<title>Record Payment - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
    .form-grid .full { grid-column: 1 / -1; }
    .search-input { padding: 10px 15px; border: 1px solid var(--app-border); border-radius: 6px; width: 100%; max-width: 400px; }
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; }
    .content-card { background: var(--app-card); padding: 15px; border-radius: 6px; border: 1px solid var(--app-border); cursor: pointer; }
    .content-card:hover { border-color: var(--app-primary); }
    .content-card strong { display: block; margin-bottom: 5px; }
    .content-card p { margin: 0; color: var(--app-muted); font-size: 12px; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--app-border); }
    .data-table th { background: var(--app-bg); font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--app-muted); }
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
                <div class="app-header-title">Record Payment</div>
                <div class="app-header-subtitle">Record student fee payments</div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom: 30px;">
                <h2>Select Student</h2>
                <div style="margin-bottom: 15px;">
                    <input type="text" id="studentSearch" class="search-input" placeholder="Search by name or student number..." oninput="searchStudents()">
                </div>
                <div id="studentResults" class="cards-grid" style="margin-bottom: 15px;"></div>
            </div>
            <div class="panel" style="margin-bottom: 30px;">
                <h2>Payment Details</h2>
                <form id="paymentForm" class="form-grid">
                    <div class="full">
                        <label>Selected Student</label>
                        <input type="text" id="paymentStudentName" readonly placeholder="Click a student above">
                        <input type="hidden" id="paymentStudentId">
                    </div>
                    <div><label>Outstanding Balance</label><input type="text" id="studentBalance" readonly value="—"></div>
                    <div><label>Amount</label><input type="number" id="paymentAmount" step="0.01" required></div>
                    <div>
                        <label>Payment Method</label>
                        <select id="paymentMethod">
                            <option value="cash">Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="card">Card</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div><label>Reference</label><input type="text" id="paymentRef"></div>
                    <div><label>Date</label><input type="date" id="paymentDate" value="<?= date('Y-m-d') ?>"></div>
                    <div class="full"><label>Notes</label><textarea id="paymentNotes"></textarea></div>
                    <div class="full page-actions">
                        <button type="submit" class="btn btn-primary">Record Payment</button>
                    </div>
                </form>
            </div>
            <div class="panel">
                <h2>Recent Payments</h2>
                <div style="margin-bottom: 15px;">
                    <select id="paymentYear" onchange="loadPayments()" style="padding: 8px 12px; border-radius: 6px;">
                        <?php foreach ($academic_years as $y): ?>
                        <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="paymentsList" class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Student</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }

async function loadStudents() {
    const res = await fetch('API/getstudents.php');
    const data = await res.json();
    window.allStudents = Array.isArray(data) ? data : (data.data || []);
}

function searchStudents() {
    const query = document.getElementById('studentSearch').value.toLowerCase();
    if(!query) {
        document.getElementById('studentResults').innerHTML = '';
        return;
    }
    const filtered = window.allStudents.filter(s => 
        (s.fullname||'').toLowerCase().includes(query) || (s.student_number||'').toLowerCase().includes(query)
    );
    document.getElementById('studentResults').innerHTML = filtered.slice(0, 10).map(s => `
        <div class="content-card" onclick="selectStudent(${s.id}, '${s.fullname}')">
            <strong>${s.fullname}</strong>
            <p>${s.student_number || 'No number'}</p>
        </div>
    `).join('') || '<p class="empty-state">No students found</p>';
}

function selectStudent(id, name) {
    document.getElementById('paymentStudentId').value = id;
    document.getElementById('paymentStudentName').value = name;
    document.getElementById('paymentAmount').value = '';
    document.getElementById('studentSearch').value = '';
    document.getElementById('studentResults').innerHTML = '';
    document.getElementById('studentBalance').value = 'Loading...';
    fetch('API/finance_api.php?action=get_student_balance&student_id=' + id)
        .then(r => r.json())
        .then(d => {
            if (d.status === 'paid' || d.status === 'none') {
                document.getElementById('studentBalance').value = 'R 0.00 (Cleared)';
            } else {
                document.getElementById('studentBalance').value = formatCurrency(d.balance);
                document.getElementById('studentBalance').dataset.balance = d.balance;
            }
        });
}

document.getElementById('paymentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const studentId = document.getElementById('paymentStudentId').value;
    if(!studentId) { alert('Please select a student'); return; }
    const amount = parseFloat(document.getElementById('paymentAmount').value);
    const balanceEl = document.getElementById('studentBalance');
    const maxBalance = parseFloat(balanceEl.dataset.balance || 0);
    if (maxBalance > 0 && amount > maxBalance) {
        alert('Amount (R ' + amount.toFixed(2) + ') exceeds outstanding balance (R ' + maxBalance.toFixed(2) + ')');
        return;
    }
    const payload = {
        student_id: parseInt(studentId),
        amount: amount,
        payment_method: document.getElementById('paymentMethod').value,
        reference_number: document.getElementById('paymentRef').value,
        payment_date: document.getElementById('paymentDate').value,
        notes: document.getElementById('paymentNotes').value
    };
    const res = await fetch('API/finance_api.php?action=record_payment', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    alert(data.status === 'success' ? 'Payment recorded!' : data.message || 'Error');
    document.getElementById('paymentForm').reset();
    document.getElementById('paymentDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('studentBalance').value = '—';
    delete document.getElementById('studentBalance').dataset.balance;
    loadPayments();
});

async function loadPayments() {
    const year = document.getElementById('paymentYear').value;
    const res = await fetch('API/finance_api.php?action=get_payments&year=' + year);
    const data = await res.json();
    if(!data.length) {
        document.querySelector('#paymentsList tbody').innerHTML = '<tr><td colspan="5" class="empty-state">No payments found</td></tr>';
        return;
    }
    document.querySelector('#paymentsList tbody').innerHTML = data.map(p => `
        <tr>
            <td>${p.payment_date}</td>
            <td>${p.fullname}</td>
            <td>${formatCurrency(p.amount)}</td>
            <td>${p.payment_method}</td>
            <td>${p.reference_number||'-'}</td>
        </tr>
    `).join('');
}

loadStudents(); loadPayments();
</script>
</body>
</html>