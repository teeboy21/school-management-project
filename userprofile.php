<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$student = $conn->query("
    SELECT u.email, s.fullname, s.student_number, s.phone, s.gender, s.address, c.class_name, g.name AS grade
    FROM user u
    JOIN students s ON u.id = s.user_id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN grades g ON c.grade_id = g.id
    WHERE u.id = $user_id
")->fetch_assoc();

$subjects = $conn->query("
    SELECT sub.subject_name
    FROM student_subject ss
    JOIN subjects sub ON ss.subject_id = sub.id
    WHERE ss.student_id = $user_id AND ss.status = 'approved'
    ORDER BY sub.subject_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Profile</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:32px;max-width:480px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal-box h2{margin:0 0 4px;font-size:22px}
.modal-box .modal-sub{color:#5f728c;margin:0 0 20px;font-size:14px}
.modal-actions{display:flex;gap:12px;margin-top:20px;justify-content:flex-end}
.info-item{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #eef4fb}
.info-item:last-child{border-bottom:none}
.info-item label{font-size:13px;font-weight:700;color:#5f728c;text-transform:uppercase;min-width:140px}
.info-item p{margin:0;font-size:15px;color:#10233f;flex:1}
.edit-btn{background:none;border:none;color:#1677ff;cursor:pointer;font-size:13px;font-weight:600;padding:4px 10px;border-radius:6px;transition:background 0.15s}
.edit-btn:hover{background:#e8f0ff}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="userdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Student Profile</div>
                <div class="app-header-subtitle">Your core profile, class details, and approved subjects in one page.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel" style="margin-bottom:20px;">
                <h2>Personal Information</h2>
                <div class="info-item"><label>Name</label><p><?= htmlspecialchars($student['fullname'] ?? '') ?></p></div>
                <div class="info-item"><label>Email</label><p><?= htmlspecialchars($student['email'] ?? '') ?></p></div>
                <div class="info-item"><label>Student No.</label><p><?= htmlspecialchars($student['student_number'] ?? 'Not assigned') ?></p></div>
                <div class="info-item"><label>Gender</label><p><?= htmlspecialchars($student['gender'] ?? '') ?></p></div>
                <div class="info-item"><label>Class</label><p><?= htmlspecialchars(trim(($student['grade'] ?? '') . ' ' . ($student['class_name'] ?? ''))) ?></p></div>
                <div class="info-item"><label>Phone</label><p><?= htmlspecialchars($student['phone'] ?: '—') ?></p><button class="edit-btn" onclick="openEditModal('phone')">Edit</button></div>
                <div class="info-item"><label>Address</label><p><?= htmlspecialchars($student['address'] ?: '—') ?></p><button class="edit-btn" onclick="openEditModal('address')">Edit</button></div>
            </div>
            <div class="panel">
                <h2>Approved Subjects</h2>
                <?php if ($subjects->num_rows > 0): ?>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <span class="badge"><?= htmlspecialchars($subject['subject_name']) ?></span>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">No approved subjects yet.</div>
                <?php endif; ?>
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
    document.getElementById("editField").value = field;
    const current = <?= json_encode($student) ?>;

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
        if (result.status === "success") { closeModal(); setTimeout(() => location.reload(), 1000); }
    } catch (err) { showToast("Error saving changes", "error"); }
    btn.disabled = false;
    btn.textContent = "Save";
});
</script>
</body>
</html>
