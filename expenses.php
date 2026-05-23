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
<title>Expenses - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
    .form-grid .full { grid-column: 1 / -1; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--app-border); }
    .data-table th { background: var(--app-bg); font-weight: 600; font-size: 12px; text-transform: uppercase; color: var(--app-muted); }
    .data-table tr:hover { background: var(--app-bg); }
    .page-actions { margin-top: 15px; display: flex; gap: 10px; }
    .empty-state { text-align: center; padding: 40px; color: var(--app-muted); }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Expenses</div>
                <div class="app-header-subtitle">Track school expenses by category</div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom: 30px;">
                <h2>Add Expense</h2>
                <form id="expenseForm" class="form-grid">
                    <input type="hidden" id="expenseId">
                    <div>
                        <label>Category</label>
                        <select id="expenseCategory" required>
                            <option value="Supplies">Supplies</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Utilities">Utilities</option>
                            <option value="Transport">Transport</option>
                            <option value="Catering">Catering</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div><label>Description</label><input type="text" id="expenseDesc" required></div>
                    <div><label>Amount</label><input type="number" id="expenseAmount" step="0.01" required></div>
                    <div><label>Vendor</label><input type="text" id="expenseVendor"></div>
                    <div><label>Date</label><input type="date" id="expenseDate" value="<?= date('Y-m-d') ?>"></div>
                    <div><label>Receipt #</label><input type="text" id="expenseReceipt"></div>
                    <div class="full page-actions">
                        <button type="submit" class="btn btn-primary">Save Expense</button>
                        <button type="button" class="btn" onclick="clearForm()">Clear</button>
                    </div>
                </form>
            </div>
            <div class="panel">
                <h2>Expense Records</h2>
                <div style="margin-bottom: 15px;">
                    <select id="filterYear" onchange="loadExpenses()" style="padding: 8px 12px; border-radius: 6px;">
                        <?php foreach ($academic_years as $y): ?>
                        <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="expensesList" class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Vendor</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }
function statusBadge(s) {
    const map = {draft:'badge-pending',pending_approval:'badge-pending',approved:'badge-paid',rejected:'badge-expired',paid:'badge-paid',cancelled:'badge-terminated'};
    return `<span class="badge ${map[s]||'badge-pending'}">${(s||'draft').replace(/_/g,' ')}</span>`;
}

async function submitExpense(id, amount) {
    if(!confirm('Submit this expense for approval?')) return;
    const res = await fetch('API/approval_api.php?action=submit_for_approval', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({reference_type: 'expense', reference_id: id, amount: amount, notes: 'Expense approval'})
    });
    const data = await res.json();
    alert(data.message || data.error);
    loadExpenses();
}

async function payExpense(id) {
    if(!confirm('Mark this expense as paid? This will deduct from the school account.')) return;
    const res = await fetch('API/finance_api.php?action=pay_expense&id=' + id);
    const data = await res.json();
    alert(data.message || data.error);
    loadExpenses();
}

document.getElementById('expenseForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const payload = {
        id: document.getElementById('expenseId').value||null,
        category: document.getElementById('expenseCategory').value,
        description: document.getElementById('expenseDesc').value,
        amount: parseFloat(document.getElementById('expenseAmount').value),
        vendor_name: document.getElementById('expenseVendor').value,
        expense_date: document.getElementById('expenseDate').value,
        receipt_number: document.getElementById('expenseReceipt').value
    };
    
    try {
        const res = await fetch('API/finance_api.php?action=save_expense', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            alert('Expense saved successfully!');
            clearForm(); loadExpenses();
        } else {
            alert(data.message || 'Failed to save expense');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
});

function clearForm() {
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseDate').value = '<?= date('Y-m-d') ?>';
}

async function loadExpenses() {
    try {
        const year = document.getElementById('filterYear').value;
        const res = await fetch('API/finance_api.php?action=get_expenses&year=' + year);
        const data = await res.json();
        console.log('Expenses response:', data);
        if (data.error) {
            document.querySelector('#expensesList tbody').innerHTML = '<tr><td colspan="6" class="empty-state">Error: ' + data.error + '</td></tr>';
            return;
        }
        if(!data.length) {
            document.querySelector('#expensesList tbody').innerHTML = '<tr><td colspan="6" class="empty-state">No expenses found</td></tr>';
            return;
        }
        document.querySelector('#expensesList tbody').innerHTML = data.map(e => `
            <tr>
                <td>${e.expense_date}</td>
                <td>${e.category}</td>
                <td>${e.description}</td>
                <td>${e.vendor_name||'-'}</td>
                <td>${formatCurrency(e.amount)}</td>
                <td>${statusBadge(e.status)}</td>
                <td>
                    ${e.status === 'draft' ? `<button class="btn btn-small btn-primary" onclick="submitExpense(${e.id},${e.amount})">Submit</button>` : ''}
                    ${e.status === 'approved' ? `<button class="btn btn-small btn-success" onclick="payExpense(${e.id})">Pay</button>` : ''}
                    <button class="btn btn-small" onclick="editExpense(${e.id})">Edit</button>
                    ${e.status === 'draft' ? `<button class="btn btn-small btn-danger" onclick="deleteExpense(${e.id})">Delete</button>` : ''}
                </td>
            </tr>
        `).join('');
    } catch (e) {
        console.error(e);
    }
}

function editExpense(id) {
    fetch('API/finance_api.php?action=get_expenses&year=' + document.getElementById('filterYear').value)
        .then(r=>r.json()).then(data=>{
            const e = data.find(x=>x.id===id);
            if(e){
                document.getElementById('expenseId').value = e.id;
                document.getElementById('expenseCategory').value = e.category;
                document.getElementById('expenseDesc').value = e.description;
                document.getElementById('expenseAmount').value = e.amount;
                document.getElementById('expenseVendor').value = e.vendor_name||'';
                document.getElementById('expenseDate').value = e.expense_date;
                document.getElementById('expenseReceipt').value = e.receipt_number||'';
            }
        });
}

async function deleteExpense(id) {
    if(!confirm('Delete this expense?')) return;
    await fetch('API/finance_api.php?action=delete_expense&id='+id);
    loadExpenses();
}

loadExpenses();
</script>
</body>
</html>