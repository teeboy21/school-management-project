<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Select Subjects</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="userdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">Select Subjects</div>
                <div class="app-header-subtitle">Choose your subjects once. After submission, the page will only show your application status.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>

        <section class="app-content">
            <div class="hero-card">
                <h2 id="studentName">Student</h2>
                <p id="studentClass">Loading class information...</p>
            </div>

            <div id="selectionMessage"></div>

            <div class="panel" id="subjectPanel">
                <div id="subjectContent" class="empty-state">Loading subjects...</div>
            </div>
        </section>
    </main>
</div>

<script src="assets/toast.js"></script>
<script>
const subjectContent = document.getElementById("subjectContent");
const selectionMessage = document.getElementById("selectionMessage");
const studentName = document.getElementById("studentName");
const studentClass = document.getElementById("studentClass");

function renderBadgeList(items) {
    return items.map(item => `<span class="badge">${item.subject_name}</span>`).join("");
}

function renderAvailableForm(subjects, maxOptional) {
    if (!subjects.length) {
        subjectContent.innerHTML = `<div class="empty-state">No subjects are configured for your grade yet.</div>`;
        return;
    }

    const compulsory = subjects.filter(s => Number(s.is_compulsory) === 1);
    const optional = subjects.filter(s => Number(s.is_compulsory) !== 1);

    subjectContent.innerHTML = `
        <div style="margin-bottom:15px;">
            <strong>Compulsory Subjects</strong> (auto-included)
            <div style="margin-top:8px;">${compulsory.map(s => `<span class="badge">${s.subject_name}</span>`).join(' ')}</div>
        </div>
        <form id="subjectSelectionForm">
            <p style="margin-bottom:10px;">Select up to <strong>${maxOptional}</strong> optional subjects:</p>
            <p id="selectionCount" style="font-size:13px;color:var(--app-muted);margin-bottom:15px;">0 of ${maxOptional} selected</p>
            <div class="cards-grid">
                ${optional.map(subject => `
                    <label class="content-card" style="cursor:pointer;">
                        <div style="display:flex; justify-content:space-between; gap:10px; align-items:flex-start;">
                            <div>
                                <h3 style="margin:0 0 8px;">${subject.subject_name}</h3>
                                <p style="margin:0;">Select this subject if you want it included in your application.</p>
                            </div>
                            <span class="badge">Optional</span>
                        </div>
                        <div class="page-actions">
                            <input type="checkbox" name="subjects[]" value="${subject.id}" onchange="updateSelectionCount(${maxOptional})">
                        </div>
                    </label>
                `).join("")}
            </div>
            <div class="page-actions">
                <button type="submit" class="btn btn-primary">Save Subject Choices</button>
            </div>
        </form>
    `;

    document.getElementById("subjectSelectionForm").addEventListener("submit", function (event) {
        event.preventDefault();
        const selected = Array.from(document.querySelectorAll('input[name="subjects[]"]:checked')).map(input => Number(input.value));

        if (selected.length > maxOptional) {
            showToast("You can select at most " + maxOptional + " optional subjects", "error");
            return;
        }

        fetch("API/submit_subject_selection.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ subjects: selected })
        })
        .then(res => res.json())
        .then(response => {
            if (response.status !== "success") {
                throw new Error(response.message || "Failed to submit subjects");
            }
            showToast(response.message, "success");
            loadSelectionState();
        })
        .catch(error => showToast(error.message, "error"));
    });
}

function updateSelectionCount(maxOptional) {
    const count = document.querySelectorAll('input[name="subjects[]"]:checked').length;
    const el = document.getElementById('selectionCount');
    if (el) el.textContent = count + ' of ' + maxOptional + ' selected';
}

function loadSelectionState() {
    fetch("API/get_subject_selection.php")
    .then(res => {
        if (!res.ok) {
            throw new Error("Failed to load subject selection");
        }
        return res.json();
    })
    .then(response => {
        const student = response.student || {};
        const approved = response.approved_subjects || [];
        const pending = response.pending_subjects || [];
        const available = response.available_subjects || [];

        studentName.textContent = student.fullname || "Student";
        const classText = [student.grade_name, student.class_name].filter(Boolean).join(" ");
        studentClass.textContent = classText ? `Current class: ${classText}` : "You need an approved class before you can select subjects.";

        selectionMessage.innerHTML = "";

        if (!student.grade_id) {
            subjectContent.innerHTML = `<div class="empty-state">No class has been assigned to your account yet. Please contact the admin.</div>`;
            return;
        }

        if (approved.length > 0) {
            selectionMessage.innerHTML = `<div class="alert alert-success">Your subject application has already been approved.</div>`;
            subjectContent.innerHTML = `
                <h2>Approved Subjects</h2>
                <div>${renderBadgeList(approved)}</div>
            `;
            return;
        }

        if (pending.length > 0) {
            selectionMessage.innerHTML = `<div class="alert alert-warning">Your subject application is still pending approval.</div>`;
            subjectContent.innerHTML = `
                <h2>Pending Subjects</h2>
                <div>${renderBadgeList(pending)}</div>
            `;
            return;
        }

        renderAvailableForm(available, response.max_optional_subjects || 7);
    })
    .catch(error => {
        subjectContent.innerHTML = `<div class="empty-state">Failed to load subject selection.</div>`;
        showToast(error.message, "error");
    });
}

loadSelectionState();
</script>
</body>
</html>
