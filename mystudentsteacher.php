<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'teacher') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Students</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="teacherdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">My Students</div>
                <div class="app-header-subtitle">Browse students attached to the subjects you teach.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Teacher') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel">
                <input class="search-input" type="search" id="studentSearch" placeholder="Search students">
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Grade</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="teacherStudentsTable"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<script>
const teacherStudentsTable = document.getElementById("teacherStudentsTable");
const studentSearch = document.getElementById("studentSearch");
let students = [];

function renderStudents(rows) {
    if (!rows.length) {
        teacherStudentsTable.innerHTML = `<tr><td colspan="5">No students found.</td></tr>`;
        return;
    }
    teacherStudentsTable.innerHTML = rows.map(row => `
        <tr>
            <td>${row.student_number ?? ""}</td>
            <td>${row.fullname ?? ""}</td>
            <td>${row.numbers ?? ""}</td>
            <td>${row.gradename ?? "Unassigned"}</td>
            <td>
                ${row.numbers ? `<a href="tel:${row.numbers.replace(/[^0-9+]/g,'')}" class="btn btn-small btn-primary" style="text-decoration:none;">Call</a> ` : ''}
                ${row.email ? `<a href="mailto:${row.email}" class="btn btn-small btn-secondary" style="text-decoration:none;">Email</a>` : ''}
            </td>
        </tr>
    `).join("");
}

fetch("API/teacher_students.php")
.then(res => {
    if (!res.ok) throw new Error("Failed to load students");
    return res.json();
})
.then(response => {
    students = response.data || [];
    renderStudents(students);
})
.catch(error => showToast(error.message, "error"));

studentSearch.addEventListener("input", function () {
    const query = this.value.toLowerCase().trim();
    if (!query) {
        renderStudents(students);
        return;
    }
    renderStudents(students.filter(row =>
        [row.student_number, row.fullname, row.numbers, row.gradename].join(" ").toLowerCase().includes(query)
    ));
});
</script>
</body>
</html>
