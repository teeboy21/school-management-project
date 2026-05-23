<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Subjects</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Manage Subjects</div>
                <div class="app-header-subtitle">Create and update subjects with API-backed saves.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom:20px;">
                <h2>Add Subject</h2>
                <form id="subjectForm" class="form-grid">
                    <div>
                        <label>Subject Name</label>
                        <input type="text" id="subjectName" required>
                    </div>
                    <div>
                        <label>Grade</label>
                        <select id="subjectGrade" required></select>
                    </div>
                    <div>
                        <label><input type="checkbox" id="subjectCompulsory"> Compulsory subject</label>
                    </div>
                    <div class="page-actions">
                        <button type="submit" class="btn btn-primary">Add Subject</button>
                    </div>
                </form>
            </div>
            <div class="panel">
                <h2>Existing Subjects</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Grade</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subjectsTable"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="assets/toast.js"></script>
<script>
let grades = [];
const subjectGrade = document.getElementById("subjectGrade");
const subjectsTable = document.getElementById("subjectsTable");

function renderGradeOptions(selected = "") {
    return `<option value="">Select Grade</option>` + grades.map(grade =>
        `<option value="${grade.id}" ${String(grade.id) === String(selected) ? "selected" : ""}>${grade.name}</option>`
    ).join("");
}

function loadSubjects() {
    fetch("API/subjects.php")
    .then(res => res.json())
    .then(response => {
        grades = response.grades || [];
        subjectGrade.innerHTML = renderGradeOptions();
        const rows = response.data || [];
        subjectsTable.innerHTML = rows.length ? rows.map(row => `
            <tr>
                <td><input type="text" id="subject-name-${row.id}" value="${row.subject_name}"></td>
                <td><select id="subject-grade-${row.id}">${renderGradeOptions(row.grade_id)}</select></td>
                <td><label><input type="checkbox" id="subject-compulsory-${row.id}" ${Number(row.is_compulsory) === 1 ? "checked" : ""}> Compulsory</label></td>
                <td>
                    <button class="btn btn-secondary" onclick="updateSubject(${row.id})">Update</button>
                    <button class="btn btn-danger" onclick="deleteSubject(${row.id})">Delete</button>
                </td>
            </tr>
        `).join("") : `<tr><td colspan="4" class="empty-state">No subjects found.</td></tr>`;
    })
    .catch(() => showToast("Failed to load subjects.", "error"));
}

document.getElementById("subjectForm").addEventListener("submit", function (event) {
    event.preventDefault();
    fetch("API/subjects.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            subject_name: document.getElementById("subjectName").value.trim(),
            grade_id: subjectGrade.value,
            is_compulsory: document.getElementById("subjectCompulsory").checked ? 1 : 0
        })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        document.getElementById("subjectName").value = "";
        subjectGrade.value = "";
        document.getElementById("subjectCompulsory").checked = false;
        showToast(response.message, "success");
        loadSubjects();
    })
    .catch(error => showToast(error.message, "error"));
});

function updateSubject(id) {
    fetch("API/subjects.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            id,
            subject_name: document.getElementById(`subject-name-${id}`).value.trim(),
            grade_id: document.getElementById(`subject-grade-${id}`).value,
            is_compulsory: document.getElementById(`subject-compulsory-${id}`).checked ? 1 : 0
        })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        showToast(response.message, "success");
        loadSubjects();
    })
    .catch(error => showToast(error.message, "error"));
}

function deleteSubject(id) {
    if (!confirm("Delete this subject?")) return;
    fetch("API/subjects.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        showToast(response.message, "success");
        loadSubjects();
    })
    .catch(error => showToast(error.message, "error"));
}

loadSubjects();
</script>
</body>
</html>
