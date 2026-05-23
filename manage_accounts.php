<?php
session_start();
$allowed_roles = ['admin', 'it_technician'];
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    header("Location: login.html");
    exit();
}
require_once 'config.php';
$dash_map = ['admin'=>'admindashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Accounts</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
<style>
.badge{padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600}
.badge-locked{background:#fee2e2;color:#dc2626}
.badge-active{background:#dcfce7;color:#16a34a}
.badge-pending{background:#fef3c7;color:#d97706}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:20px}
.stat-card{background:var(--app-card);border-radius:10px;padding:14px 16px;border:1px solid var(--app-border)}
.stat-card .num{font-size:22px;font-weight:700}
.stat-card .lbl{font-size:12px;color:var(--app-muted);margin-top:2px}
.stat-card.red .num{color:#dc2626}
.stat-card.green .num{color:#16a34a}
.stat-card.amber .num{color:#d97706}
.section-title{font-size:15px;font-weight:600;margin:0 0 12px;display:flex;align-items:center;gap:8px}
.section-title .count{background:var(--app-border);border-radius:20px;padding:1px 8px;font-size:11px;color:var(--app-muted)}
.locked-card{background:var(--app-card);border:1px solid #fecaca;border-radius:10px;padding:14px 16px;margin-bottom:20px}
.locked-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--app-border)}
.locked-row:last-child{border-bottom:none}
.locked-row .info{flex:1}
.locked-row .name{font-weight:600;font-size:14px}
.locked-row .meta{font-size:12px;color:var(--app-muted)}
.locked-row .reason{font-size:12px;color:#dc2626;margin-top:2px}
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(2px)}
.modal-box{background:var(--app-card);border-radius:14px;padding:28px;width:440px;max-width:92vw;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.modal-box h3{margin:0 0 6px;font-size:17px}
.modal-box .sub{font-size:13px;color:var(--app-muted);margin:0 0 16px}
.modal-box select,.modal-box input[type=text]{width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--app-border);margin-bottom:14px;font-family:inherit;font-size:14px;box-sizing:border-box}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:6px}
.confirm-text{font-size:14px;margin:0 0 16px;line-height:1.5}
.confirm-text strong{color:var(--app-text)}
.empty-state{padding:20px;text-align:center;color:var(--app-muted);font-size:13px}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Manage Accounts</div>
<div class="app-header-subtitle">Block and unblock user accounts</div>
</div>
</header>
<section class="app-content">

<div class="stats" id="statsRow"></div>

<div id="lockedSection"></div>

<div class="toolbar" style="margin-bottom:12px">
<input type="text" id="searchInput" placeholder="Search by name or email..." oninput="loadAll()" style="padding:8px 12px;border-radius:6px;border:1px solid var(--app-border);width:260px;font-size:13px">
<select id="roleFilter" onchange="loadAll()" style="padding:8px;border-radius:6px;border:1px solid var(--app-border);font-size:13px">
<option value="">All Roles</option>
</select>
</div>

<div class="table-wrap">
<table class="data-table">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Attempts</th><th>Action</th></tr></thead>
<tbody id="accountsBody"></tbody>
</table>
</div>

</section>
</main>
</div>

<div class="modal-overlay" id="modal">
<div class="modal-box">
<h3 id="modalTitle">Block Account</h3>
<p class="sub" id="modalUser"></p>
<p class="confirm-text" id="confirmText">Are you sure you want to block this account?</p>
<div id="blockArea">
<select id="reasonSelect" onchange="toggleCustom()"></select>
<div id="customWrap" style="display:none">
<input type="text" id="reasonCustom" placeholder="Enter custom reason...">
</div>
</div>
<div class="modal-actions">
<button class="btn btn-secondary" onclick="closeModal()">Cancel</button>
<button class="btn btn-primary" id="confirmBtn" onclick="confirmAction()">Yes, Block</button>
</div>
</div>
</div>

<script src="assets/toast.js"></script>
<script>
let currentAction = null;
let currentUserId = null;

const REASONS = ['Violation of school policy','Unauthorized access attempt','Account compromise','Suspicious activity','Harassment or abuse','Other'];

async function loadReasons() {
    const sel = document.getElementById('reasonSelect');
    try {
        const r = await fetch('API/settings_api.php?action=get_one&key=block_reasons');
        const d = await r.json();
        if (d.value) {
            const arr = d.value.split('\n').map(s=>s.trim()).filter(Boolean);
            if (arr.length) { sel.innerHTML = arr.map(v=>'<option value="'+v.replace(/"/g,'&quot;')+'">'+v+'</option>').join(''); return; }
        }
    } catch(e){}
    sel.innerHTML = REASONS.map(v=>'<option value="'+v+'">'+v+'</option>').join('');
}

function toggleCustom(){document.getElementById('customWrap').style.display=document.getElementById('reasonSelect').value==='Other'?'block':'none'}

async function loadAll() {
    const params = new URLSearchParams({action:'list_accounts',search:document.getElementById('searchInput').value,status:''});
    const res = await fetch('API/account_api.php?'+params);
    let data;
    try{data=await res.json()}catch(e){data=[]}
    if(!Array.isArray(data))data=[];

    const locked = data.filter(a=>a.locked==1);
    const pending = data.filter(a=>a.status==='pending' || a.status==='profile_completed' || a.status==='account_created');
    const rejected = data.filter(a=>a.status==='rejected');
    const active = data.filter(a=>a.locked!=1 && !pending.includes(a) && !rejected.includes(a));

    document.getElementById('statsRow').innerHTML = `
        <div class="stat-card"><div class="num">${data.length}</div><div class="lbl">Total Accounts</div></div>
        <div class="stat-card green"><div class="num">${active.length}</div><div class="lbl">Active</div></div>
        <div class="stat-card red"><div class="num">${locked.length}</div><div class="lbl">Locked</div></div>
        <div class="stat-card amber"><div class="num">${pending.length}</div><div class="lbl">Pending</div></div>
        <div class="stat-card" style="border-color:#fecaca"><div class="num" style="color:#dc2626">${rejected.length}</div><div class="lbl">Rejected</div></div>
    `;

    const ls = document.getElementById('lockedSection');
    if (!locked.length) {
        ls.innerHTML = '';
    } else {
        ls.innerHTML = `<div class="locked-card"><div class="section-title">🔒 Locked Accounts <span class="count">${locked.length}</span></div>
            ${locked.map(a=>{
                const sn=(a.fullname||a.email).replace(/'/g,"\\'");
                return `<div class="locked-row"><div class="info"><div class="name">${a.fullname||'—'}</div>
                <div class="meta">${a.email} · ${a.role_name||'—'} · ${a.login_attempts||0} attempt(s)</div>
                <div class="reason">${a.locked_reason||''}</div></div>
                <button class="btn btn-success btn-small" onclick="openModal(${a.id},'${sn}','unblock')">Unblock</button></div>`;
            }).join('')}</div>`;
    }

    const roles = [...new Set(data.map(a=>a.role_name).filter(Boolean))];
    const rf = document.getElementById('roleFilter');
    const curVal = rf.value;
    rf.innerHTML = '<option value="">All Roles</option>'+roles.map(r=>'<option value="'+r+'">'+r+'</option>').join('');
    rf.value = curVal;

    const filtered = rf.value ? data.filter(a=>a.role_name===rf.value) : data;

    const tb = document.getElementById('accountsBody');
    if (!filtered.length) {
        tb.innerHTML = '<tr><td colspan="6" class="empty-state">No accounts found</td></tr>';
        return;
    }
    tb.innerHTML = filtered.map(a=>{
        const displayStatus = a.locked==1 ? 'locked' : a.status;
        let badgeClass = 'badge-pending';
        if (a.locked==1) badgeClass = 'badge-locked';
        else if (a.status==='approved' || a.status==='active') badgeClass = 'badge-active';
        else if (a.status==='rejected') badgeClass = 'badge-locked';
        const il = a.locked==1;
        const bl = il?'Unblock':'Block';
        const bc = il?'btn-success':'btn-danger';
        const sn=(a.fullname||a.email).replace(/'/g,"\\'");
        return `<tr><td><strong>${a.fullname||'—'}</strong></td>
            <td>${a.email}</td><td>${a.role_name||'—'}</td>
            <td><span class="badge ${badgeClass}">${displayStatus}</span></td>
            <td>${a.login_attempts||0}</td>
            <td><button class="btn btn-small ${bc}" onclick="openModal(${a.id},'${sn}','${il?'unblock':'block'}')">${bl}</button></td></tr>`;
    }).join('');
}

function openModal(id,name,action){
    currentUserId=id;currentAction=action;
    document.getElementById('modalUser').textContent=name+' (ID: '+id+')';
    const ct = document.getElementById('confirmText');
    if(action==='block'){
        document.getElementById('modalTitle').textContent='Block Account';
        document.getElementById('confirmBtn').textContent='Yes, Block';
        document.getElementById('confirmBtn').className='btn btn-danger';
        document.getElementById('blockArea').style.display='block';
        document.getElementById('reasonSelect').value=REASONS[0];
        document.getElementById('customWrap').style.display='none';
        document.getElementById('reasonCustom').value='';
        ct.textContent='Are you sure you want to block '+name+'? They will be unable to log in until unblocked.';
    } else {
        document.getElementById('modalTitle').textContent='Unblock Account';
        document.getElementById('confirmBtn').textContent='Yes, Unblock';
        document.getElementById('confirmBtn').className='btn btn-success';
        document.getElementById('blockArea').style.display='none';
        ct.textContent='Are you sure you want to unblock '+name+'? They will regain access to their account.';
    }
    document.getElementById('modal').style.display='flex';
}
function closeModal(){document.getElementById('modal').style.display='none';currentAction=null;currentUserId=null;}

async function confirmAction(){
    let reason;
    if(currentAction==='block'){
        reason=document.getElementById('reasonSelect').value;
        if(reason==='Other'){reason=document.getElementById('reasonCustom').value.trim();if(!reason){showToast('Enter a custom reason','error');return}}
    } else reason='Unblocked by administrator';
    const action=currentAction==='block'?'block_account':'unblock_account';
    const res=await fetch('API/account_api.php?action='+action,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({user_id:currentUserId,reason})});
    const d=await res.json();
    if(d.status==='success'){showToast(d.message,'success');closeModal();loadAll();}
    else showToast(d.error||'Action failed','error');
}

loadReasons();
loadAll();
setInterval(loadAll,15000);
</script>
</body>
</html>
