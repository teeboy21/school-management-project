<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'principal' && ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Classes</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Manage Classes</div>
                <div class="app-header-subtitle">Create and update classes.</div>

        <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom:20px;">
                <h2>Add Class</h2>
                <form id="classForm" class="form-grid">
                    <div>
                        <label>Grade</label>
                        <select id="classGrade" required></select>
                    </div>
                    <div>
                        <label>Class Name</label>
                        <input type="text" id="className" required>
                    </div>
                    <div class="page-actions">
                        <button type="submit" class="btn btn-primary">Add Class</button>
                    </div>
                </form>
            </div>
            <div class="panel">
                <h2>Existing Classes</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>Class Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="classesTable"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="assets/toast.js"></script>
<script>
let grades = [];
const classGrade = document.getElementById("classGrade");
const classesTable = document.getElementById("classesTable");

function renderGradeOptions(selected = "") {
    return `<option value="">Select Grade</option>` + grades.map(grade =>
        `<option value="${grade.id}" ${String(grade.id) === String(selected) ? "selected" : ""}>${grade.name}</option>`
    ).join("");
}

function loadClasses() {
    fetch("API/classes.php")
    .then(res => res.json())
    .then(response => {
        grades = response.grades || [];
        classGrade.innerHTML = renderGradeOptions();
        const rows = response.data || [];
        classesTable.innerHTML = rows.length ? rows.map(row => `
            <tr>
                <td><select id="class-grade-${row.id}">${renderGradeOptions(row.grade_id)}</select></td>
                <td><input type="text" id="class-name-${row.id}" value="${row.class_name}"></td>
                <td>
                    <button class="btn btn-secondary" onclick="updateClass(${row.id})">Update</button>
                    <button class="btn btn-danger" onclick="deleteClass(${row.id})">Delete</button>
                </td>
            </tr>
        `).join("") : `<tr><td colspan="3" class="empty-state">No classes found.</td></tr>`;
    })
    .catch(() => showToast("Failed to load classes.", "error"));
}

document.getElementById("classForm").addEventListener("submit", function (event) {
    event.preventDefault();
    fetch("API/classes.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            grade_id: classGrade.value,
            class_name: document.getElementById("className").value.trim()
        })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        document.getElementById("className").value = "";
        classGrade.value = "";
        showToast(response.message, "success");
        loadClasses();
    })
    .catch(error => showToast(error.message, "error"));
});

function updateClass(id) {
    fetch("API/classes.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id,
            grade_id: document.getElementById(`class-grade-${id}`).value,
            class_name: document.getElementById(`class-name-${id}`).value.trim()
        })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        showToast(response.message, "success");
        loadClasses();
    })
    .catch(error => showToast(error.message, "error"));
}

function deleteClass(id) {
    if (!confirm("Delete this class?")) return;
    fetch("API/classes.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        showToast(response.message, "success");
        loadClasses();
    })
    .catch(error => showToast(error.message, "error"));
}

loadClasses();
</script>
</body>
</html>
