<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['hr_manager', 'principal', 'admin'])) {
    header("Location: login.html");
    exit();
}
require_once 'config.php';
$dash_map = ['admin'=>'admindashboard.php','hr_manager'=>'hr_dashboard.php','principal'=>'principal_dashboard.php','finance_manager'=>'finance_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Ratings</title>
<link rel="stylesheet" href="internal.css">
<style>
.rating-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}
.rating-card{background:var(--app-card);border-radius:12px;padding:20px;border:1px solid var(--app-border)}
.rating-card h3{margin:0 0 4px}
.rating-card .avg{font-size:32px;font-weight:700;margin:8px 0}
.rating-card .count{color:var(--app-muted);font-size:13px}
.bar-container{display:flex;align-items:center;gap:8px;margin:4px 0}
.bar-label{width:20px;font-size:12px;color:var(--app-muted);text-align:right}
.bar-track{flex:1;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden}
.bar-fill{height:100%;border-radius:4px;transition:width .3s}
.satisfaction{display:inline-block;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600}
.high{background:#dcfce7;color:#16a34a}
.medium{background:#fef3c7;color:#d97706}
.low{background:#fee2e2;color:#dc2626}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Teacher Ratings</div>
<div class="app-header-subtitle">Anonymous student ratings aggregated by teacher</div>
</div>
</header>
<section class="app-content">
<div id="ratingsContainer"><div class="panel"><div class="empty-state">Loading...</div></div></div>
</section>
</main>
</div>
<script>
async function loadRatings() {
    const data = await fetch('API/teacher_ratings_api.php?action=get_ratings').then(r=>r.json());
    const container = document.getElementById('ratingsContainer');
    if (!data.length) {
        container.innerHTML = '<div class="panel"><div class="empty-state">No ratings data available yet.</div></div>';
        return;
    }
    const colors = {1:'#dc2626',2:'#f97316',3:'#eab308',4:'#a3e635',5:'#16a34a'};
    container.innerHTML = '<div class="rating-grid">' + data.map(t => {
        const bars = [5,4,3,2,1].map(s => {
            const pct = t.rating_count > 0 ? ((t.distribution[s] || 0) / t.rating_count * 100).toFixed(0) : 0;
            return `<div class="bar-container"><span class="bar-label">${s}★</span><div class="bar-track"><div class="bar-fill" style="width:${pct}%;background:${colors[s]}"></div></div><span style="font-size:12px;color:var(--app-muted);min-width:24px">${t.distribution[s] || 0}</span></div>`;
        }).join('');
        const satClass = t.satisfaction_pct >= 70 ? 'high' : t.satisfaction_pct >= 40 ? 'medium' : 'low';
        return `<div class="rating-card">
            <h3>${t.fullname}</h3>
            <div class="count">${t.rating_count} rating${t.rating_count !== 1 ? 's' : ''}</div>
            <div class="avg">${t.avg_rating || '—'} <span style="font-size:16px;font-weight:400;color:var(--app-muted)">/ 5</span></div>
            <div class="satisfaction ${satClass}">${t.satisfaction_pct || 0}% satisfaction</div>
            <div style="margin-top:12px">${bars}</div>
        </div>`;
    }).join('') + '</div>';
}
loadRatings();
</script>
</body>
</html>
