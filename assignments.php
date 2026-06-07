<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
$role = $_SESSION['role'];
$user_id = (int)$_SESSION['user_id'];
$display_name = $_SESSION['fullname'] ?? ucfirst($role);
$display_email = $_SESSION['email'] ?? '';

$dash_map = ['admin'=>'admindashboard.php','student'=>'userdashboard.php','teacher'=>'teacherdashboard.php','hr_manager'=>'hr_dashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$role] ?? 'login.html';

require __DIR__ . '/config.php';

$assignments_enabled = true;
$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'assignments_enabled'");
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if ($row && $row['setting_value'] === 'off') {
    $assignments_enabled = false;
}

$teacher_sidebar = [
    ['label' => 'Dashboard', 'href' => 'teacherdashboard.php'],
    ['label' => 'My Classes', 'href' => 'myclassesteacher.php'],
    ['label' => 'Students', 'href' => 'mystudentsteacher.php'],
    ['label' => 'Marks', 'href' => 'marks.php'],
    ['label' => 'Assignments', 'href' => 'assignments.php', 'active' => true],
    ['label' => 'Student Results', 'href' => 'results.php'],
    ['label' => 'Salary', 'href' => 'teacher_salary.php'],
    ['label' => 'Profile', 'href' => 'teacherprofile.php'],
];

$student_sidebar = [
    ['label' => 'Dashboard', 'href' => 'userdashboard.php'],
    ['label' => 'My Subjects', 'href' => 'studentsubject.php'],
    ['label' => 'Assignments', 'href' => 'assignments.php', 'active' => true],
    ['label' => 'Results', 'href' => 'results.php'],
    ['label' => 'My Fees', 'href' => 'fees.php'],
    ['label' => 'Profile', 'href' => 'userprofile.php'],
    ['label' => 'Select Subjects', 'href' => 'selectsubject.php'],
    ['label' => 'Rate Teachers', 'href' => 'teacher_ratings.php'],
    ['label' => 'Events', 'href' => 'events.php'],
];

if ($role === 'teacher') {
    $sidebar = $teacher_sidebar;
} elseif ($role === 'student') {
    $sidebar = $student_sidebar;
} else {
    header("Location: $dashboard_url");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assignments</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
<style>
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal-box{background:#fff;border-radius:16px;padding:32px;max-width:600px;width:90%;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.2)}
.modal-box h2{margin:0 0 4px;font-size:24px}
.modal-box .modal-sub{color:#5f728c;margin:0 0 20px;font-size:14px}
.modal-actions{display:flex;gap:12px;margin-top:20px;justify-content:flex-end}
.sub-card{background:#f8fafc;border:1px solid var(--app-border);border-radius:12px;padding:16px;margin-bottom:12px}
.sub-card .sub-meta{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.sub-card .sub-student{font-weight:600;color:var(--app-text)}
.sub-card .sub-status{font-size:13px;padding:4px 12px;border-radius:20px;font-weight:600}
.sub-card .sub-status.graded{background:#d1fae5;color:#065f46}
.sub-card .sub-status.pending{background:#fef3c7;color:#92400e}
.sub-card .score-input{width:80px;padding:8px 10px;border-radius:8px;border:1px solid var(--app-border);font-size:14px;text-align:center}
.assignment-card{background:var(--app-card);border:1px solid var(--app-border);border-radius:12px;padding:20px;transition:box-shadow 0.2s}
.assignment-card:hover{box-shadow:0 4px 20px rgba(0,0,0,0.06)}
.assignment-card .card-header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:8px}
.assignment-card .card-header h3{margin:0;font-size:17px;color:var(--app-text)}
.assignment-card .card-meta{font-size:13px;color:var(--app-muted);display:flex;gap:16px;flex-wrap:wrap;margin-bottom:8px}
.assignment-card .card-desc{font-size:14px;color:var(--app-text);line-height:1.5;margin-bottom:12px;white-space:pre-wrap}
.assignment-card .card-actions{display:flex;gap:8px;flex-wrap:wrap}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:700}
.badge-submitted{background:#d1fae5;color:#065f46}
.badge-graded{background:#dbeafe;color:#1e40af}
.badge-pending{background:#fef3c7;color:#92400e}
.badge-overdue{background:#fee2e2;color:#991b1b}
.tab-bar{display:flex;gap:4px;margin-bottom:20px;background:#f1f5f9;border-radius:10px;padding:4px;width:fit-content}
.tab-btn{padding:8px 20px;border:none;border-radius:8px;background:transparent;font-weight:600;font-size:14px;cursor:pointer;color:#5f728c;transition:all 0.15s}
.tab-btn.active{background:#fff;color:#10233f;box-shadow:0 2px 8px rgba(0,0,0,0.06)}
.tab-content{display:none}
.tab-content.show{display:block}
.text-muted{color:#5f728c;font-size:13px}
.file-link{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:#f0f6ff;border-radius:8px;font-size:13px;font-weight:600;color:#0d57c6;text-decoration:none;transition:background 0.15s}
.file-link:hover{background:#dbeafe}
</style>
</head>
<body>
<div class="app-shell">
<?php if (!empty($sidebar)): ?>
<aside class="app-sidebar">
    <div class="app-brand"><?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></div>
    <ul class="app-nav">
        <?php foreach ($sidebar as $item): ?>
            <li class="<?= !empty($item['active']) ? 'active' : '' ?>">
                <a href="<?= $item['href'] ?>"><?= $item['label'] ?></a>
            </li>
        <?php endforeach; ?>
        <li><form action="logout.php" method="post"><button type="submit">Logout</button></form></li>
    </ul>
</aside>
<?php endif; ?>
<main class="app-main">
    <header class="app-header">
        <a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
        <div class="app-header-meta">
            <div class="app-header-title">Assignments</div>
            <div class="app-header-subtitle"><?= $role === 'teacher' ? 'Create, manage and grade assignments for your classes.' : 'View and submit your assignments.' ?></div>
        </div>
        <div class="app-user">
            <div class="app-user-name"><?= htmlspecialchars($display_name) ?></div>
            <div class="app-user-email"><?= htmlspecialchars($display_email) ?></div>
        </div>
    </header>
    <section class="app-content" id="appContent">
        <?php if ($assignments_enabled): ?>
        <div id="loadingState" class="empty-state" style="padding:60px 0">Loading assignments...</div>
        <?php else: ?>
        <div class="hero-card">
            <h2>Assignments</h2>
            <p>The assignments module is currently disabled by the administrator.</p>
        </div>
        <?php endif; ?>
    </section>
</main>
</div>

<div class="modal-overlay" id="gradeModal">
    <div class="modal-box">
        <h2>Grade Submissions</h2>
        <p class="modal-sub" id="gradeModalSub">Assignment title</p>
        <div id="submissionsList"></div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeGradeModal()">Close</button>
        </div>
    </div>
</div>

<script src="assets/toast.js"></script>
<?php if ($assignments_enabled): ?>
<script>
const role = <?= json_encode($role) ?>;
const appContent = document.getElementById("appContent");

/* ================= LOAD ================= */
async function loadPage() {
    if (role === "teacher") {
        await renderTeacherView();
    } else if (role === "student") {
        await renderStudentView();
    }
}

/* ================= TEACHER VIEW ================= */
async function renderTeacherView() {
    const [res, subjectsRes] = await Promise.all([
        fetch("API/assignment_api.php?action=my_assignments"),
        fetch("API/get_teacher_subjects.php?teacher_id=<?= $user_id ?>")
    ]);
    const assignments = await res.json();
    let subjects = [];
    try { subjects = await subjectsRes.json(); } catch(e) {}

    const hasAssignments = Array.isArray(assignments) && assignments.length > 0;

    appContent.innerHTML = createFormHTML(subjects);
    const listContainer = document.getElementById("assignmentsList");
    if (!hasAssignments) {
        listContainer.innerHTML = `<div class="empty-state">No assignments yet. Create your first one above.</div>`;
    } else {
        listContainer.innerHTML = renderAssignmentCards(assignments);
        listContainer.querySelectorAll(".view-submissions-btn").forEach(b => b.addEventListener("click", e => {
            openGradeModal(e.target.dataset.id, e.target.dataset.title);
        }));
        listContainer.querySelectorAll(".delete-assignment-btn").forEach(b => b.addEventListener("click", e => {
            if (confirm("Delete this assignment and all submissions?")) deleteAssignment(e.target.dataset.id);
        }));
    }

    document.getElementById("createForm").addEventListener("submit", createAssignment);
}

function createFormHTML(subjects) {
    return `
    <div class="hero-card">
        <h2>Create Assignment</h2>
        <p>Set a title, attach a file, and assign it to your subject.</p>
    </div>
    <div class="panel" style="margin-bottom:20px">
        <form id="createForm" class="form-grid" enctype="multipart/form-data">
            <div class="full">
                <label>Title</label>
                <input type="text" name="title" id="inp_title" required placeholder="e.g. Chapter 5 Review">
            </div>
            <div>
                <label>Subject</label>
                <select name="subject_id" id="inp_subject" required>
                    <option value="">Select subject</option>
                    ${subjects.map(s => `<option value="${s.id}">${s.subject_name}${s.grade_name ? ' — ' + s.grade_name : ''}</option>`).join("")}
                </select>
            </div>
            <div>
                <label>Max Score</label>
                <input type="number" name="max_score" id="inp_max_score" value="100" min="1" max="1000">
            </div>
            <div>
                <label>Due Date</label>
                <input type="date" name="due_date" id="inp_due_date" required>
            </div>
            <div>
                <label>Due Time (optional)</label>
                <input type="time" name="due_time" id="inp_due_time">
            </div>
            <div class="full">
                <label>Description</label>
                <textarea name="description" id="inp_desc" rows="3" placeholder="Instructions, requirements, or notes..."></textarea>
            </div>
            <div class="full">
                <label>Attachment (optional)</label>
                <input type="file" name="file" id="inp_file">
            </div>
            <div class="full page-actions">
                <button type="submit" class="btn btn-primary" id="createBtn">Create Assignment</button>
            </div>
        </form>
    </div>
    <div class="panel">
        <div class="toolbar">
            <h2>My Assignments</h2>
            <input class="search-input" type="search" id="searchAssignments" placeholder="Search by title..." oninput="filterAssignments()">
        </div>
        <div id="assignmentsList"></div>
    </div>`;
}

function renderAssignmentCards(assignments) {
    const now = new Date().toISOString().slice(0,10);
    let html = `<div class="cards-grid" id="assignContainer">`;
    assignments.forEach(a => {
        const due = a.due_date;
        const overdue = due < now;
        html += `
        <div class="assignment-card" data-title="${a.title.toLowerCase()}">
            <div class="card-header">
                <h3>${a.title}</h3>
                <span class="badge ${overdue ? 'badge-overdue' : 'badge-pending'}">${overdue ? 'Overdue' : 'Open'}</span>
            </div>
            <div class="card-meta">
                <span>📘 ${a.subject_name}</span>
                <span>📅 Due: ${a.due_date}${a.due_time ? ' ' + a.due_time.slice(0,5) : ''}</span>
                <span>🏆 ${a.max_score} pts</span>
                ${a.grade_name ? `<span>📚 ${a.grade_name}${a.class_name ? ' ' + a.class_name : ''}</span>` : ''}
                <span>📝 ${a.submission_count} submission(s)</span>
            </div>
            ${a.description ? `<div class="card-desc">${a.description}</div>` : ''}
            <div class="card-actions">
                ${a.file_path ? `<a href="${a.file_path}" target="_blank" class="file-link">📎 ${a.file_name || 'Attachment'}</a>` : ''}
                <button class="btn btn-primary btn-small view-submissions-btn" data-id="${a.id}" data-title="${a.title}">View Submissions (${a.submission_count})</button>
                <button class="btn btn-danger btn-small delete-assignment-btn" data-id="${a.id}">Delete</button>
            </div>
        </div>`;
    });
    html += `</div>`;
    return html;
}

async function createAssignment(e) {
    e.preventDefault();
    const btn = document.getElementById("createBtn");
    btn.disabled = true;
    btn.textContent = "Creating...";

    const fd = new FormData(document.getElementById("createForm"));
    fd.append("action", "create");

    try {
        const res = await fetch("API/assignment_api.php?action=create", { method: "POST", body: fd });
        const data = await res.json();
        showToast(data.message, data.status === "success" ? "success" : "error");
        if (data.status === "success") {
            document.getElementById("createForm").reset();
            await renderTeacherView();
        }
    } catch (err) {
        showToast("Error creating assignment", "error");
    }
    btn.disabled = false;
    btn.textContent = "Create Assignment";
}

async function deleteAssignment(id) {
    const res = await fetch("API/assignment_api.php?action=delete", {
        method: "POST",
        headers: {"Content-Type":"application/x-www-form-urlencoded"},
        body: "id=" + id
    });
    const data = await res.json();
    showToast(data.message, data.status === "success" ? "success" : "error");
    if (data.status === "success") await renderTeacherView();
}

function filterAssignments() {
    const q = document.getElementById("searchAssignments").value.toLowerCase();
    document.querySelectorAll(".assignment-card").forEach(c => {
        const title = c.dataset.title || "";
        c.style.display = title.includes(q) ? "" : "none";
    });
}

/* ================= STUDENT VIEW ================= */
async function renderStudentView() {
    const res = await fetch("API/assignment_api.php?action=student_assignments");
    const assignments = await res.json();

    const has = Array.isArray(assignments) && assignments.length > 0;
    const now = new Date().toISOString().slice(0,10);
    let pending = 0, submitted = 0, overdue = 0;
    if (has) {
        assignments.forEach(a => {
            if (a.submission_id) { submitted++; }
            else if (a.due_date < now) { overdue++; }
            else { pending++; }
        });
    }

    let html = `
    <div class="cards-grid" style="margin-bottom:20px">
        <div class="stat-card"><div>Pending</div><strong>${pending}</strong></div>
        <div class="stat-card"><div>Submitted</div><strong>${submitted}</strong></div>
        <div class="stat-card"><div>Overdue</div><strong>${overdue}</strong></div>
    </div>
    <div class="panel">
        <div class="toolbar">
            <h2>My Assignments</h2>
            <input class="search-input" type="search" id="sSearch" placeholder="Search..." oninput="sFilter()">
        </div>
        <div id="sList">`;

    if (!has) {
        html += `<div class="empty-state">No assignments yet. Check back later.</div>`;
    } else {
        html += `<div class="cards-grid" id="sContainer">`;
        assignments.forEach(a => {
            const due = a.due_date;
            const dueDate = new Date(due + (a.due_time ? 'T' + a.due_time : ''));
            const isLate = !a.submission_id && dueDate < new Date();
            const isGraded = a.my_score !== null;

            html += `
            <div class="assignment-card" data-title="${a.title.toLowerCase()}">
                <div class="card-header">
                    <h3>${a.title}</h3>
                    ${a.submission_id
                        ? `<span class="badge ${isGraded ? 'badge-graded' : 'badge-submitted'}">${isGraded ? 'Graded: ' + a.my_score + '/' + a.max_score : 'Submitted'}</span>`
                        : `<span class="badge ${isLate ? 'badge-overdue' : 'badge-pending'}">${isLate ? 'Overdue' : 'Pending'}</span>`
                    }
                </div>
                <div class="card-meta">
                    <span>📘 ${a.subject_name}</span>
                    <span>👨‍🏫 ${a.teacher_name}</span>
                    <span>📅 Due: ${a.due_date}${a.due_time ? ' ' + a.due_time.slice(0,5) : ''}</span>
                    <span>🏆 ${a.max_score} pts</span>
                </div>
                ${a.description ? `<div class="card-desc">${a.description}</div>` : ''}
                <div class="card-actions">
                    ${a.file_path ? `<a href="${a.file_path}" target="_blank" class="file-link">📎 ${a.file_name || 'Attachment'}</a>` : ''}
                    ${!a.submission_id
                        ? `<button class="btn btn-primary btn-small" onclick="showSubmitModal(${a.id})">Submit</button>`
                        : `<span class="text-muted">${a.submitted_at ? 'Submitted ' + new Date(a.submitted_at).toLocaleDateString() : ''}</span>`
                    }
                    ${isGraded && a.feedback ? `<button class="btn btn-ghost btn-small" onclick="showFeedback('${a.feedback.replace(/'/g, "\\'")}')">Feedback</button>` : ''}
                </div>
            </div>`;
        });
        html += `</div>`;
    }

    html += `</div></div>`;
    appContent.innerHTML = html;
}

function sFilter() {
    const q = document.getElementById("sSearch").value.toLowerCase();
    document.querySelectorAll("#sContainer .assignment-card").forEach(c => {
        c.style.display = (c.dataset.title || "").includes(q) ? "" : "none";
    });
}

function showFeedback(text) {
    showToast(text, "info", 5000);
}

/* ================= SUBMIT MODAL (STUDENT) ================= */
function showSubmitModal(assignmentId) {
    const overlay = document.createElement("div");
    overlay.className = "modal-overlay show";
    overlay.id = "submitModal";
    overlay.innerHTML = `
    <div class="modal-box">
        <h2>Submit Assignment</h2>
        <p class="modal-sub">Upload your completed work.</p>
        <form id="submitForm">
            <input type="hidden" name="assignment_id" value="${assignmentId}">
            <div>
                <label>File</label>
                <input type="file" name="file" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('submitModal').remove()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit</button>
            </div>
        </form>
    </div>`;
    document.body.appendChild(overlay);
    document.getElementById("submitForm").addEventListener("submit", async e => {
        e.preventDefault();
        const btn = e.target.querySelector("button[type=submit]");
        btn.disabled = true;
        btn.textContent = "Uploading...";
        const fd = new FormData(e.target);
        fd.append("action", "submit");
        try {
            const res = await fetch("API/submission_api.php?action=submit", { method: "POST", body: fd });
            const data = await res.json();
            showToast(data.message, data.status === "success" ? "success" : "error");
            if (data.status === "success") { overlay.remove(); await renderStudentView(); }
        } catch (err) { showToast("Upload failed", "error"); }
        btn.disabled = false;
        btn.textContent = "Submit";
    });
}

/* ================= GRADE MODAL (TEACHER) ================= */
async function openGradeModal(assignmentId, title) {
    document.getElementById("gradeModalSub").textContent = title;
    const list = document.getElementById("submissionsList");
    list.innerHTML = "Loading...";
    document.getElementById("gradeModal").classList.add("show");

    const res = await fetch(`API/submission_api.php?action=submissions&assignment_id=${assignmentId}`);
    const data = await res.json();
    const subs = data.submissions || [];
    const students = data.all_students || [];

    if (!subs.length && !students.length) {
        list.innerHTML = `<div class="empty-state">No students assigned to this subject.</div>`;
        return;
    }

    const submittedIds = new Set(subs.map(s => s.student_id));
    const unsubmitted = students.filter(s => !submittedIds.has(s.student_id));

    let html = "";
    subs.forEach(s => {
        const isGraded = s.score !== null;
        html += `
        <div class="sub-card" data-sub-id="${s.id}">
            <div class="sub-meta">
                <span class="sub-student">${s.student_name} (${s.student_number || 'N/A'})</span>
                <span class="sub-status ${isGraded ? 'graded' : 'pending'}">${isGraded ? 'Graded: ' + s.score + '/100' : 'Pending'}</span>
            </div>
            <div style="margin-top:10px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
                ${s.file_path ? `<a href="${s.file_path}" target="_blank" class="file-link">📎 ${s.file_name || 'Download'}</a>` : '<span class="text-muted">No file</span>'}
                <input type="number" class="score-input" id="score_${s.id}" value="${s.score !== null ? s.score : ''}" placeholder="Score" min="0" max="100">
                <input type="text" class="text-input" style="flex:1;min-width:150px" id="feedback_${s.id}" value="${s.feedback || ''}" placeholder="Feedback (optional)">
                <button class="btn btn-primary btn-small" onclick="gradeSubmission(${s.id})">Save</button>
            </div>
        </div>`;
    });

    if (unsubmitted.length) {
        html += `<div style="margin-top:16px"><strong style="font-size:14px;color:#5f728c;">Not yet submitted (${unsubmitted.length})</strong>`;
        unsubmitted.forEach(s => {
            html += `<div class="text-muted" style="padding:6px 0">${s.student_name}</div>`;
        });
        html += `</div>`;
    }

    list.innerHTML = html;
}

async function gradeSubmission(submissionId) {
    const score = document.getElementById("score_" + submissionId).value;
    const feedback = document.getElementById("feedback_" + submissionId).value;

    if (score === '' || score < 0 || score > 100) {
        showToast("Enter a score between 0 and 100", "error");
        return;
    }

    const res = await fetch("API/submission_api.php?action=grade", {
        method: "POST",
        headers: {"Content-Type":"application/json"},
        body: JSON.stringify({submission_id: submissionId, score: parseInt(score), feedback})
    });
    const data = await res.json();
    showToast(data.message, data.status === "success" ? "success" : "error");
    if (data.status === "success") {
        const card = document.querySelector(`.sub-card[data-sub-id="${submissionId}"]`);
        if (card) {
            const status = card.querySelector(".sub-status");
            status.textContent = "Graded: " + score + "/100";
            status.className = "sub-status graded";
        }
    }
}

function closeGradeModal() {
    document.getElementById("gradeModal").classList.remove("show");
}

loadPage();
</script>
<?php endif; ?>
</body>
</html>
