<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.html");
    exit();
}
require_once 'config.php';
$dash_map = ['admin'=>'admindashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','hr_manager'=>'hr_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approval Workflows - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:15px; }
.form-grid .full { grid-column:1/-1; }
.page-actions { margin-top:15px; display:flex; gap:10px; }
.empty-state { text-align:center; padding:40px; color:var(--app-muted); }
.role-badge { display:inline-block; padding:3px 8px; border-radius:4px; font-size:11px; font-weight:600; background:#e8f0ff; color:#1d4ed8; }
.step-row { display:flex; gap:10px; align-items:center; padding:10px 12px; background:var(--app-bg); border-radius:8px; margin-bottom:8px; flex-wrap:wrap; }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Approval Workflows</div>
<div class="app-header-subtitle">Configure approval chains for finance actions</div>
</div>
</header>
<section class="app-content">
<div class="panel" style="margin-bottom:30px">
<h2>Create / Edit Workflow</h2>
<form id="workflowForm" class="form-grid">
<input type="hidden" id="wfId">
<div><label>Module</label><select id="wfModule" required>
<option value="expense">Expense</option>
<option value="budget">Budget</option>
<option value="contract">Contract</option>
<option value="salary_change">Salary Change</option>
<option value="payroll">Payroll</option>
</select></div>
<div><label>Name</label><input type="text" id="wfName" required></div>
<div class="full"><label>Description</label><textarea id="wfDesc"></textarea></div>
<div class="full page-actions">
<button type="submit" class="btn btn-primary">Save Workflow</button>
<button type="button" class="btn" onclick="clearWorkflow()">Clear</button>
</div>
</form>
</div>

<div class="panel" style="margin-bottom:30px">
<h2>Workflow Steps</h2>
<p style="color:var(--app-muted);margin-bottom:15px">Configure who approves what, based on amount thresholds.</p>
<form id="stepForm" class="form-grid">
<input type="hidden" id="stepId">
<input type="hidden" id="stepWfId">
<div><label>Workflow</label><select id="stepWorkflow" required onchange="loadSteps()"></select></div>
<div><label>Step Order</label><input type="number" id="stepOrder" value="1" min="1"></div>
<div><label>Role Required</label><select id="stepRole"><option value="finance_manager">Finance Manager</option><option value="hr_manager">HR Manager</option><option value="principal">Principal</option><option value="admin">Executive (Admin)</option></select></div>
<div><label>Label</label><input type="text" id="stepLabel" placeholder="e.g. Finance Manager Review"></div>
<div><label>Min Amount (R)</label><input type="number" id="stepMin" step="0.01" value="0"></div>
<div><label>Max Amount (R)</label><input type="number" id="stepMax" step="0.01" value="999999999.99"></div>
<div class="full page-actions">
<button type="submit" class="btn btn-primary">Save Step</button>
<button type="button" class="btn" onclick="clearStep()">Clear</button>
</div>
</form>
</div>

<div class="panel">
<h2>Steps for Selected Workflow</h2>
<div id="stepsList" class="empty-state">Select a workflow above</div>
</div>
</section>
</main>
</div>

<script>
async function loadWorkflows() {
    const res = await fetch('API/approval_api.php?action=get_workflows');
    const data = await res.json();
    const sel = document.getElementById('stepWorkflow');
    sel.innerHTML = '<option value="">Select workflow</option>' + data.map(w => `<option value="${w.id}">${w.name} (${w.module})</option>`).join('');
    const list = document.getElementById('stepsList');
    if (!data.length) {
        list.innerHTML = '<div class="empty-state">No workflows configured. Create one above.</div>';
        return;
    }
    list.innerHTML = data.map(w => `<div class="panel" style="margin-bottom:15px">
        <div style="display:flex;justify-content:space-between;align-items:start">
            <div>
                <strong>${w.name}</strong> <span class="badge badge-${w.module}">${w.module}</span>
                <div style="color:var(--app-muted);font-size:13px">${w.description || ''}</div>
            </div>
            <div style="display:flex;gap:8px">
                <button class="btn btn-small" onclick="editWorkflow(${w.id})">Edit</button>
                <button class="btn btn-small btn-danger" onclick="deleteWorkflow(${w.id})">Delete</button>
            </div>
        </div>
        ${(w.steps||[]).length ? `<div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--app-border)">
            ${w.steps.map((s,i) => `<div class="step-row">
                <span class="role-badge">Step ${s.step_order}</span>
                <span class="role-badge">${s.role_required}</span>
                <span>${s.label}</span>
                <span style="color:var(--app-muted);font-size:12px">R ${parseFloat(s.min_amount).toLocaleString()} - R ${parseFloat(s.max_amount).toLocaleString()}</span>
                <button class="btn btn-small btn-ghost" onclick="editStep(${s.id},${w.id})">Edit</button>
                <button class="btn btn-small btn-danger" onclick="deleteStep(${s.id})">X</button>
            </div>`).join('')}
        </div>` : '<div style="color:var(--app-muted);font-size:13px;margin-top:8px">No steps defined</div>'}
    </div>`).join('');
}

document.getElementById('workflowForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
        id: document.getElementById('wfId').value || null,
        module: document.getElementById('wfModule').value,
        name: document.getElementById('wfName').value,
        description: document.getElementById('wfDesc').value
    };
    const res = await fetch('API/approval_api.php?action=save_workflow', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const data = await res.json();
    alert(data.status === 'success' ? 'Workflow saved' : data.error || 'Error');
    clearWorkflow();
    loadWorkflows();
});

document.getElementById('stepForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const wfId = document.getElementById('stepWfId').value || document.getElementById('stepWorkflow').value;
    if (!wfId) { alert('Select a workflow first'); return; }
    const payload = {
        id: document.getElementById('stepId').value || null,
        workflow_id: parseInt(wfId),
        step_order: parseInt(document.getElementById('stepOrder').value),
        role_required: document.getElementById('stepRole').value,
        label: document.getElementById('stepLabel').value,
        min_amount: parseFloat(document.getElementById('stepMin').value) || 0,
        max_amount: parseFloat(document.getElementById('stepMax').value) || 999999999.99
    };
    const res = await fetch('API/approval_api.php?action=save_workflow_step', {
        method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload)
    });
    const data = await res.json();
    alert(data.status === 'success' ? 'Step saved' : 'Error');
    clearStep();
    loadWorkflows();
    loadSteps();
});

function editWorkflow(id) {
    fetch('API/approval_api.php?action=get_workflows').then(r=>r.json()).then(data => {
        const w = data.find(x => x.id === id);
        if (w) {
            document.getElementById('wfId').value = w.id;
            document.getElementById('wfModule').value = w.module;
            document.getElementById('wfName').value = w.name;
            document.getElementById('wfDesc').value = w.description || '';
        }
    });
}

function deleteWorkflow(id) {
    if (!confirm('Delete this workflow and all its steps?')) return;
    fetch('API/approval_api.php?action=delete_workflow&id='+id).then(() => loadWorkflows());
}

function clearWorkflow() {
    document.getElementById('workflowForm').reset();
    document.getElementById('wfId').value = '';
}

function editStep(id, wfId) {
    fetch('API/approval_api.php?action=get_workflows').then(r=>r.json()).then(data => {
        for (const w of data) {
            const s = (w.steps||[]).find(x => x.id === id);
            if (s) {
                document.getElementById('stepId').value = s.id;
                document.getElementById('stepWfId').value = wfId;
                document.getElementById('stepWorkflow').value = wfId;
                document.getElementById('stepOrder').value = s.step_order;
                document.getElementById('stepRole').value = s.role_required;
                document.getElementById('stepLabel').value = s.label;
                document.getElementById('stepMin').value = s.min_amount;
                document.getElementById('stepMax').value = s.max_amount;
                loadSteps();
                return;
            }
        }
    });
}

function deleteStep(id) {
    if (!confirm('Delete this step?')) return;
    fetch('API/approval_api.php?action=delete_workflow_step&id='+id).then(() => { loadWorkflows(); loadSteps(); });
}

function clearStep() {
    document.getElementById('stepForm').reset();
    document.getElementById('stepId').value = '';
    document.getElementById('stepWfId').value = '';
}

async function loadSteps() {
    const wfId = document.getElementById('stepWorkflow').value;
    const container = document.getElementById('stepsList');
    if (!wfId) { container.innerHTML = '<div class="empty-state">Select a workflow to see its steps</div>'; return; }
    const res = await fetch('API/approval_api.php?action=get_workflows');
    const data = await res.json();
    const wf = data.find(w => w.id == wfId);
    if (!wf || !wf.steps.length) { container.innerHTML = '<div class="empty-state">No steps defined for this workflow</div>'; return; }
    container.innerHTML = wf.steps.map((s,i) => `<div class="step-row">
        <span class="role-badge">Step ${s.step_order}</span>
        <span class="role-badge">${s.role_required}</span>
        <span>${s.label}</span>
        <span style="color:var(--app-muted);font-size:12px">R ${parseFloat(s.min_amount).toLocaleString()} - R ${parseFloat(s.max_amount).toLocaleString()}</span>
        <button class="btn btn-small btn-ghost" onclick="editStep(${s.id},${wf.id})">Edit</button>
        <button class="btn btn-small btn-danger" onclick="deleteStep(${s.id})">X</button>
    </div>`).join('');
}

loadWorkflows();
</script>
</body>
</html>
