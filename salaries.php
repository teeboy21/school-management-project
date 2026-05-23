<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager', 'finance_manager'])) {
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
<title>Salaries - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.form-grid .full { grid-column: 1 / -1; }
.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; }
.content-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); }
.content-card h3 { margin: 0 0 10px; font-size: 16px; }
.content-card p { margin: 5px 0; color: var(--app-muted); font-size: 14px; }
.content-card .amount { font-size: 20px; font-weight: 700; }
.data-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
.data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--app-border); }
.data-table th { background: var(--app-bg); font-weight: 600; font-size: 12px; text-transform: uppercase; }
.stat-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); }
.stat-card .label { font-size: 12px; color: var(--app-muted); text-transform: uppercase; }
.stat-card .value { font-size: 24px; font-weight: 700; }
.page-actions { margin-top: 15px; display: flex; gap: 10px; }
.empty-state { text-align: center; padding: 40px; color: var(--app-muted); }
.badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.badge-paid { background: #d4edda; color: #155724; }
.badge-pending { background: #fff3cd; color: #856404; }
.hero-card { background: #e0f2fe; border: 1px solid #0284c7; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Salaries & Payroll</div>
<div class="app-header-subtitle">Set employee salary rates and process monthly payments</div>
</div>
</header>
<section class="app-content">
<div class="hero-card">
<h3>How Salaries Work</h3>
<p><strong>1. Set Salary</strong> - Enter the monthly salary rate for each employee (basic + allowances)</p>
<p><strong>2. Process Payroll</strong> - Run payroll to create payment records for a month. This records what gets paid.</p>
<p><strong>3. Payment</strong> - When you mark as paid, the amount is deducted from school account.</p>
<p><em>Tip: You can process salaries for any past or future month. Process each month once.</em></p>
</div>

<div class="panel" style="margin-bottom: 30px;">
<h2>1. Set Employee Monthly Salary</h2>
<form id="salaryForm" class="form-grid">
<input type="hidden" id="salaryId">
<div><label>Employee</label><select id="salaryEmployee" required></select></div>
<div><label>Basic Salary (monthly)</label><input type="number" id="basicSalary" step="0.01" required></div>
<div><label>Housing Allowance</label><input type="number" id="housingAllowance" step="0.01" value="0"></div>
<div><label>Transport Allowance</label><input type="number" id="transportAllowance" step="0.01" value="0"></div>
<div><label>Medical Allowance</label><input type="number" id="medicalAllowance" step="0.01" value="0"></div>
<div><label>Other Allowances</label><input type="number" id="otherAllowances" step="0.01" value="0"></div>
<div><label>Deductions</label><input type="number" id="salaryDeductions" step="0.01" value="0"></div>
<div><label>Effective From</label><input type="date" id="salaryEffective" value="<?= date('Y-m-01') ?>"></div>
<div class="full page-actions">
<button type="submit" class="btn btn-primary">Save Salary Rate</button>
<button type="button" class="btn" onclick="clearForm()">Clear</button>
</div>
</form>
</div>

<div class="panel" style="margin-bottom: 30px;">
<h2>2. Current Salary Rates</h2>
<div id="salariesList" class="cards-grid"></div>
</div>

<div class="panel">
<h2>3. Process Monthly Payroll</h2>
<p style="margin-bottom:15px;">Creates payment records. Run this each month to record salaries.</p>
<div style="display:flex;gap:15px;align-items:center;margin-bottom:15px;flex-wrap:wrap;">
<div><label>Select Month</label>
<select id="processMonth" style="padding:8px 12px;border-radius:6px;">
<?php for($m=1; $m<=12; $m++): ?>
<option value="<?= sprintf('%d-%02d',$current_year,$m) ?>"><?= sprintf('%d-%02d',$current_year,$m) ?></option>
<?php endfor; ?>
</select></div>
<button type="button" class="btn btn-primary" onclick="processSalaries()">Process This Month</button>
<button type="button" class="btn" onclick="processYear()">Process Full Year (<?= $current_year ?>)</button>
</div>
<div id="salaryPaymentsList">
<table class="data-table"><thead><tr><th>Employee</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th>Action</th></tr></thead><tbody></tbody></table>
</div>
</div>
</section>
</main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }
function statusBadge(s) {
    const map = {draft:'badge-pending',pending_approval:'badge-pending',approved:'badge-paid',paid:'badge-paid',pending:'badge-pending',rejected:'badge-expired',cancelled:'badge-terminated'};
    return `<span class="badge ${map[s]||'badge-pending'}">${(s||'pending').replace(/_/g,' ')}</span>`;
}

async function loadEmployees() {
    try {
        const res = await fetch('API/finance_api.php?action=get_employees');
        const data = await res.json();
        if (data.error) { alert(data.error); return; }
        if (!data.length) { document.getElementById('salaryEmployee').innerHTML = '<option>No employees</option>'; return; }
        document.getElementById('salaryEmployee').innerHTML = '<option value="">Select Employee</option>' + 
            data.map(e => '<option value="'+e.id+'">'+e.fullname+'</option>').join('');
    } catch(e) { console.error(e); }
}

async function loadSalaries() {
    try {
        const res = await fetch('API/finance_api.php?action=get_salaries');
        const data = await res.json();
        if (data.error) { document.getElementById('salariesList').innerHTML = '<p class="empty-state">Error: '+data.error+'</p>'; return; }
        if (!data.length) { document.getElementById('salariesList').innerHTML = '<p class="empty-state">No salaries set. Add employee salaries above.</p>'; return; }
        document.getElementById('salariesList').innerHTML = data.map(s => {
            const gross = (s.basic_salary||0) + (s.housing_allowance||0) + (s.transport_allowance||0) + (s.medical_allowance||0) + (s.other_allowances||0);
            const net = gross - (s.deductions||0);
            return `<div class="content-card">
                <h3>${s.fullname}</h3>
                <p>Basic: ${formatCurrency(s.basic_salary)}</p>
                <p>Housing: ${formatCurrency(s.housing_allowance)} | Transport: ${formatCurrency(s.transport_allowance)}</p>
                <p class="amount">Net/Month: ${formatCurrency(net)}</p>
                <div class="page-actions">
                    <button class="btn btn-small" onclick="editSalary(${s.id})">Edit</button>
                    <button class="btn btn-small btn-danger" onclick="deleteSalary(${s.id})">Delete</button>
                </div>
            </div>`;
        }).join('');
    } catch(e) { console.error(e); }
}

const salaryForm = document.getElementById('salaryForm');
if (salaryForm) {
salaryForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const employeeId = document.getElementById('salaryEmployee').value;
    if (!employeeId) { alert('Select employee'); return; }
    const basicSalary = parseFloat(document.getElementById('basicSalary').value);
    if (!basicSalary) { alert('Enter basic salary'); return; }
    const payload = {
        id: document.getElementById('salaryId').value||null,
        employee_id: parseInt(employeeId),
        basic_salary: basicSalary,
        housing_allowance: parseFloat(document.getElementById('housingAllowance').value)||0,
        transport_allowance: parseFloat(document.getElementById('transportAllowance').value)||0,
        medical_allowance: parseFloat(document.getElementById('medicalAllowance').value)||0,
        other_allowances: parseFloat(document.getElementById('otherAllowances').value)||0,
        deductions: parseFloat(document.getElementById('salaryDeductions').value)||0,
        effective_date: document.getElementById('salaryEffective').value
    };
    try {
        const res = await fetch('API/finance_api.php?action=save_salary', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') { alert('Salary saved!'); clearForm(); loadSalaries(); }
        else { alert(data.message || 'Failed'); }
    } catch(e) { alert('Error: ' + e.message); }
});
}

function clearForm() {
    document.getElementById('salaryForm').reset();
    document.getElementById('salaryId').value = '';
    document.getElementById('salaryEffective').value = '<?= date('Y-m-01') ?>';
}

function editSalary(id) {
    fetch('API/finance_api.php?action=get_salaries').then(r=>r.json()).then(data=>{
        const s = data.find(x=>x.id===id);
        if(s) {
            document.getElementById('salaryId').value = s.id;
            document.getElementById('salaryEmployee').value = s.employee_id;
            document.getElementById('basicSalary').value = s.basic_salary;
            document.getElementById('housingAllowance').value = s.housing_allowance||0;
            document.getElementById('transportAllowance').value = s.transport_allowance||0;
            document.getElementById('medicalAllowance').value = s.medical_allowance||0;
            document.getElementById('otherAllowances').value = s.other_allowances||0;
            document.getElementById('salaryDeductions').value = s.deductions||0;
            document.getElementById('salaryEffective').value = s.effective_date;
        }
    });
}

async function deleteSalary(id) {
    if(!confirm('Delete this salary?')) return;
    await fetch('API/finance_api.php?action=delete_salary&id='+id);
    loadSalaries();
}

async function processSalaries() {
    const month = document.getElementById('processMonth').value;
    if(!confirm('Process salaries for ' + month + '?')) return;
    const res = await fetch('API/finance_api.php?action=process_salaries&month=' + month);
    const data = await res.json();
    alert(data.status === 'success' ? 'Processed ' + (data.processed||0) + ' salaries for ' + month : data.message || 'Error');
    loadSalaryPayments(month);
}

async function processYear() {
    if(!confirm('Process salaries for ALL 12 months of <?= $current_year ?>? This may take a moment.')) return;
    const months = [];
    for(let m=1; m<=12; m++) months.push('<?= $current_year ?>-' + (m<10?'0'+m:m));
    let processed = 0;
    for(const month of months) {
        await fetch('API/finance_api.php?action=process_salaries&month=' + month);
        processed++;
    }
    alert('Processed ' + processed + ' months');
    loadSalaryPayments(document.getElementById('processMonth').value);
}

async function loadSalaryPayments(month) {
    try {
        const res = await fetch('API/finance_api.php?action=get_salary_payments&month=' + month);
        const data = await res.json();
    const tbody = document.querySelector('#salaryPaymentsList tbody');
    if(!data.length) { tbody.innerHTML = '<tr><td colspan="6" class="empty-state">No payments for this month</td></tr>'; return; }
    tbody.innerHTML = data.map(p => `<tr>
        <td>${p.fullname}</td>
        <td>${formatCurrency(p.gross_salary)}</td>
        <td>${formatCurrency(p.total_deductions)}</td>
        <td>${formatCurrency(p.net_salary)}</td>
        <td>${statusBadge(p.status)}</td>
        <td>
            ${p.status === 'pending' ? `<button class="btn btn-small btn-primary" onclick="submitPayroll(${p.id},${p.net_salary})">Submit</button>` : ''}
            ${p.status === 'approved' ? `<button class="btn btn-small btn-success" onclick="processPayrollPay(${p.id})">Pay</button>` : ''}
        </td>
    </tr>`).join('');
    } catch(e) { console.error(e); }
}

async function submitPayroll(id, amount) {
    if(!confirm('Submit this salary payment for approval?')) return;
    const res = await fetch('API/approval_api.php?action=submit_for_approval', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({reference_type: 'payroll', reference_id: id, amount: amount, notes: 'Payroll payment approval'})
    });
    const data = await res.json();
    alert(data.message || data.error);
    loadSalaryPayments(document.getElementById('processMonth').value);
}

async function processPayrollPay(id) {
    if(!confirm('Mark this salary as paid? Amount will be deducted from school account.')) return;
    const res = await fetch('API/finance_api.php?action=pay_salary&id=' + id);
    const data = await res.json();
    alert(data.message || data.error);
    loadSalaryPayments(document.getElementById('processMonth').value);
}

loadEmployees();
loadSalaries();
loadSalaryPayments('<?= date('Y-m') ?>');
</script>
</body>
</html>