<?php
session_start();
require __DIR__ . '/config.php';

$allowed = ['admin', 'principal', 'hr_manager', 'finance_manager', 'it_technician'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed)) {
    header("Location: login.html");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];
$display_name = $_SESSION['fullname'] ?? ucfirst($role);
$display_email = $_SESSION['email'] ?? '';

$dash_map = ['admin'=>'admindashboard.php','hr_manager'=>'hr_dashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$role] ?? 'login.html';

$stmt = $conn->prepare("SELECT u.email, e.employee_number, e.fullname, e.phone, e.address, e.gender, e.hire_date, e.department, e.job_title FROM user u JOIN employees e ON u.id = e.user_id WHERE u.id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Profile</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:32px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal-box h2{margin:0 0 4px;font-size:22px}
.modal-box .modal-sub{color:#5f728c;margin:0 0 20px;font-size:14px}
.modal-actions{display:flex;gap:12px;margin-top:20px;justify-content:flex-end}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
@media(max-width:600px){.info-grid{grid-template-columns:1fr}}
.info-item label{font-size:12px;font-weight:700;color:#5f728c;text-transform:uppercase;display:block;margin-bottom:2px}
.info-item p{margin:0;font-size:16px;font-weight:600;color:#10233f}
.info-item .editable{cursor:pointer;transition:color 0.15s}
.info-item .editable:hover{color:#1677ff}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">My Profile</div>
<div class="app-header-subtitle">Your personal and employment details.</div>
</div>
<div class="app-user">
<div class="app-user-name"><?= htmlspecialchars($display_name) ?></div>
<div class="app-user-email"><?= htmlspecialchars($display_email) ?></div>
</div>
</header>
<section class="app-content">
<div class="panel">
<div class="info-grid">
<div class="info-item"><label>Full Name</label><p><?= htmlspecialchars($profile['fullname'] ?? '') ?></p></div>
<div class="info-item"><label>Email</label><p><?= htmlspecialchars($profile['email'] ?? '') ?></p></div>
<div class="info-item"><label>Employee Number</label><p><?= htmlspecialchars($profile['employee_number'] ?? '') ?></p></div>
<div class="info-item"><label>Department</label><p><?= htmlspecialchars($profile['department'] ?? '') ?></p></div>
<div class="info-item"><label>Job Title</label><p><?= htmlspecialchars($profile['job_title'] ?? '') ?></p></div>
<div class="info-item"><label>Gender</label><p><?= htmlspecialchars(ucfirst($profile['gender'] ?? '')) ?></p></div>
<div class="info-item"><label>Hire Date</label><p><?= htmlspecialchars($profile['hire_date'] ?? '') ?></p></div>
<div class="info-item"><label>Phone</label><p class="editable" onclick="openEditModal('phone')"><?= htmlspecialchars($profile['phone'] ?: '—') ?> ✏</p></div>
<div class="info-item" style="grid-column:1/-1"><label>Address</label><p class="editable" onclick="openEditModal('address')"><?= htmlspecialchars($profile['address'] ?: '—') ?> ✏</p></div>
</div>
</div>
</section>
</main>
</div>

<div class="modal-overlay" id="editModal">
<div class="modal-box">
<h2 id="modalTitle">Edit</h2>
<p class="modal-sub" id="modalSub">Update your information below.</p>
<form id="editForm">
<input type="hidden" id="editField">
<div id="editFieldContainer"></div>
<div class="modal-actions">
<button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
<button type="submit" class="btn btn-primary">Save</button>
</div>
</form>
</div>
</div>

<script src="assets/toast.js"></script>
<script>
function openEditModal(field) {
    const modal = document.getElementById("editModal");
    const title = document.getElementById("modalTitle");
    const sub = document.getElementById("modalSub");
    const container = document.getElementById("editFieldContainer");
    const hidden = document.getElementById("editField");

    hidden.value = field;
    const current = <?= json_encode($profile) ?>;

    if (field === "phone") {
        title.textContent = "Edit Phone Number";
        sub.textContent = "Enter your updated cellphone number.";
        container.innerHTML = `<label>Phone Number</label><input type="text" class="text-input" id="editValue" value="${current.phone || ''}" required placeholder="+27 XX XXX XXXX">`;
    } else {
        title.textContent = "Edit Address";
        sub.textContent = "Enter your updated physical address.";
        container.innerHTML = `<label>Address</label><textarea class="text-input" id="editValue" rows="3" required placeholder="Street, City, Province">${current.address || ''}</textarea>`;
    }
    modal.classList.add("show");
}

function closeModal() {
    document.getElementById("editModal").classList.remove("show");
}

document.getElementById("editForm").addEventListener("submit", async function(e) {
    e.preventDefault();
    const field = document.getElementById("editField").value;
    const value = document.getElementById("editValue").value;
    const data = {};
    if (field === "phone") data.phone = value;
    else data.address = value;

    const btn = this.querySelector("button[type=submit]");
    btn.disabled = true;
    btn.textContent = "Saving...";

    try {
        const res = await fetch("API/update_profile.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify(data)
        });
        const result = await res.json();
        showToast(result.message, result.status === "success" ? "success" : "error");
        if (result.status === "success") {
            closeModal();
            setTimeout(() => location.reload(), 1000);
        }
    } catch (err) {
        showToast("Error saving changes", "error");
    }
    btn.disabled = false;
    btn.textContent = "Save";
});
</script>
</body>
</html>
