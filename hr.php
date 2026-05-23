<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.html"); exit(); }
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HR & Payroll</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
    <main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">HR & Payroll</div>
                <div class="app-header-subtitle">Manage employee salaries, contracts, and payroll processing.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? '') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        
        <section class="app-content">
            <div class="cards-grid" style="margin-bottom:20px;">
                <div class="stat-card"><div>Monthly Payroll</div><strong id="monthlyPayroll">R 0</strong></div>
                <div class="stat-card"><div>Total Salaries</div><strong id="totalSalaries">R 0</strong></div>
                <div class="stat-card"><div>Active Employees</div><strong id="activeContracts">0</strong></div>
                <div class="stat-card"><div>Total Donations</div><strong id="totalDonations">R 0</strong></div>
            </div>
            
            <div class="panel" style="margin-bottom:20px;">
                <h2>Set Employee Salary</h2>
                <form id="salaryForm" class="form-grid">
                    <input type="hidden" id="salaryId">
                    <div>
                        <label>Employee</label>
                        <select id="teacherSelect" required></select>
                    </div>
                    <div>
                        <label>Basic Salary (R)</label>
                        <input type="number" id="basicSalary" step="0.01" required placeholder="0.00">
                    </div>
                    <div>
                        <label>Housing Allowance (R)</label>
                        <input type="number" id="housingAllowance" step="0.01" value="0">
                    </div>
                    <div>
                        <label>Transport Allowance (R)</label>
                        <input type="number" id="transportAllowance" step="0.01" value="0">
                    </div>
                    <div>
                        <label>Medical Allowance (R)</label>
                        <input type="number" id="medicalAllowance" step="0.01" value="0">
                    </div>
                    <div>
                        <label>Other Allowances (R)</label>
                        <input type="number" id="otherAllowances" step="0.01" value="0">
                    </div>
                    <div>
                        <label>Date Effective</label>
                        <input type="date" id="effectiveDate" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="page-actions" style="grid-column:span 2;">
                        <button type="submit" class="btn btn-primary">Save Salary</button>
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">Clear</button>
                    </div>
                </form>
            </div>
            
            <div class="panel" style="margin-bottom:20px;">
                <h2>Employee Salaries</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Basic</th>
                                <th>Housing</th>
                                <th>Transport</th>
                                <th>Medical</th>
                                <th>Other</th>
                                <th>Gross</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="salariesTable">
                            <tr><td colspan="8">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="panel" style="margin-bottom:20px;">
                <h2>Process Monthly Payroll</h2>
                <form id="processForm" class="form-grid">
                    <div>
                        <label>Select Month</label>
                        <input type="month" id="processMonth" value="<?= date('Y-m') ?>">
                    </div>
                    <div class="page-actions">
                        <button type="submit" class="btn btn-primary">Generate Payslips</button>
                    </div>
                </form>
            </div>
            
            <div class="panel">
                <h2>Processed Salary Payments</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Month</th><th>Employee</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<script>
function fmt(a) { return 'R ' + parseFloat(a||0).toLocaleString(); }

function loadStats() {
    fetch('API/hr_api.php?action=hr_stats')
    .then(r => r.json())
    .then(d => {
        document.getElementById('monthlyPayroll').textContent = fmt(d.monthly_payroll);
        document.getElementById('totalSalaries').textContent = fmt(d.total_salaries);
        document.getElementById('activeContracts').textContent = d.active_contracts || 0;
        document.getElementById('totalDonations').textContent = fmt(d.total_donations);
    });
}

function loadTeachers() {
    fetch('API/finance_api.php?action=get_employees')
    .then(r => r.json())
    .then(d => {
        var s = document.getElementById('teacherSelect');
        s.innerHTML = '<option value="">Select Teacher</option>';
        d.forEach(t => { s.innerHTML += '<option value="'+t.id+'">'+t.fullname+'</option>'; });
    });
}

function loadSalaries() {
    fetch('API/finance_api.php?action=get_salaries')
    .then(r => r.json())
    .then(d => {
        var t = document.getElementById('salariesTable');
        if (!d || !d.length) {
            t.innerHTML = '<tr><td colspan="8">No salaries set</td></tr>';
            return;
        }
        var h = '';
        d.forEach(s => {
            var b = parseFloat(s.basic_salary)||0;
            var ho = parseFloat(s.housing_allowance)||0;
            var tr = parseFloat(s.transport_allowance)||0;
            var m = parseFloat(s.medical_allowance)||0;
            var o = parseFloat(s.other_allowances)||0;
            var g = b + ho + tr + m + o;
            h += '<tr>';
            h += '<td>'+(s.fullname||'N/A')+'</td>';
            h += '<td>'+fmt(b)+'</td>';
            h += '<td>'+fmt(ho)+'</td>';
            h += '<td>'+fmt(tr)+'</td>';
            h += '<td>'+fmt(m)+'</td>';
            h += '<td>'+fmt(o)+'</td>';
            h += '<td>'+fmt(g)+'</td>';
            h += '<td><button class="btn btn-small" onclick="editSalary('+s.id+')">Edit</button> <button class="btn btn-small btn-danger" onclick="deleteSalary('+s.id+')">Delete</button></td>';
            h += '</tr>';
        });
        t.innerHTML = h;
    });
}

function loadSalaryPayments() {
    fetch('API/hr_api.php?action=get_salary_payments')
    .then(r => r.json())
    .then(d => {
        var t = document.getElementById('salaryPayments').querySelector('tbody');
        if (!d || !d.length) {
            t.innerHTML = '<tr><td colspan="6">No salary payments processed</td></tr>';
            return;
        }
        var h = '';
        d.forEach(p => {
            h += '<tr>';
            h += '<td>'+p.month+'</td>';
            h += '<td>'+(p.fullname||'N/A')+'</td>';
            h += '<td>'+fmt(p.gross_salary)+'</td>';
            h += '<td>'+fmt(p.total_deductions)+'</td>';
            h += '<td>'+fmt(p.net_salary)+'</td>';
            h += '<td><span class="badge badge-'+p.status+'">'+p.status+'</span></td>';
            h += '</tr>';
        });
        t.innerHTML = h;
    });
}

function editSalary(id) {
    fetch('API/hr_api.php?action=get_salary&id='+id)
    .then(r => r.json())
    .then(d => {
        if (d.error) { alert(d.error); return; }
        document.getElementById('salaryId').value = d.id;
        document.getElementById('teacherSelect').value = d.employee_id;
        document.getElementById('teacherSelect').disabled = true;
        document.getElementById('basicSalary').value = d.basic_salary;
        document.getElementById('housingAllowance').value = d.housing_allowance||0;
        document.getElementById('transportAllowance').value = d.transport_allowance||0;
        document.getElementById('medicalAllowance').value = d.medical_allowance||0;
        document.getElementById('otherAllowances').value = d.other_allowances||0;
        document.getElementById('effectiveDate').value = d.effective_date;
    });
}

function deleteSalary(id) {
    if (!confirm('Delete this salary record?')) return;
    fetch('API/hr_api.php?action=delete_salary&id='+id)
    .then(r => r.json())
    .then(d => {
        if (d.status === 'deleted') {
            Toast.show('Salary deleted', 'success');
            loadSalaries();
            loadStats();
        } else {
            Toast.show(d.message||'Error', 'error');
        }
    });
}

function resetForm() {
    document.getElementById('salaryForm').reset();
    document.getElementById('salaryId').value = '';
    document.getElementById('teacherSelect').disabled = false;
}

document.getElementById('salaryForm').onsubmit = function(e) {
    e.preventDefault();
    var teacher = document.getElementById('teacherSelect').value;
    var basic = parseFloat(document.getElementById('basicSalary').value);
    if (!teacher || !basic) {
        Toast.show('Select teacher and enter salary', 'error');
        return;
    }
    
    var data = {
        id: document.getElementById('salaryId').value || null,
        employee_id: teacher,
        basic_salary: basic,
        housing_allowance: parseFloat(document.getElementById('housingAllowance').value)||0,
        transport_allowance: parseFloat(document.getElementById('transportAllowance').value)||0,
        medical_allowance: parseFloat(document.getElementById('medicalAllowance').value)||0,
        other_allowances: parseFloat(document.getElementById('otherAllowances').value)||0,
        effective_date: document.getElementById('effectiveDate').value
    };
    
    fetch('API/hr_api.php?action=save_salary', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => {
        if (!r.ok) { return r.text().then(t => { throw new Error('Server error: ' + t); }); }
        return r.text();
    })
    .then(t => { console.log('Response:', t); return JSON.parse(t); })
    .then(d => {
        if (d.status === 'success') {
            Toast.show('Salary saved!', 'success');
            resetForm();
            loadSalaries();
            loadStats();
        } else {
            Toast.show(d.message||'Error saving', 'error');
        }
    });
};

document.getElementById('processForm').onsubmit = function(e) {
    e.preventDefault();
    if (!confirm('Generate payslips for this month?')) return;
    
    var data = { month: document.getElementById('processMonth').value };
    fetch('API/hr_api.php?action=process_salaries', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(data)
    })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success') {
            Toast.show('Processed '+d.processed+' payslips', 'success');
            loadStats();
            loadSalaryPayments();
        } else {
            Toast.show(d.message||'Error', 'error');
        }
    });
};

loadStats();
loadTeachers();
loadSalaries();
loadSalaryPayments();
</script>
</body>
</html>