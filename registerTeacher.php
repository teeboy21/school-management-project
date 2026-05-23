<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager'])) {
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
<title>Manage Teachers</title>
<link rel="stylesheet" href="assets/toast.css">
<style>
*{box-sizing:border-box;font-family:Arial,sans-serif}
body{margin:0;background:#f8fafc;color:#0f172a}
.header{background:#1e3a8a;color:white;padding:18px 24px;display:flex;justify-content:space-between;align-items:center}
.header a{color:white;text-decoration:none;font-weight:700}
.content{padding:24px;max-width:1200px;margin:0 auto}
.topbar{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:20px;flex-wrap:wrap}
.topbar input{padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px;min-width:280px}
.topbar button{background:#0ea5e9;color:white;border:none;padding:12px 14px;border-radius:12px;cursor:pointer;font-weight:700}
.panel{background:white;border-radius:18px;padding:18px;box-shadow:0 15px 35px rgba(15,23,42,.08)}
table{width:100%;border-collapse:collapse}
th,td{padding:14px 12px;border-bottom:1px solid #e2e8f0;text-align:left}
th{background:#f8fafc}
.action-btn{padding:8px 10px;border:none;border-radius:10px;color:white;cursor:pointer;margin-right:6px}
.edit-btn{background:#0ea5e9}
.delete-btn{background:#ef4444}
.modal{position:fixed;inset:0;background:rgba(15,23,42,.5);display:none;align-items:center;justify-content:center;padding:20px}
.modal.open{display:flex}
.modal-card{width:min(720px,100%);background:white;border-radius:18px;padding:22px}
.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.grid .full{grid-column:1/-1}
.grid input,.grid select,.grid textarea{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:12px}
.grid textarea{min-height:100px}
.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}
.actions button{padding:12px 14px;border:none;border-radius:12px;cursor:pointer;font-weight:700}
.save-btn{background:#16a34a;color:white}
.cancel-btn{background:#e2e8f0;color:#0f172a}
@media (max-width:700px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="header">
    <a href="<?= $dashboard_url ?>">Back To Dashboard</a>
    <div>Teacher Management</div>
</div>

<div class="content">
    <div class="topbar">
        <input type="search" id="teacherSearch" placeholder="Search teachers by name, email, or employee number">
        <button type="button" onclick="window.location.href='addteacher.php'">Register New Teacher</button>
    </div>

    <div class="panel">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Employee No</th>
                    <th>Phone</th>
                    <th>Hire Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="teachersTable"></tbody>
        </table>
    </div>
</div>

<div class="modal" id="teacherModal">
    <div class="modal-card">
        <h3>Edit Teacher</h3>
        <form id="teacherEditForm">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="grid">
                <input type="text" name="fullname" id="editFullname" placeholder="Full Name" required>
                <input type="email" name="email" id="editEmail" placeholder="Email" required>
                <input type="text" name="phone" id="editPhone" placeholder="Phone">
                <input type="text" name="identitynumber" id="editIdentity" placeholder="Identity Number">
                <div>
                    <label>Date of Birth</label>
                    <input type="date" name="dob" id="editDob" required>
                </div>
                <div>
                    <label>Hire Date</label>
                    <input type="date" name="hire_date" id="editHireDate" required>
                </div>
                <select name="race" id="editRace" required>
                    <option value="">Select Race</option>
                    <option value="black">Black</option>
                    <option value="white">White</option>
                    <option value="asian">Asian</option>
                    <option value="other">Other</option>
                </select>
                <select name="gender" id="editGender" required>
                    <option value="">Select Gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>
                <textarea class="full" name="address" id="editAddress" placeholder="Address"></textarea>
            </div>
            <div class="actions">
                <button class="cancel-btn" type="button" onclick="closeTeacherModal()">Cancel</button>
                <button class="save-btn" type="submit" id="saveTeacherBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/toast.js"></script>
<script>
const teachersTable = document.getElementById("teachersTable");
const teacherSearch = document.getElementById("teacherSearch");
const teacherModal = document.getElementById("teacherModal");
const teacherEditForm = document.getElementById("teacherEditForm");
const saveTeacherBtn = document.getElementById("saveTeacherBtn");
let teachers = [];

function renderTeachers(rows) {
    if (!rows.length) {
        teachersTable.innerHTML = `<tr><td colspan="7">No teachers found.</td></tr>`;
        return;
    }

    teachersTable.innerHTML = rows.map(row => `
        <tr>
            <td>${row.user_id}</td>
            <td>${row.fullname ?? ""}</td>
            <td>${row.email ?? ""}</td>
            <td>${row.employee_number ?? ""}</td>
            <td>${row.phone ?? ""}</td>
            <td>${row.hire_date ?? ""}</td>
            <td>
                <button class="action-btn edit-btn" onclick="openTeacherModal(${row.user_id})">Edit</button>
                <button class="action-btn delete-btn" onclick="deleteTeacher(${row.user_id}, '${(row.fullname ?? "").replace(/'/g, "\\'")}')">Delete</button>
            </td>
        </tr>
    `).join("");
}

function loadTeachers() {
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
}

function openTeacherModal(userId) {
    fetch(`API/get_teacher.php?user_id=${userId}`)
    .then(res => {
        if (!res.ok) throw new Error("Failed to load teacher");
        return res.json();
    })
    .then(response => {
        const teacher = response.teacher;
        document.getElementById("editUserId").value = teacher.user_id;
        document.getElementById("editFullname").value = teacher.fullname ?? "";
        document.getElementById("editEmail").value = teacher.email ?? "";
        document.getElementById("editPhone").value = teacher.phone ?? "";
        document.getElementById("editIdentity").value = teacher.identitynumber ?? "";
        document.getElementById("editDob").value = teacher.dob ?? "";
        document.getElementById("editHireDate").value = teacher.hire_date ?? "";
        document.getElementById("editRace").value = teacher.race ?? "";
        document.getElementById("editGender").value = teacher.gender ?? "";
        document.getElementById("editAddress").value = teacher.address ?? "";
        teacherModal.classList.add("open");
    })
    .catch(error => showToast(error.message, "error"));
}

function closeTeacherModal() {
    teacherModal.classList.remove("open");
    teacherEditForm.reset();
}

function deleteTeacher(userId, fullname) {
    if (!window.confirm(`Delete ${fullname}? This will remove the teacher account.`)) {
        return;
    }

    fetch("API/delete_teacher.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ user_id: userId })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") {
            throw new Error(response.message || "Delete failed");
        }
        showToast(response.message, "success");
        loadTeachers();
    })
    .catch(error => showToast(error.message, "error"));
}

teacherEditForm.addEventListener("submit", function (event) {
    event.preventDefault();
    saveTeacherBtn.disabled = true;
    saveTeacherBtn.textContent = "Saving...";

    const payload = Object.fromEntries(new FormData(teacherEditForm).entries());

    fetch("API/update_teacher.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") {
            throw new Error(response.message || "Update failed");
        }
        closeTeacherModal();
        showToast(response.message, "success");
        loadTeachers();
    })
    .catch(error => showToast(error.message, "error"))
    .finally(() => {
        saveTeacherBtn.disabled = false;
        saveTeacherBtn.textContent = "Save Changes";
    });
});

teacherSearch.addEventListener("input", function () {
    const query = this.value.toLowerCase().trim();
    if (!query) {
        renderTeachers(teachers);
        return;
    }

    const filtered = teachers.filter(row =>
        [row.fullname, row.email, row.employee_number, row.phone].join(" ").toLowerCase().includes(query)
    );
    renderTeachers(filtered);
});

teacherModal.addEventListener("click", function (event) {
    if (event.target === teacherModal) {
        closeTeacherModal();
    }
});

loadTeachers();
</script>
</body>
</html>
