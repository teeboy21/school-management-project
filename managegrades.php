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
<title>Manage Grades</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Manage Grades</div>
                <div class="app-header-subtitle">Create and update grades without full page reloads.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom:20px;">
                <h2>Add Grade</h2>
                <form id="gradeForm" class="form-grid">
                    <div>
                        <label>Grade Name</label>
                        <input type="text" id="gradeName" required>
                    </div>
                    <div class="page-actions">
                        <button type="submit" class="btn btn-primary">Add Grade</button>
                    </div>
                </form>
            </div>
            <div class="panel">
                <h2>Existing Grades</h2>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="gradesTable"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>
<script src="assets/toast.js"></script>
<script>
const gradesTable = document.getElementById("gradesTable");
const gradeForm = document.getElementById("gradeForm");

function loadGrades() {
    fetch("API/grades.php")
    .then(res => res.json())
    .then(response => {
        const grades = response.data || [];
        gradesTable.innerHTML = grades.length ? grades.map(grade => `
            <tr>
                <td>${grade.id}</td>
                <td><input type="text" id="grade-name-${grade.id}" value="${grade.name}"></td>
                <td>
                    <button class="btn btn-secondary" onclick="updateGrade(${grade.id})">Update</button>
                    <button class="btn btn-danger" onclick="deleteGrade(${grade.id})">Delete</button>
                </td>
            </tr>
        `).join("") : `<tr><td colspan="3" class="empty-state">No grades found.</td></tr>`;
    })
    .catch(() => showToast("Failed to load grades.", "error"));
}

gradeForm.addEventListener("submit", function (event) {
    event.preventDefault();
    fetch("API/grades.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ name: document.getElementById("gradeName").value.trim() })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        document.getElementById("gradeName").value = "";
        showToast(response.message, "success");
        loadGrades();
    })
    .catch(error => showToast(error.message, "error"));
});

function updateGrade(id) {
    fetch("API/grades.php", {
        method: "PUT",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id, name: document.getElementById(`grade-name-${id}`).value.trim() })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        showToast(response.message, "success");
        loadGrades();
    })
    .catch(error => showToast(error.message, "error"));
}

function deleteGrade(id) {
    if (!confirm("Delete this grade?")) return;
    fetch("API/grades.php", {
        method: "DELETE",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ id })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        showToast(response.message, "success");
        loadGrades();
    })
    .catch(error => showToast(error.message, "error"));
}

loadGrades();
</script>
</body>
</html>
