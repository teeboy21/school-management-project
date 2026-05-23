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
<title>Fee Structures - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
    .form-grid .full { grid-column: 1 / -1; }
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
    .content-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); }
    .content-card h3 { margin: 0 0 10px; font-size: 16px; }
    .content-card p { margin: 5px 0; color: var(--app-muted); font-size: 14px; }
    .content-card strong { color: var(--app-text); }
    .page-actions { margin-top: 15px; display: flex; gap: 10px; }
    .empty-state { text-align: center; padding: 40px; color: var(--app-muted); }
    .alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 15px; }
    .alert-danger { background: #f8d7da; color: #721c24; }
    .alert-success { background: #d4edda; color: #155724; }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Fee Structures</div>
                <div class="app-header-subtitle">Set fee amounts per grade and academic year</div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom: 30px;">
                <h2>Create / Edit Fee Structure</h2>
                <form id="feeForm" class="form-grid">
                    <input type="hidden" id="structureId">
                    <div>
                        <label>Grade</label>
                        <select id="gradeName" required>
                            <option value="">Select Grade</option>
                            <option value="Grade 8">Grade 8</option>
                            <option value="Grade 9">Grade 9</option>
                            <option value="Grade 10">Grade 10</option>
                            <option value="Grade 11">Grade 11</option>
                            <option value="Grade 12">Grade 12</option>
                        </select>
                    </div>
                    <div>
                        <label>Academic Year</label>
                        <select id="academicYear" required>
                            <?php foreach ($academic_years as $y): ?>
                            <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div><label>Tuition Fee</label><input type="number" id="tuitionFee" step="0.01" value="0"></div>
                    <div><label>Registration Fee</label><input type="number" id="registrationFee" step="0.01" value="0"></div>
                    <div><label>Exam Fee</label><input type="number" id="examFee" step="0.01" value="0"></div>
                    <div><label>Library Fee</label><input type="number" id="libraryFee" step="0.01" value="0"></div>
                    <div><label>Sports Fee</label><input type="number" id="sportsFee" step="0.01" value="0"></div>
                    <div><label>Transport Fee</label><input type="number" id="transportFee" step="0.01" value="0"></div>
                    <div><label>Other Fee</label><input type="number" id="otherFee" step="0.01" value="0"></div>
                    <div><label>Due Date</label><input type="date" id="dueDate"></div>
                    <div class="full"><label>Description</label><textarea id="structureDesc"></textarea></div>
                    <div class="full page-actions">
                        <button type="submit" class="btn btn-primary">Save Structure</button>
                        <button type="button" class="btn" onclick="clearForm()">Clear</button>
                    </div>
                </form>
            </div>
            <div class="panel">
                <h2>Existing Fee Structures</h2>
                <div style="margin-bottom: 15px;">
                    <select id="filterYear" onchange="loadStructures()" style="padding: 8px 12px; border-radius: 6px;">
                        <?php foreach ($academic_years as $y): ?>
                        <option value="<?= $y ?>" <?= $y === $current_year ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="structuresList" class="cards-grid"></div>
            </div>
        </section>
    </main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }

async function loadStructures() {
    try {
        const year = document.getElementById('filterYear').value;
        const res = await fetch('API/finance_api.php?action=get_fee_structures&year=' + year);
        const data = await res.json();
        console.log('Fee structures response:', data);
        if (data.error) {
            document.getElementById('structuresList').innerHTML = '<div class="empty-state">Error: ' + data.error + '</div>';
            return;
        }
        if (!data.length) {
            document.getElementById('structuresList').innerHTML = '<div class="empty-state">No fee structures found</div>';
            return;
        }
        document.getElementById('structuresList').innerHTML = data.map(s => {
            const total = parseFloat(s.tuition_fee||0) + parseFloat(s.registration_fee||0) + parseFloat(s.exam_fee||0) + parseFloat(s.library_fee||0) + parseFloat(s.sports_fee||0) + parseFloat(s.transport_fee||0) + parseFloat(s.other_fee||0);
            return `<div class="content-card">
                <h3>${s.grade_name} - ${s.academic_year}</h3>
                <p>Tuition: ${formatCurrency(s.tuition_fee)} | Registration: ${formatCurrency(s.registration_fee)}</p>
                <p>Exam: ${formatCurrency(s.exam_fee)} | Due: ${s.due_date||'N/A'}</p>
                <p>Total: <strong>${formatCurrency(total)}</strong></p>
                <div class="page-actions">
                    <button class="btn btn-small" onclick="editStructure(${s.id})">Edit</button>
                    <button class="btn btn-small btn-danger" onclick="deleteStructure(${s.id})">Delete</button>
                </div>
            </div>`;
        }).join('');
    } catch (e) {
        console.error(e);
    }
}

document.getElementById('feeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const payload = {
        id: document.getElementById('structureId').value||null,
        grade_name: document.getElementById('gradeName').value,
        academic_year: document.getElementById('academicYear').value,
        tuition_fee: parseFloat(document.getElementById('tuitionFee').value)||0,
        registration_fee: parseFloat(document.getElementById('registrationFee').value)||0,
        exam_fee: parseFloat(document.getElementById('examFee').value)||0,
        library_fee: parseFloat(document.getElementById('libraryFee').value)||0,
        sports_fee: parseFloat(document.getElementById('sportsFee').value)||0,
        transport_fee: parseFloat(document.getElementById('transportFee').value)||0,
        other_fee: parseFloat(document.getElementById('otherFee').value)||0,
        due_date: document.getElementById('dueDate').value,
        description: document.getElementById('structureDesc').value
    };
    
    try {
        const res = await fetch('API/finance_api.php?action=save_fee_structure', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.status === 'success') {
            alert('Fee structure saved successfully!');
            clearForm(); loadStructures();
        } else {
            alert(data.message || 'Failed to save fee structure');
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
});

function clearForm() {
    document.getElementById('feeForm').reset();
    document.getElementById('structureId').value = '';
}

function editStructure(id) {
    fetch('API/finance_api.php?action=get_fee_structures&year=' + document.getElementById('filterYear').value)
        .then(r=>r.json()).then(data=>{
            const s = data.find(x=>x.id===id);
            if(s){
                document.getElementById('structureId').value = s.id;
                document.getElementById('gradeName').value = s.grade_name;
                document.getElementById('academicYear').value = s.academic_year;
                document.getElementById('tuitionFee').value = s.tuition_fee;
                document.getElementById('registrationFee').value = s.registration_fee;
                document.getElementById('examFee').value = s.exam_fee;
                document.getElementById('libraryFee').value = s.library_fee;
                document.getElementById('sportsFee').value = s.sports_fee;
                document.getElementById('transportFee').value = s.transport_fee;
                document.getElementById('otherFee').value = s.other_fee;
                document.getElementById('dueDate').value = s.due_date;
                document.getElementById('structureDesc').value = s.description||'';
            }
        });
}

async function deleteStructure(id) {
    if(!confirm('Delete this fee structure?')) return;
    await fetch('API/finance_api.php?action=delete_fee_structure&id='+id);
    loadStructures();
}

loadStructures();
</script>
</body>
</html>