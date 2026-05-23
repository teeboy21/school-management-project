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
<title>Manage Employees</title>
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
    <div>Employee Management</div>
</div>

<div class="content">
    <div class="topbar">
        <input type="search" id="employeeSearch" placeholder="Search employees by name, email, or employee number">
        <button type="button" onclick="window.location.href='addemployee.php'">Register New Employee</button>
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
            <tbody id="employeesTable"></tbody>
        </table>
    </div>
</div>

<div class="modal" id="employeeModal">
    <div class="modal-card">
        <h3>Edit Employee</h3>
        <form id="employeeEditForm">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="grid">
                <input type="text" name="fullname" id="editFullname" placeholder="Full Name" required>
                <input type="email" name="email" id="editEmail" placeholder="Email" required>
                <input type="text" name="phone" id="editPhone" placeholder="Phone">
                <input type="text" name="identity_number" id="editIdentity" placeholder="Identity Number">
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
                <input type="text" name="department" id="editDepartment" placeholder="Department">
                <input type="text" name="job_title" id="editJobTitle" placeholder="Job Title">
                <textarea class="full" name="address" id="editAddress" placeholder="Address"></textarea>
            </div>
            <div class="actions">
                <button class="cancel-btn" type="button" onclick="closeEmployeeModal()">Cancel</button>
                <button class="save-btn" type="submit" id="saveEmployeeBtn">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/toast.js"></script>
<script>
const employeesTable = document.getElementById("employeesTable");
const employeeSearch = document.getElementById("employeeSearch");
const employeeModal = document.getElementById("employeeModal");
const employeeEditForm = document.getElementById("employeeEditForm");
const saveEmployeeBtn = document.getElementById("saveEmployeeBtn");
let employees = [];

function renderEmployees(rows) {
    if (!rows.length) {
        employeesTable.innerHTML = `<tr><td colspan="7">No employees found.</td></tr>`;
        return;
    }

    employeesTable.innerHTML = rows.map(row => `
        <tr>
            <td>${row.user_id}</td>
            <td>${row.fullname ?? ""} ${row.department ? `<br><small>${row.department}</small>` : ""}</td>
            <td>${row.email ?? ""}</td>
            <td>${row.employee_number ?? ""}</td>
            <td>${row.phone ?? ""}</td>
            <td>${row.hire_date ?? ""}</td>
            <td>
                <button class="action-btn edit-btn" onclick="openEmployeeModal(${row.user_id})">Edit</button>
                <button class="action-btn delete-btn" onclick="deleteEmployee(${row.user_id}, '${(row.fullname ?? "").replace(/'/g, "\\'")}')">Delete</button>
            </td>
        </tr>
    `).join("");
}

function loadEmployees() {
    fetch("API/get_employees.php")
    .then(res => {
        if (!res.ok) throw new Error("Failed to load employees");
        return res.json();
    })
    .then(response => {
        employees = response.data || [];
        renderEmployees(employees);
    })
    .catch(error => showToast(error.message, "error"));
}

function openEmployeeModal(userId) {
    fetch(`API/get_employee.php?user_id=${userId}`)
    .then(res => {
        if (!res.ok) throw new Error("Failed to load employee");
        return res.json();
    })
    .then(response => {
        const emp = response.employee;
        document.getElementById("editUserId").value = emp.user_id;
        document.getElementById("editFullname").value = emp.fullname ?? "";
        document.getElementById("editEmail").value = emp.email ?? "";
        document.getElementById("editPhone").value = emp.phone ?? "";
        document.getElementById("editIdentity").value = emp.identity_number ?? "";
        document.getElementById("editDob").value = emp.dob ?? "";
        document.getElementById("editHireDate").value = emp.hire_date ?? "";
        document.getElementById("editRace").value = emp.race ?? "";
        document.getElementById("editGender").value = emp.gender ?? "";
        document.getElementById("editDepartment").value = emp.department ?? "";
        document.getElementById("editJobTitle").value = emp.job_title ?? "";
        document.getElementById("editAddress").value = emp.address ?? "";
        employeeModal.classList.add("open");
    })
    .catch(error => showToast(error.message, "error"));
}

function closeEmployeeModal() {
    employeeModal.classList.remove("open");
    employeeEditForm.reset();
}

function deleteEmployee(userId, fullname) {
    if (!window.confirm(`Delete ${fullname}? This will remove the employee account.`)) {
        return;
    }

    fetch("API/delete_employee.php", {
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
        loadEmployees();
    })
    .catch(error => showToast(error.message, "error"));
}

employeeEditForm.addEventListener("submit", function (event) {
    event.preventDefault();
    saveEmployeeBtn.disabled = true;
    saveEmployeeBtn.textContent = "Saving...";

    const payload = Object.fromEntries(new FormData(employeeEditForm).entries());

    fetch("API/update_employee.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") {
            throw new Error(response.message || "Update failed");
        }
        closeEmployeeModal();
        showToast(response.message, "success");
        loadEmployees();
    })
    .catch(error => showToast(error.message, "error"))
    .finally(() => {
        saveEmployeeBtn.disabled = false;
        saveEmployeeBtn.textContent = "Save Changes";
    });
});

employeeSearch.addEventListener("input", function () {
    const query = this.value.toLowerCase().trim();
    if (!query) {
        renderEmployees(employees);
        return;
    }

    const filtered = employees.filter(row =>
        [row.fullname, row.email, row.employee_number, row.phone].join(" ").toLowerCase().includes(query)
    );
    renderEmployees(filtered);
});

employeeModal.addEventListener("click", function (event) {
    if (event.target === employeeModal) {
        closeEmployeeModal();
    }
});

loadEmployees();
</script>
</body>
</html>
