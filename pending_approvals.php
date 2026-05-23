<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
require_once 'config.php';
$role = $_SESSION['role'] ?? '';
$fullname = $_SESSION['fullname'] ?? 'User';
$email = $_SESSION['email'] ?? '';
$dash_map = ['admin'=>'admindashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','hr_manager'=>'hr_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$role] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pending Approvals - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
.tab-nav { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
.tab-btn { padding:10px 18px; border:1px solid var(--app-border); background:var(--app-surface); border-radius:8px; cursor:pointer; font-weight:500; transition:all 0.2s; }
.tab-btn:hover { background:var(--app-bg); }
.tab-btn.active { background:var(--app-primary); color:#fff; border-color:var(--app-primary); }
.step-dot { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:4px; }
.step-dot.done { background:#16a34a; }
.step-dot.active { background:#d97706; }
.step-dot.pending { background:#d9e4f3; }
.step-dot.rejected { background:#dc2626; }
.approval-timeline { position:relative; padding-left:24px; }
.approval-timeline::before { content:''; position:absolute; left:8px; top:4px; bottom:4px; width:2px; background:var(--app-border); }
.timeline-item { position:relative; margin-bottom:16px; padding-left:16px; }
.timeline-item::before { content:''; position:absolute; left:-20px; top:6px; width:12px; height:12px; border-radius:50%; background:var(--app-border); }
.timeline-item.done::before { background:#16a34a; }
.timeline-item.active::before { background:#d97706; animation:pulse 1.5s infinite; }
.timeline-item.rejected::before { background:#dc2626; }
@keyframes pulse { 0%{box-shadow:0 0 0 0 rgba(217,119,6,0.4)} 70%{box-shadow:0 0 0 8px rgba(217,119,6,0)} 100%{box-shadow:0 0 0 0 rgba(217,119,6,0)} }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Approvals</div>
<div class="app-header-subtitle">Review and manage approval requests</div>
</div>
<div class="app-user">
<div class="app-user-name"><?= htmlspecialchars($fullname) ?></div>
<div class="app-user-email"><?= htmlspecialchars($email) ?></div>
</div>
</header>
<section class="app-content">
<div class="tab-nav">
<button class="tab-btn active" data-tab="pending" onclick="switchTab('pending')">Pending for Me</button>
<button class="tab-btn" data-tab="my" onclick="switchTab('my')">My Requests</button>
<button class="tab-btn" data-tab="all" onclick="switchTab('all')">All Requests</button>
<button class="tab-btn" data-tab="stats" onclick="switchTab('stats')">Statistics</button>
</div>

<div id="tab-pending" class="tab-content">
<div class="toolbar"><h2>Pending Your Approval</h2><span id="pendingCount" class="badge badge-pending">0</span></div>
<div id="pendingList" class="cards-grid"></div>
</div>

<div id="tab-my" class="tab-content" style="display:none">
<div class="toolbar"><h2>My Requests</h2></div>
<div id="myRequestsList" class="table-wrap"></div>
</div>

<div id="tab-all" class="tab-content" style="display:none">
<div class="toolbar">
<h2>All Approval Requests</h2>
<select id="allFilter" onchange="loadAll()" style="padding:8px 12px;border-radius:6px;">
<option value="">All Statuses</option>
<option value="pending_approval">Pending</option>
<option value="approved">Approved</option>
<option value="rejected">Rejected</option>
<option value="cancelled">Cancelled</option>
</select>
</div>
<div id="allRequestsList" class="table-wrap"></div>
</div>

<div id="tab-stats" class="tab-content" style="display:none">
<h2>Approval Statistics</h2>
<div id="statsGrid" class="cards-grid"></div>
</div>
</section>
</main>
</div>

<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }
function formatDate(d) { if(!d) return '-'; return new Date(d).toLocaleDateString('en-ZA',{year:'numeric',month:'short',day:'numeric'}); }

let currentTab = 'pending';

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.getElementById('tab-' + tab).style.display = 'block';
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active');
    if (tab === 'pending') loadPending();
    if (tab === 'my') loadMyRequests();
    if (tab === 'all') loadAll();
    if (tab === 'stats') loadStats();
}

function statusBadge(s) {
    const map = {
        draft:'badge-pending', pending_approval:'badge-pending', partially_approved:'badge-partial',
        approved:'badge-paid', rejected:'badge-expired', cancelled:'badge-terminated',
        paid:'badge-paid', pending:'badge-pending'
    };
    return `<span class="badge ${map[s]||'badge-pending'}">${s.replace(/_/g,' ')}</span>`;
}

function getRefLink(type, id) {
    const pages = {expense:'expenses.php', budget:'budgets.php', contract:'suppliers.php', payroll:'salaries.php'};
    return pages[type] ? pages[type] + '?highlight=' + id : '#';
}

async function loadPending() {
    const res = await fetch('API/approval_api.php?action=get_pending_approvals&limit=50');
    const data = await res.json();
    document.getElementById('pendingCount').textContent = data.length;
    const container = document.getElementById('pendingList');
    if (!data.length) {
        container.innerHTML = '<div class="panel" style="text-align:center;color:var(--app-muted);grid-column:1/-1">No pending approvals</div>';
        return;
    }
    container.innerHTML = data.map(r => {
        const ref = r.reference || {};
        const refAmount = ref.amount || r.amount || 0;
        const refName = ref.description || ref.supplier_name || ref.category || ref.month || '#' + r.reference_id;
        return `<div class="panel">
            <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:10px">
                <div>
                    <strong style="font-size:16px">${r.reference_type.toUpperCase()}: ${refName}</strong>
                    <div style="color:var(--app-muted);font-size:13px;margin-top:4px">
                        Submitted ${formatDate(r.created_at)} by ${r.requester_name || 'Unknown'}
                    </div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:20px;font-weight:700">${formatCurrency(refAmount)}</div>
                    ${statusBadge(r.status)}
                </div>
            </div>
            <div style="margin:12px 0">
                <div style="font-size:12px;color:var(--app-muted);margin-bottom:4px">Step ${r.step_progress + 1} of ${r.step_total}: <strong>${r.step_label}</strong></div>
                <div style="display:flex;gap:4px">${'<span class="step-dot done"></span>'.repeat(r.step_progress)}${'<span class="step-dot active"></span>'.repeat(1)}${'<span class="step-dot pending"></span>'.repeat(Math.max(0, r.step_total - r.step_progress - 1))}</div>
            </div>
            <div style="border-top:1px solid var(--app-border);padding-top:12px;margin-top:8px">
                <form class="approve-form" data-id="${r.id}" style="display:flex;gap:8px;align-items:start;flex-wrap:wrap">
                    <input type="text" class="comment-input" placeholder="Optional comment..." style="flex:1;min-width:150px;padding:8px 12px;border-radius:6px;border:1px solid var(--app-border)">
                    <button type="button" class="btn btn-primary btn-small" onclick="takeAction(${r.id},'approved',this)">Approve</button>
                    <button type="button" class="btn btn-small" style="background:#fee2e2;color:#991b1b" onclick="takeAction(${r.id},'rejected',this)">Reject</button>
                    <button type="button" class="btn btn-small btn-ghost" onclick="takeAction(${r.id},'returned',this)">Return</button>
                </form>
            </div>
            <div style="font-size:12px;margin-top:8px">
                <a href="${getRefLink(r.reference_type, r.reference_id)}" style="color:var(--app-primary)">View details &rarr;</a>
            </div>
        </div>`;
    }).join('');
}

async function takeAction(id, action, btn) {
    const form = btn.closest('.approve-form');
    const comment = form.querySelector('.comment-input').value;
    if (action === 'rejected' && !confirm('Reject this request?')) return;
    btn.disabled = true;
    btn.textContent = '...';
    const res = await fetch('API/approval_api.php?action=take_action', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({request_id: id, action, comment})
    });
    const data = await res.json();
    alert(data.message || data.error);
    loadPending();
    loadMyRequests();
    loadStats();
}

async function loadMyRequests() {
    const res = await fetch('API/approval_api.php?action=get_my_requests&limit=50');
    const data = await res.json();
    const tbody = data.map(r => `<tr>
        <td>${r.reference_type.toUpperCase()} #${r.reference_id}</td>
        <td>${statusBadge(r.status)}</td>
        <td>${formatCurrency(r.amount)}</td>
        <td>${formatDate(r.created_at)}</td>
        <td><button class="btn btn-small" onclick="viewRequest(${r.id})">View</button></td>
    </tr>`).join('');
    document.getElementById('myRequestsList').innerHTML = data.length
        ? `<table class="data-table"><thead><tr><th>Item</th><th>Status</th><th>Amount</th><th>Date</th><th></th></tr></thead><tbody>${tbody}</tbody></table>`
        : '<div class="panel empty-state">No requests submitted</div>';
}

async function loadAll() {
    const status = document.getElementById('allFilter').value;
    const res = await fetch('API/approval_api.php?action=get_all_approvals&status=' + status + '&limit=100');
    const data = await res.json();
    const tbody = data.map(r => `<tr>
        <td>${r.reference_type.toUpperCase()} #${r.reference_id}</td>
        <td>${r.requester_name || 'N/A'}</td>
        <td>${statusBadge(r.status)}</td>
        <td>${formatCurrency(r.amount)}</td>
        <td>${r.action_count || 0}</td>
        <td>${formatDate(r.created_at)}</td>
    </tr>`).join('');
    document.getElementById('allRequestsList').innerHTML = data.length
        ? `<table class="data-table"><thead><tr><th>Item</th><th>Requester</th><th>Status</th><th>Amount</th><th>Actions</th><th>Date</th></tr></thead><tbody>${tbody}</tbody></table>`
        : '<div class="panel empty-state">No requests found</div>';
}

async function loadStats() {
    const res = await fetch('API/approval_api.php?action=get_approval_summary');
    const s = await res.json();
    document.getElementById('statsGrid').innerHTML = `
        <div class="stat-card"><div>Pending for Me</div><strong style="color:var(--app-warning)">${s.pending_for_me}</strong></div>
        <div class="stat-card"><div>My Pending</div><strong style="color:var(--app-warning)">${s.my_pending}</strong></div>
        <div class="stat-card"><div>My Approved</div><strong style="color:var(--app-success)">${s.my_approved}</strong></div>
        <div class="stat-card"><div>My Rejected</div><strong style="color:var(--app-danger)">${s.my_rejected}</strong></div>
        <div class="stat-card"><div>Total Pending</div><strong>${s.total_pending}</strong></div>
    `;
    const moduleRes = await fetch('API/approval_api.php?action=get_module_stats');
    const modules = await moduleRes.json();
    if (modules.length) {
        document.getElementById('statsGrid').innerHTML += `<div class="panel" style="grid-column:1/-1"><h3>By Module</h3><table class="data-table"><thead><tr><th>Module</th><th>Status</th><th>Count</th></tr></thead><tbody>${
            modules.map(m => `<tr><td>${m.module}</td><td>${statusBadge(m.status)}</td><td>${m.count}</td></tr>`).join('')
        }</tbody></table></div>`;
    }
}

function viewRequest(id) {
    fetch('API/approval_api.php?action=get_request_details&id='+id).then(r=>r.json()).then(r => {
        const steps = (r.steps||[]).map(s => {
            let cls = 'pending';
            if (s.taken_action === 'approved') cls = 'done';
            else if (s.taken_action === 'rejected') cls = 'rejected';
            else if (s.id === r.current_step) cls = 'active';
            return `<div class="timeline-item ${cls}">
                <strong>${s.label}</strong>
                <div style="color:var(--app-muted);font-size:13px">${s.role_required}</div>
                ${s.actor_name ? `<div style="font-size:12px">${s.taken_action} by ${s.actor_name} ${s.acted_at ? formatDate(s.acted_at) : ''}</div>` : '<div style="font-size:12px;color:var(--app-muted)">Awaiting action</div>'}
            </div>`;
        }).join('');
        alert(`Request: ${r.reference_type.toUpperCase()} #${r.reference_id}\nStatus: ${r.status}\nAmount: ${formatCurrency(r.amount)}\n\n--- Approval Chain ---\n${(r.steps||[]).map(s => `${s.label}: ${s.taken_action || 'Awaiting'} by ${s.actor_name || s.role_required}`).join('\n')}`);
    });
}

loadPending();
loadStats();
</script>
</body>
</html>
