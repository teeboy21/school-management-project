<?php
session_start();
include 'config.php';
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.html");
    exit();
}
require_once 'system_check.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rate Teachers</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
<style>
.teacher-card{background:var(--app-card);border-radius:12px;padding:20px;margin-bottom:16px;border:1px solid var(--app-border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.teacher-info{flex:1;min-width:200px}
.teacher-info h3{margin:0 0 4px}
.teacher-info .subject{color:var(--app-muted);font-size:13px}
.stars{display:flex;gap:4px;font-size:28px;cursor:pointer}
.star{color:#d1d5db;transition:color .15s}
.star.active{color:#f59e0b}
.star:hover{color:#f59e0b}
.rating-done{color:#16a34a;font-weight:600}
.comment-input{width:100%;margin-top:8px;padding:8px;border-radius:6px;border:1px solid var(--app-border);resize:vertical}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="userdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Rate Your Teachers</div>
<div class="app-header-subtitle">Your ratings are anonymous and help improve teaching quality</div>
</div>
</header>
<section class="app-content" id="ratingsContent">
<div id="loadingMsg" class="panel"><div class="empty-state">Loading teachers...</div></div>
</section>
</main>
</div>
<script src="assets/toast.js"></script>
<script>
async function init() {
    const check = await fetch('API/teacher_ratings_api.php?action=check_enabled').then(r=>r.json());
    if (!check.enabled) {
        document.getElementById('ratingsContent').innerHTML = '<div class="panel"><div class="empty-state">Teacher ratings are currently disabled by the administrator.</div></div>';
        return;
    }
    const teachers = await fetch('API/teacher_ratings_api.php?action=get_my_teachers').then(r=>r.json());
    const container = document.getElementById('ratingsContent');
    if (!teachers.length) {
        container.innerHTML = '<div class="panel"><div class="empty-state">No teachers available to rate. You need to be enrolled in subjects first.</div></div>';
        return;
    }
    container.innerHTML = teachers.map(t => {
        const stars = [1,2,3,4,5].map(s => `<span class="star${t.my_rating && s <= t.my_rating ? ' active' : ''}" data-rating="${s}" onclick="setRating(${t.teacher_id}, ${s})">★</span>`).join('');
        const done = t.rated ? '<span class="rating-done">✓ Rated</span>' : '';
        return `<div class="teacher-card">
            <div class="teacher-info">
                <h3>${t.fullname} ${done}</h3>
                <div class="subject">${t.subject_name}</div>
                <textarea class="comment-input" id="comment_${t.teacher_id}" placeholder="Optional comment..." rows="2">${t.my_comment || ''}</textarea>
            </div>
            <div class="stars" id="stars_${t.teacher_id}">${stars}</div>
        </div>`;
    }).join('');
}
async function setRating(teacherId, rating) {
    const comment = document.getElementById('comment_' + teacherId).value;
    const res = await fetch('API/teacher_ratings_api.php?action=submit_rating', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({teacher_id: teacherId, rating, comment})
    });
    const data = await res.json();
    if (data.status === 'success') {
        showToast(data.message, 'success');
        init();
    } else {
        showToast(data.error || 'Error submitting rating', 'error');
    }
}
init();
</script>
</body>
</html>
