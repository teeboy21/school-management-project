<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'finance_manager'])) {
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
<title>Suppliers - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.form-grid .full { grid-column: 1 / -1; }
.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
.content-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); }
.content-card h3 { margin: 0 0 10px; font-size: 16px; }
.content-card p { margin: 5px 0; color: var(--app-muted); font-size: 14px; }
.badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.badge-active, .badge-active { background: #d4edda; color: #155724; }
.badge-expired { background: #f8d7da; color: #721c24; }
.badge-pending_renewal { background: #fff3cd; color: #856404; }
.badge-cancelled { background: #e2e3e5; color: #383d41; }
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
<div class="app-header-title">Supplier Contracts</div>
<div class="app-header-subtitle">Manage supplier contracts and agreements</div>
</div>
</header>
<section class="app-content">
<div class="panel" style="margin-bottom: 30px;">
<h2>Add Contract</h2>
<form id="contractForm" class="form-grid">
<input type="hidden" id="contractId">
<div><label>Supplier Name</label><input type="text" id="supplierName" required></div>
<div><label>Contact</label><input type="text" id="supplierContact"></div>
<div><label>Email</label><input type="email" id="supplierEmail"></div>
<div><label>Contract Type</label><input type="text" id="contractType"></div>
<div><label>Contract Value</label><input type="number" id="contractValue" step="0.01"></div>
<div><label>Start Date</label><input type="date" id="contractStart"></div>
<div><label>End Date</label><input type="date" id="contractEnd"></div>
<div><label>Status</label><select id="contractStatus"><option value="draft">Draft</option><option value="active">Active</option><option value="pending_renewal">Pending Renewal</option><option value="expired">Expired</option><option value="cancelled">Cancelled</option></select></div>
<div><label>Document Ref</label><input type="text" id="contractDocRef"></div>
<div class="full"><label>Terms</label><textarea id="contractTerms"></textarea></div>
<div class="full page-actions">
<button type="submit" class="btn btn-primary">Save Contract</button>
<button type="button" class="btn" onclick="clearForm()">Clear</button>
</div>
</form>
</div>
<div class="panel">
<h2>Supplier Contracts</h2>
<div style="margin-bottom: 15px;">
<select id="filterStatus" onchange="loadContracts()" style="padding: 8px 12px; border-radius: 6px;">
<option value="">All</option>
<option value="draft">Draft</option>
<option value="pending_approval">Pending Approval</option>
<option value="approved">Approved</option>
<option value="active">Active</option>
<option value="pending_renewal">Pending Renewal</option>
<option value="expired">Expired</option>
<option value="cancelled">Cancelled</option>
<option value="rejected">Rejected</option>
</select>
</div>
<div id="contractsList" class="cards-grid"></div>
</div>
</section>
</main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }
function statusBadge(s) {
    const map = {draft:'badge-pending',pending_approval:'badge-pending',approved:'badge-paid',active:'badge-active',pending_renewal:'badge-pending_renewal',expired:'badge-expired',cancelled:'badge-terminated',rejected:'badge-expired'};
    return `<span class="badge ${map[s]||'badge-pending'}">${(s||'draft').replace(/_/g,' ')}</span>`;
}

async function submitContract(id, amount) {
    if(!confirm('Submit this contract for approval?')) return;
    const res = await fetch('API/approval_api.php?action=submit_for_approval', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({reference_type: 'contract', reference_id: id, amount: amount, notes: 'Contract approval'})
    });
    const data = await res.json();
    alert(data.message || data.error);
    loadContracts();
}

async function activateContract(id) {
    if(!confirm('Activate this approved contract?')) return;
    await fetch('API/finance_api.php?action=update_contract_status&id='+id+'&status=active');
    loadContracts();
}

const contractForm = document.getElementById('contractForm');
if (contractForm) {
contractForm.addEventListener('submit', async (e) => {
e.preventDefault();
const payload = {
id: document.getElementById('contractId').value||null,
supplier_name: document.getElementById('supplierName').value,
supplier_contact: document.getElementById('supplierContact').value,
supplier_email: document.getElementById('supplierEmail').value,
contract_type: document.getElementById('contractType').value,
contract_value: parseFloat(document.getElementById('contractValue').value)||0,
start_date: document.getElementById('contractStart').value,
end_date: document.getElementById('contractEnd').value,
status: document.getElementById('contractStatus').value,
document_ref: document.getElementById('contractDocRef').value,
terms: document.getElementById('contractTerms').value
};
try {
const res = await fetch('API/finance_api.php?action=save_contract', {
method: 'POST', headers: {'Content-Type': 'application/json'},
body: JSON.stringify(payload)
});
const data = await res.json();
if (data.status === 'success') {
alert('Contract saved successfully!');
clearForm(); loadContracts();
} else {
alert(data.message || 'Failed to save contract');
}
} catch (e) {
alert('Error: ' + e.message);
}
});
}

function clearForm() {
document.getElementById('contractForm').reset();
document.getElementById('contractId').value = '';
}

async function loadContracts() {
try {
const status = document.getElementById('filterStatus').value;
const res = await fetch('API/finance_api.php?action=get_contracts' + (status ? '&status=' + status : ''));
const data = await res.json();
console.log('Contracts response:', data);
if (data.error) {
document.getElementById('contractsList').innerHTML = '<div class="empty-state">Error: ' + data.error + '</div>';
return;
}
if(!data || !data.length) {
document.getElementById('contractsList').innerHTML = '<div class="empty-state">No contracts found</div>';
return;
}
document.getElementById('contractsList').innerHTML = data.map(c => `
<div class="panel">
<div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:8px">
<div>
<h3 style="margin:0 0 4px">${c.supplier_name}</h3>
<p style="margin:0;color:var(--app-muted);font-size:13px">${c.contract_type || 'Contract'} | ${c.start_date || ''} - ${c.end_date || ''}</p>
</div>
<div style="text-align:right">
<div style="font-size:20px;font-weight:700">${formatCurrency(c.contract_value)}</div>
${statusBadge(c.status)}
</div>
</div>
<div class="page-actions">
${c.status === 'draft' ? `<button class="btn btn-small btn-primary" onclick="submitContract(${c.id},${c.contract_value})">Submit</button>` : ''}
${c.status === 'approved' ? `<button class="btn btn-small btn-success" onclick="activateContract(${c.id})">Activate</button>` : ''}
<button class="btn btn-small" onclick="editContract(${c.id})">Edit</button>
${c.status === 'draft' ? `<button class="btn btn-small btn-danger" onclick="deleteContract(${c.id})">Delete</button>` : ''}
</div>
</div>`).join('');
} catch(e) {
console.error(e);
document.getElementById('contractsList').innerHTML = '<div class="empty-state">Error loading contracts</div>';
}
}

function editContract(id) {
fetch('API/finance_api.php?action=get_contracts').then(r=>r.json()).then(data=>{
const c = data.find(x=>x.id===id);
if(c){
document.getElementById('contractId').value = c.id;
document.getElementById('supplierName').value = c.supplier_name;
document.getElementById('supplierContact').value = c.supplier_contact||'';
document.getElementById('supplierEmail').value = c.supplier_email||'';
document.getElementById('contractType').value = c.contract_type||'';
document.getElementById('contractValue').value = c.contract_value;
document.getElementById('contractStart').value = c.start_date;
document.getElementById('contractEnd').value = c.end_date;
document.getElementById('contractStatus').value = c.status;
document.getElementById('contractDocRef').value = c.document_ref||'';
document.getElementById('contractTerms').value = c.terms||'';
}
});
}

async function deleteContract(id) {
if(!confirm('Delete this contract?')) return;
await fetch('API/finance_api.php?action=delete_contract&id='+id);
loadContracts();
}

loadContracts();
console.log('Suppliers page loaded');
</script>
</body>
</html>