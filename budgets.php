<?php
session_start();
$allowed_roles = ['admin', 'finance_manager'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.html");
    exit();
}
require_once 'config.php';
$current_year = date('Y');
$dash_map = ['admin'=>'admindashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','hr_manager'=>'hr_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Budgets - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; }
.form-grid .full { grid-column:1/-1; }
.cards-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr)); gap:15px; }
.content-card { background:var(--app-card); padding:20px; border-radius:8px; border:1px solid var(--app-border); }
.content-card h3 { margin:0 0 10px; font-size:16px; }
.page-actions { margin-top:15px; display:flex; gap:10px; }
.empty-state { text-align:center; padding:40px; color:var(--app-muted); }
.progress-bar { height:8px; background:var(--app-bg); border-radius:4px; overflow:hidden; margin:8px 0; }
.progress-fill { height:100%; border-radius:4px; transition:width 0.3s; }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Budget Management</div>
<div class="app-header-subtitle">Allocate and track departmental budgets</div>
</div>
</header>
<section class="app-content">
<div class="panel" style="margin-bottom:30px">
<h2>Create / Edit Budget</h2>
<form id="budgetForm" class="form-grid">
<input type="hidden" id="budgetId">
<div><label>Category</label><select id="budgetCategory" required>
<option value="Supplies">Supplies</option>
<option value="Maintenance">Maintenance</option>
<option value="Utilities">Utilities</option>
<option value="Transport">Transport</option>
<option value="Technology">Technology</option>
<option value="Events">Events</option>
<option value="Sports">Sports</option>
<option value="Library">Library</option>
<option value="Security">Security</option>
<option value="Catering">Catering</option>
<option value="Infrastructure">Infrastructure</option>
<option value="Other">Other</option>
</select></div>
<div><label>Fiscal Year</label><select id="budgetYear">
<option value="<?= $current_year ?>"><?= $current_year ?></option>
<option value="<?= $current_year + 1 ?>"><?= $current_year + 1 ?></option>
</select></div>
<div><label>Allocated Amount (R)</label><input type="number" id="budgetAmount" step="0.01" required></div>
<div class="full"><label>Notes</label><textarea id="budgetNotes"></textarea></div>
<div class="full page-actions">
<button type="submit" class="btn btn-primary">Save Budget</button>
<button type="button" class="btn" onclick="clearForm()">Clear</button>
</div>
</form>
</div>

<div class="toolbar">
<h2>Budgets</h2>
<select id="yearFilter" onchange="loadBudgets()" style="padding:8px 12px;border-radius:6px">
<option value="<?= $current_year ?>"><?= $current_year ?></option>
<option value="<?= $current_year + 1 ?>"><?= $current_year + 1 ?></option>
</select>
</div>
<div id="budgetsList" class="cards-grid"></div>
</section>
</main>
</div>

<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }
function statusBadge(s) {
    const map = {draft:'badge-pending',pending_approval:'badge-pending',approved:'badge-paid',active:'badge-paid',closed:'badge-terminated',cancelled:'badge-terminated',rejected:'badge-expired'};
    return `<span class="badge ${map[s]||'badge-pending'}">${s.replace(/_/g,' ')}</span>`;
}

document.getElementById('budgetForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        id: document.getElementById('budgetId').value || null,
        category: document.getElementById('budgetCategory').value,
        fiscal_year: document.getElementById('budgetYear').value,
        allocated_amount: parseFloat(document.getElementById('budgetAmount').value),
        notes: document.getElementById('budgetNotes').value,
        status: document.getElementById('budgetId').value ? undefined : 'draft'
    };
    const res = await fetch('API/finance_api.php?action=save_budget', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.status === 'success') {
        alert('Budget saved!');
        clearForm();
        loadBudgets();
    } else {
        alert(data.message || data.error || 'Error');
    }
});

async function loadBudgets() {
    const year = document.getElementById('yearFilter').value;
    const res = await fetch('API/finance_api.php?action=get_budgets&year=' + year);
    const data = await res.json();
    const container = document.getElementById('budgetsList');
    if (!data.length) {
        container.innerHTML = '<div class="empty-state" style="grid-column:1/-1">No budgets found for this year</div>';
        return;
    }
    container.innerHTML = data.map(b => {
        const pct = b.allocated_amount > 0 ? Math.min(100, (b.spent_amount / b.allocated_amount) * 100) : 0;
        const barColor = pct > 90 ? '#dc2626' : pct > 75 ? '#d97706' : '#16a34a';
        return `<div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:start">
                <div>
                    <h3 style="margin:0 0 4px">${b.category}</h3>
                    <div style="color:var(--app-muted);font-size:13px">${b.fiscal_year}</div>
                </div>
                <div>${statusBadge(b.status)}</div>
            </div>
            <div style="margin:12px 0">
                <div style="display:flex;justify-content:space-between;font-size:13px">
                    <span>Allocated: <strong>${formatCurrency(b.allocated_amount)}</strong></span>
                    <span>Spent: <strong>${formatCurrency(b.spent_amount)}</strong></span>
                </div>
                <div class="progress-bar"><div class="progress-fill" style="width:${pct}%;background:${barColor}"></div></div>
                <div style="text-align:right;font-size:12px;color:var(--app-muted)">${pct.toFixed(1)}% used</div>
            </div>
            <div class="page-actions">
                ${b.status === 'draft' ? `<button class="btn btn-small btn-primary" onclick="submitForApproval(${b.id})">Submit for Approval</button>` : ''}
                ${b.status === 'approved' || b.status === 'active' ? `<button class="btn btn-small btn-success" onclick="activateBudget(${b.id})">Activate</button>` : ''}
                ${b.status === 'active' ? `<button class="btn btn-small btn-warning" onclick="closeBudget(${b.id})">Close</button>` : ''}
                <button class="btn btn-small btn-ghost" onclick="editBudget(${b.id})">Edit</button>
                ${b.status === 'draft' ? `<button class="btn btn-small btn-danger" onclick="deleteBudget(${b.id})">Delete</button>` : ''}
                ${b.approval_id ? `<button class="btn btn-small" onclick="viewApproval(${b.approval_id})">Approval</button>` : ''}
            </div>
        </div>`;
    }).join('');
}

async function submitForApproval(id) {
    const res = await fetch('API/budget_api.php?action=submit_budget', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({budget_id: id})
    });
    const data = await res.json();
    alert(data.message || data.error);
    loadBudgets();
}

function editBudget(id) {
    fetch('API/finance_api.php?action=get_budgets').then(r=>r.json()).then(data => {
        const b = data.find(x => x.id === id);
        if (b) {
            document.getElementById('budgetId').value = b.id;
            document.getElementById('budgetCategory').value = b.category;
            document.getElementById('budgetYear').value = b.fiscal_year;
            document.getElementById('budgetAmount').value = b.allocated_amount;
            document.getElementById('budgetNotes').value = b.notes || '';
        }
    });
}

function deleteBudget(id) {
    if (!confirm('Delete this budget?')) return;
    fetch('API/finance_api.php?action=delete_budget&id=' + id).then(() => loadBudgets());
}

function activateBudget(id) {
    fetch('API/finance_api.php?action=update_budget_status&id=' + id + '&status=active').then(() => loadBudgets());
}

function closeBudget(id) {
    fetch('API/finance_api.php?action=update_budget_status&id=' + id + '&status=closed').then(() => loadBudgets());
}

function viewApproval(id) {
    window.location.href = 'pending_approvals.php';
}

function clearForm() {
    document.getElementById('budgetForm').reset();
    document.getElementById('budgetId').value = '';
}

loadBudgets();
</script>
</body>
</html>
