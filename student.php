<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'principal'])) {
    header("Location: login.html");
    exit();
}

$querystudents = "SELECT COUNT(*) AS totalstudent FROM students";
$resultstudents = mysqli_query($conn, $querystudents);
$rowstudents = mysqli_fetch_assoc($resultstudents);
$total_students = $rowstudents['totalstudent'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Directory</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Student Directory</div>
                <div class="app-header-subtitle">Manage student records with quick edit and delete actions.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2>Students</h2>
                <p>Total registered students: <strong><?= (int) $total_students ?></strong></p>
            </div>

            <div class="panel">
                <div class="toolbar">
                    <h2>Records</h2>
                    <div class="page-actions">
                        <a class="btn btn-secondary" href="enroll.php">Register Student</a>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student Number</th>
                                <th>Name</th>
                                <th>Parent Phone</th>
                                <th>Grade</th>
                                <th>Class</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usertable"></tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</div>

<div id="editModal" class="modal" style="position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;align-items:center;justify-content:center;padding:20px;">
    <div class="panel" style="width:min(480px,100%);">
        <h3>Edit Student</h3>
        <form id="editStudentForm" class="form-grid">
            <input type="hidden" id="editStudentId">
            <div class="full">
                <label>Student Number</label>
                <input type="text" id="editStudentNumber" required>
            </div>
            <div class="full">
                <label>Full Name</label>
                <input type="text" id="editFullname" required>
            </div>
            <div class="full">
                <label>Phone Number</label>
                <input type="text" id="editPhone">
            </div>
            <div class="full">
                <label>Class</label>
                <select id="editClassId" required></select>
            </div>
            <div class="page-actions">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveStudentBtn">Save</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/toast.js"></script>
<script>
const table = document.getElementById("usertable");
const editModal = document.getElementById("editModal");
const editStudentForm = document.getElementById("editStudentForm");
const saveStudentBtn = document.getElementById("saveStudentBtn");

function closeEditModal() {
    editModal.style.display = "none";
    editStudentForm.reset();
}

function loadStudents() {
    fetch("API/getstudents.php")
    .then(res => {
        if (!res.ok) throw new Error("Failed to fetch students");
        return res.json();
    })
    .then(response => {
        const students = Array.isArray(response) ? response : (response.data || []);
if (students.length === 0) {
            table.innerHTML = `<tr><td colspan="6" class="empty-state">No student records found.</td></tr>`;
            return;
        }

        table.innerHTML = students.map(user => `
            <tr>
                <td>${user.student_number || ""}</td>
                <td>${user.fullname || ""}</td>
                <td>${user.phone || "-"}</td>
                <td>${user.grade_name || "-"}</td>
                <td>${user.class_name || "-"}</td>
                <td>
                    ${user.phone ? `<a href="tel:${user.phone}" class="btn btn-secondary" style="padding:4px 10px;">Call</a>` : ''}
                    <button type="button" class="btn btn-secondary" onclick="openEditModal(${Number(user.id)})">Edit</button>
                    <button type="button" class="btn btn-danger" onclick="deleteStudent(${Number(user.id)}, '${(user.fullname || "").replace(/'/g, "\\'")}')">Delete</button>
                </td>
            </tr>
        `).join("");
    })
    .catch(error => {
        table.innerHTML = `<tr><td colspan="6" class="empty-state">Failed to load student records.</td></tr>`;
        showToast(error.message, "error");
    });
}

function openEditModal(studentId) {
    fetch(`API/get_student.php?id=${studentId}`)
    .then(res => {
        if (!res.ok) throw new Error("Failed to load student details");
        return res.json();
    })
    .then(response => {
        document.getElementById("editStudentId").value = response.student.student_id;
        document.getElementById("editStudentNumber").value = response.student.student_number ?? "";
        document.getElementById("editFullname").value = response.student.fullname ?? "";
        document.getElementById("editPhone").value = response.student.phone ?? "";

        const classSelect = document.getElementById("editClassId");
        classSelect.innerHTML = '<option value="">Select Class</option>';

        response.classes.forEach(item => {
            const option = document.createElement("option");
            option.value = item.id;
            option.textContent = `${item.grade_name} ${item.class_name}`;
            if (String(item.id) === String(response.student.class_id)) {
                option.selected = true;
            }
            classSelect.appendChild(option);
        });

        editModal.style.display = "flex";
    })
    .catch(error => showToast(error.message, "error"));
}

function deleteStudent(studentId, fullname) {
    if (!window.confirm(`Delete ${fullname}? This will remove the student account.`)) {
        return;
    }

    fetch("API/delete_student.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ student_id: studentId })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message || "Delete failed");
        showToast(response.message, "success");
        loadStudents();
    })
    .catch(error => showToast(error.message || "Failed to delete student.", "error"));
}

editStudentForm.addEventListener("submit", function (event) {
    event.preventDefault();
    saveStudentBtn.disabled = true;
    saveStudentBtn.textContent = "Saving...";

    const payload = {
        student_id: document.getElementById("editStudentId").value,
        student_number: document.getElementById("editStudentNumber").value.trim(),
        fullname: document.getElementById("editFullname").value.trim(),
        phone: document.getElementById("editPhone").value.trim(),
        class_id: document.getElementById("editClassId").value
    };

    fetch("API/update_student.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message || "Update failed");
        closeEditModal();
        showToast(response.message, "success");
        loadStudents();
    })
    .catch(error => showToast(error.message || "Failed to update student.", "error"))
    .finally(() => {
        saveStudentBtn.disabled = false;
        saveStudentBtn.textContent = "Save";
    });
});

editModal.addEventListener("click", function (event) {
    if (event.target === editModal) {
        closeEditModal();
    }
});

loadStudents();
</script>
</body>
</html>
