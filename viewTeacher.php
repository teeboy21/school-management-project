<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager', 'principal'])) {
    header("Location: login.html");
    exit();
}
$dash_map = ['admin'=>'admindashboard.php','hr_manager'=>'hr_dashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Directory</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Teacher Directory</div>
                <div class="app-header-subtitle">A cleaner read-only directory with direct access to teacher management.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Staff Overview</h2>
                <p>Use this page for fast browsing, then open teacher management when you need edit and delete actions.</p>
                <div class="page-actions">
                    <a class="btn btn-primary" href="registerTeacher.php">Manage Teachers</a>
                    <a class="btn btn-secondary" href="addteacher.php">Register Teacher</a>
                </div>
            </div>

            <div class="panel">
                <div class="toolbar">
                    <h2>Teachers</h2>
                    <input class="search-input" type="search" id="teacherSearch" placeholder="Search by name, email, employee number">
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Employee Number</th>
                                <th>Phone</th>
                                <th>Hire Date</th>
                            </tr>
                        </thead>
                        <tbody id="teacherTable"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<script>
const teacherTable = document.getElementById("teacherTable");
const teacherSearch = document.getElementById("teacherSearch");
let teachers = [];

function renderTeachers(rows) {
    if (!rows.length) {
        teacherTable.innerHTML = `<tr><td colspan="6" class="empty-state">No teachers found.</td></tr>`;
        return;
    }

    teacherTable.innerHTML = rows.map(row => `
        <tr>
            <td>${row.user_id}</td>
            <td>${row.fullname ?? ""}</td>
            <td>${row.email ?? ""}</td>
            <td>${row.employee_number ?? ""}</td>
            <td>${row.phone ?? ""}</td>
            <td>${row.hire_date ?? ""}</td>
        </tr>
    `).join("");
}

fetch("API/get_teachers.php")
.then(res => {
    if (!res.ok) throw new Error("Failed to load teachers");
    return res.json();
})
.then(response => {
    teachers = response.data || [];
    renderTeachers(teachers);
})
.catch(error => showToast(error.message, "error"));

teacherSearch.addEventListener("input", function () {
    const query = this.value.toLowerCase().trim();
    if (!query) {
        renderTeachers(teachers);
        return;
    }

    renderTeachers(teachers.filter(row =>
        [row.fullname, row.email, row.employee_number, row.phone].join(" ").toLowerCase().includes(query)
    ));
});
</script>
</body>
</html>
