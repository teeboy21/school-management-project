<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Approve Subjects</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Approve Subjects</div>
                <div class="app-header-subtitle">Review and action subject requests without full page reloads.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="cards-grid" id="pendingRequests"></div>
        </section>
    </main>
</div>
<script src="assets/toast.js"></script>
<script>
const pendingRequests = document.getElementById("pendingRequests");

function renderRequests(rows) {
    if (!rows.length) {
        pendingRequests.innerHTML = `<div class="panel empty-state">No pending subject requests found.</div>`;
        return;
    }

    pendingRequests.innerHTML = rows.map(student => `
        <div class="content-card">
            <h3 style="margin:0 0 6px;">${student.fullname}</h3>
            <p style="margin:0 0 12px;">${student.email}</p>
            <div>${student.subjects.map(subject => `<span class="badge">${subject}</span>`).join("")}</div>
            <div class="page-actions">
                <button class="btn btn-success" onclick="runAction(${student.id}, 'approve')">Approve</button>
                <button class="btn btn-danger" onclick="runAction(${student.id}, 'reject')">Reject</button>
            </div>
        </div>
    `).join("");
}

function loadRequests() {
    fetch("API/pending_subject_requests.php")
    .then(res => res.json())
    .then(response => renderRequests(response.data || []))
    .catch(() => showToast("Failed to load subject requests.", "error"));
}

function runAction(student_id, action) {
    fetch("API/subject_request_action.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ student_id, action })
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") throw new Error(response.message);
        showToast(response.message, "success");
        loadRequests();
    })
    .catch(error => showToast(error.message, "error"));
}

loadRequests();
</script>
</body>
</html>
