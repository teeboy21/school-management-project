<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
    header("Location: login.html");
    exit();
}
$role = $_SESSION['role'];
$dash_map = ['admin'=>'admindashboard.php','principal'=>'principal_dashboard.php'];
$dashboard_url = $dash_map[$role] ?? 'admindashboard.php';
$title = $role === 'principal' ? 'Principal Approval Queue' : 'Admin Approval Queue';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Accounts</title>
<link rel="stylesheet" href="assets/toast.css">
<link rel="stylesheet" href="internal.css">
<style>
body{margin:0;font-family:Arial,sans-serif;background:linear-gradient(180deg,#e2e8f0 0%,#f8fafc 100%);color:#0f172a}
.header{height:64px;background:#0f172a;color:white;display:flex;justify-content:space-between;align-items:center;padding:0 20px}
.back-link{color:white;text-decoration:none;font-weight:700}
.container{max-width:1200px;margin:0 auto;padding:24px}
.hero{background:white;border-radius:20px;padding:24px;box-shadow:0 15px 35px rgba(15,23,42,0.08);margin-bottom:20px}
.hero h1{margin:0 0 8px;font-size:28px}
.hero p{margin:0;color:#475569}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:20px}
.stat-card{background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:16px}
.stat-card strong{display:block;font-size:26px;margin-top:8px}
.panel{background:white;border-radius:20px;padding:18px;box-shadow:0 15px 35px rgba(15,23,42,0.08)}
.toolbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap}
.toolbar input{min-width:280px;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px;font-size:13px}
.tabs{display:flex;gap:8px;margin-bottom:18px}
.tab{padding:10px 20px;border-radius:10px;border:1px solid #cbd5e1;background:white;cursor:pointer;font-weight:600;font-size:13px}
.tab.active{background:#0f172a;color:white;border-color:#0f172a}
.table-wrap{overflow:auto}
table{width:100%;border-collapse:collapse}
th,td{padding:14px 12px;border-bottom:1px solid #e2e8f0;vertical-align:middle;text-align:left}
th{background:#f8fafc;color:#334155;font-size:13px}
.student-card{display:flex;flex-direction:column;gap:4px}
.muted{color:#64748b;font-size:14px}
.stack{display:flex;flex-direction:column;gap:8px}
select,button{padding:10px 12px;border-radius:10px;border:1px solid #cbd5e1;font-size:13px}
button{border:none;color:white;cursor:pointer;font-weight:700}
.approve-btn{background:#16a34a}
.reject-btn{background:#dc2626}
.secondary-btn{background:#0ea5e9;display:inline-block;text-align:center;text-decoration:none;padding:10px 12px;border-radius:10px;color:white;font-weight:700;font-size:13px}
.empty-state{padding:32px 16px;text-align:center;color:#64748b}
</style>
</head>
<body>
<div class="header">
    <a class="back-link" href="<?= $dashboard_url ?>">← Back To Dashboard</a>
    <div><?= $title ?></div>
</div>

<div class="container">
    <section class="hero">
        <h1>Account Approvals</h1>
        <p>Review and approve or reject pending student registrations and employee accounts.</p>
        <div class="stats">
            <div class="stat-card"><span>Pending Students</span><strong id="pendingStudentCount">0</strong></div>
            <div class="stat-card"><span>Pending Employees</span><strong id="pendingEmployeeCount">0</strong></div>
        </div>
    </section>

    <div class="tabs">
        <button class="tab active" data-tab="students" onclick="switchTab('students')">Pending Students</button>
        <button class="tab" data-tab="employees" onclick="switchTab('employees')">Pending Employees</button>
    </div>

    <section class="panel" id="studentsTab">
        <div class="toolbar">
            <h2>Students</h2>
            <input type="search" id="studentSearch" placeholder="Search by name, email, grade, or parent">
        </div>
        <div class="table-wrap">
            <table><thead><tr><th>Student</th><th>Academic</th><th>Parent</th><th>Decision</th></tr></thead>
            <tbody id="studentsBody"></tbody></table>
        </div>
    </section>

    <section class="panel" id="employeesTab" style="display:none">
        <div class="toolbar">
            <h2>Employees</h2>
            <input type="search" id="employeeSearch" placeholder="Search by name, email, or department">
        </div>
        <div class="table-wrap">
            <table><thead><tr><th>Name</th><th>Email</th><th>Department</th><th>Job Title</th><th>Role</th><th>Decision</th></tr></thead>
            <tbody id="employeesBody"></tbody></table>
        </div>
    </section>
</div>

<script src="assets/toast.js"></script>
<script>
let pendingStudents = [], pendingEmployees = [];
let activeTab = 'students';

function switchTab(tab) {
    activeTab = tab;
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
    document.getElementById('studentsTab').style.display = tab === 'students' ? 'block' : 'none';
    document.getElementById('employeesTab').style.display = tab === 'employees' ? 'block' : 'none';
}

function renderStudents(items) {
    document.getElementById('pendingStudentCount').textContent = items.length;
    const tb = document.getElementById('studentsBody');
    if (!items.length) {
        tb.innerHTML = '<tr><td colspan="4" class="empty-state">No pending students found.</td></tr>';
        return;
    }
    tb.innerHTML = items.map(s => `<tr>
        <td><div class="student-card"><strong>${s.fullname||''}</strong><span class="muted">${s.email||''}</span></div></td>
        <td><div class="student-card"><span>Grade ${s.grade||''}</span></div></td>
        <td><div class="student-card"><strong>${s.parent_name||'No parent name'}</strong><span class="muted">${s.parent_phone||'No phone'}</span><span class="muted">${s.relationship||''}</span></div></td>
        <td><div class="stack">
            <button class="approve-btn" onclick="approveStudent(${s.user_id})">Approve</button>
            <button class="reject-btn" onclick="rejectStudent(${s.user_id})">Reject</button>
            <a class="secondary-btn" href="view_student.php?id=${s.user_id}">View</a>
        </div></td>
    </tr>`).join('');
}

function renderEmployees(items) {
    document.getElementById('pendingEmployeeCount').textContent = items.length;
    const tb = document.getElementById('employeesBody');
    if (!items.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty-state">No pending employees found.</td></tr>';
        return;
    }
    tb.innerHTML = items.map(e => `<tr>
        <td><strong>${e.fullname||''}</strong></td>
        <td>${e.email||''}</td>
        <td>${e.department||'—'}</td>
        <td>${e.job_title||'—'}</td>
        <td>${e.role_name||'—'}</td>
        <td><div class="stack">
            <button class="approve-btn" onclick="approveEmployee(${e.user_id})">Approve</button>
            <button class="reject-btn" onclick="rejectEmployee(${e.user_id})">Reject</button>
        </div></td>
    </tr>`).join('');
}

function filterStudents() {
    const q = document.getElementById('studentSearch').value.toLowerCase().trim();
    if (!q) return renderStudents(pendingStudents);
    renderStudents(pendingStudents.filter(s => [s.fullname, s.email, s.grade, s.stream, s.parent_name, s.parent_phone].join(' ').toLowerCase().includes(q)));
}

function filterEmployees() {
    const q = document.getElementById('employeeSearch').value.toLowerCase().trim();
    if (!q) return renderEmployees(pendingEmployees);
    renderEmployees(pendingEmployees.filter(e => [e.fullname, e.email, e.department, e.job_title, e.role_name].join(' ').toLowerCase().includes(q)));
}

function loadPendingStudents() {
    fetch('API/get_pending_students.php')
    .then(r => r.json())
    .then(d => { pendingStudents = d.students || []; renderStudents(pendingStudents); })
    .catch(() => { document.getElementById('studentsBody').innerHTML = '<tr><td colspan="5" class="empty-state">Failed to load.</td></tr>'; });
}

function loadPendingEmployees() {
    fetch('API/get_pending_employees.php')
    .then(r => r.json())
    .then(d => { pendingEmployees = d.employees || []; renderEmployees(pendingEmployees); })
    .catch(() => { document.getElementById('employeesBody').innerHTML = '<tr><td colspan="6" class="empty-state">Failed to load.</td></tr>'; });
}

function approveStudent(id) {
    fetch('API/approve_student.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:id})})
    .then(r=>r.json()).then(d=>{if(d.status==='success'){showToast(d.message,'success');loadPendingStudents();}else throw new Error(d.message||'Failed');})
    .catch(e=>showToast(e.message,'error'));
}

function rejectStudent(id) {
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;
    fetch('API/reject_student.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:id,rejection_reason:reason})})
    .then(r=>r.json()).then(d=>{if(d.status==='success'){showToast(d.message,'success');loadPendingStudents();}else throw new Error(d.message||'Failed');})
    .catch(e=>showToast(e.message,'error'));
}

function approveEmployee(id) {
    fetch('API/approve_employee.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:id})})
    .then(r=>r.json()).then(d=>{if(d.status==='success'){showToast(d.message,'success');loadPendingEmployees();}else throw new Error(d.message||'Failed');})
    .catch(e=>showToast(e.message,'error'));
}

function rejectEmployee(id) {
    const reason = prompt('Enter rejection reason:');
    if (!reason) return;
    fetch('API/reject_employee.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:id,rejection_reason:reason})})
    .then(r=>r.json()).then(d=>{if(d.status==='success'){showToast(d.message,'success');loadPendingEmployees();}else throw new Error(d.message||'Failed');})
    .catch(e=>showToast(e.message,'error'));
}

document.getElementById('studentSearch').addEventListener('input', filterStudents);
document.getElementById('employeeSearch').addEventListener('input', filterEmployees);

loadPendingStudents();
loadPendingEmployees();
</script>
</body>
</html>
