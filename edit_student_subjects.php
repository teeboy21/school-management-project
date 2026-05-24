<?php
session_start();
require __DIR__ . '/config.php';

$role = $_SESSION['role'] ?? '';
$allowed = ['admin'];
$setting = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'principal_can_edit_subjects'")->fetch_assoc();
if ($role === 'principal' && ($setting['setting_value'] ?? 'off') === 'on') {
    $allowed[] = 'principal';
}

if (!in_array($role, $allowed)) {
    header("Location: login.html");
    exit();
}

$display_name = $_SESSION['fullname'] ?? ucfirst($role);
$display_email = $_SESSION['email'] ?? '';
$dash_map = ['admin'=>'admindashboard.php','principal'=>'principal_dashboard.php'];
$dashboard_url = $dash_map[$role] ?? 'login.html';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Student Subjects</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:32px;max-width:640px;width:90%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal-box h2{margin:0 0 4px;font-size:22px}
.modal-box .modal-sub{color:#5f728c;margin:0 0 20px;font-size:14px}
.modal-actions{display:flex;gap:12px;margin-top:20px;justify-content:flex-end}
.subject-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eef4fb}
.subject-row:last-child{border-bottom:none}
.subject-row .subject-name{font-weight:600;color:#10233f;font-size:15px}
.student-result{cursor:pointer;padding:12px;border-radius:10px;border:1px solid var(--app-border);transition:background 0.15s;margin-bottom:8px}
.student-result:hover{background:#f0f6ff;border-color:#1677ff}
.student-result .name{font-weight:600;color:#10233f}
.student-result .meta{font-size:13px;color:#5f728c}
.search-wrap{position:relative}
.search-wrap .clear-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;font-size:18px;cursor:pointer;color:#5f728c;padding:4px 8px}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Edit Student Subjects</div>
<div class="app-header-subtitle">Search for a student and manage their subject selections.</div>
</div>
<div class="app-user">
<div class="app-user-name"><?= htmlspecialchars($display_name) ?></div>
<div class="app-user-email"><?= htmlspecialchars($display_email) ?></div>
</div>
</header>
<section class="app-content">
<div class="hero-card">
<h2>Student Subject Manager</h2>
<p>Search for a student by name or student number, then add or remove their subjects. Changes take effect immediately.</p>
</div>
<div class="panel">
<div class="search-wrap">
<input class="search-input" type="search" id="studentSearch" placeholder="Search by name or student number..." style="width:100%;padding:12px 16px;font-size:15px">
</div>
<div id="searchResults" style="margin-top:16px"></div>
</div>
<div id="subjectPanel" class="panel" style="display:none;margin-top:20px">
<h2 id="selectedStudentName">Subjects</h2>
<p class="app-header-subtitle" id="selectedStudentMeta"></p>
<div id="subjectsList"></div>
</div>
</section>
</main>
</div>

<script src="assets/toast.js"></script>
<script>
let selectedStudentId = null;
let searchTimer = null;

const searchInput = document.getElementById("studentSearch");
const searchResults = document.getElementById("searchResults");
const subjectPanel = document.getElementById("subjectPanel");

searchInput.addEventListener("input", function() {
    clearTimeout(searchTimer);
    const q = this.value.trim();
    if (q.length < 2) { searchResults.innerHTML = ""; return; }
    searchTimer = setTimeout(() => searchStudents(q), 300);
});

async function searchStudents(q) {
    const res = await fetch(`API/student_subject_admin.php?action=search_students&q=${encodeURIComponent(q)}`);
    const students = await res.json();
    if (!Array.isArray(students) || students.length === 0) {
        searchResults.innerHTML = '<div class="text-muted" style="padding:12px">No students found.</div>';
        return;
    }
    searchResults.innerHTML = students.map(s => `
        <div class="student-result" onclick="selectStudent(${s.id}, '${s.fullname.replace(/'/g, "\\'")}', '${s.student_number || ''}')">
            <div class="name">${s.fullname}</div>
            <div class="meta">${s.student_number ? s.student_number + ' · ' : ''}${s.grade || ''}</div>
        </div>
    `).join("");
}

async function selectStudent(id, name, number) {
    selectedStudentId = id;
    searchInput.value = name;
    searchResults.innerHTML = "";
    subjectPanel.style.display = "block";
    document.getElementById("selectedStudentName").textContent = name + "'s Subjects";
    document.getElementById("selectedStudentMeta").textContent = number ? `Student #${number}` : '';
    document.getElementById("subjectsList").innerHTML = "Loading...";

    const res = await fetch(`API/student_subject_admin.php?action=get_subjects&student_id=${id}`);
    const data = await res.json();
    if (!data.subjects) { document.getElementById("subjectsList").innerHTML = '<div class="text-muted">Error loading subjects.</div>'; return; }

    document.getElementById("subjectsList").innerHTML = data.subjects.map(s => `
        <div class="subject-row">
            <span class="subject-name">${s.subject_name}</span>
            ${s.status === 'approved'
                ? `<button class="btn btn-danger btn-small" onclick="toggleSubject(${id}, ${s.id}, false)">Remove</button>`
                : `<button class="btn btn-primary btn-small" onclick="toggleSubject(${id}, ${s.id}, true)">Add</button>`
            }
        </div>
    `).join("");
}

async function toggleSubject(studentId, subjectId, add) {
    const res = await fetch("API/student_subject_admin.php?action=toggle", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({student_id: studentId, subject_id: subjectId, add})
    });
    const data = await res.json();
    showToast(data.message, data.status === "success" ? "success" : "error");
    if (data.status === "success") {
        await selectStudent(studentId, searchInput.value, '');
    }
}
</script>
</body>
</html>
