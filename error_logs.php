<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','it_technician'])) {
    header("Location: login.html"); exit;
}
$role = $_SESSION['role'];
$dashboard_url = match(true){
    $role === 'admin' => 'admindashboard.php',
    $role === 'it_technician' => 'it_dashboard.php',
    default => 'login.html'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Error Logs</title>
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="internal.css">
<script src="assets/toast.js"></script>
<style>
.error-card{background:var(--app-card);border-radius:10px;padding:16px;border:1px solid var(--app-border);margin-bottom:10px;cursor:pointer;transition:box-shadow .15s}
.error-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.08)}
.error-card .hdr{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.error-card .hdr .msg{font-size:14px;font-weight:600;color:#dc2626;word-break:break-word;flex:1}
.error-card .meta{font-size:12px;color:var(--app-muted);margin-top:4px}
.error-card .meta span{margin-right:16px}
.error-card .trace{display:none;margin-top:10px;padding:10px;background:#f8f8f8;border-radius:6px;font-size:12px;font-family:monospace;white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto}
.detail-panel{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:9999}
.detail-box{background:var(--app-card);border-radius:14px;padding:28px;width:680px;max-width:94vw;max-height:85vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.detail-box h3{margin:0 0 4px}
.detail-box .field{margin-bottom:14px}
.detail-box .field .lbl{font-size:12px;color:var(--app-muted);margin-bottom:2px}
.detail-box .field .val{font-size:14px;word-break:break-word;background:#f5f5f5;padding:8px 10px;border-radius:6px;font-family:monospace;font-size:12px;white-space:pre-wrap;max-height:160px;overflow:auto}
.toolbar-row{display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap}
.toolbar-row input{flex:1;min-width:200px}
.toolbar-row .count{font-size:13px;color:var(--app-muted)}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Error Logs</div>
<div class="app-header-subtitle">Captured system errors for debugging</div>
</div>
</header>
<section class="app-content">

<div class="toolbar-row">
<input type="text" id="searchInput" placeholder="Search errors..." oninput="loadErrors()">
<span class="count" id="totalCount"></span>
<button class="btn btn-danger btn-small" onclick="clearOld()">Clear 30+ days</button>
<button class="btn btn-danger btn-small" onclick="deleteAll()">Clear All</button>
</div>

<div id="errorList"></div>
</section>
</main>
</div>

<div class="detail-panel" id="detailPanel">
<div class="detail-box">
<h3>Error Details</h3>
<p class="sub" style="font-size:13px;color:var(--app-muted);margin:0 0 16px">Full exception information</p>
<div class="field"><div class="lbl">Error Message</div><div class="val" id="dMsg" style="color:#dc2626;font-weight:600;font-size:13px"></div></div>
<div class="field"><div class="lbl">File</div><div class="val" id="dFile"></div></div>
<div class="field"><div class="lbl">Line</div><div class="val" id="dLine"></div></div>
<div class="field"><div class="lbl">User</div><div class="val" id="dUser"></div></div>
<div class="field"><div class="lbl">URL</div><div class="val" id="dUrl"></div></div>
<div class="field"><div class="lbl">Method</div><div class="val" id="dMethod"></div></div>
<div class="field"><div class="lbl">Request Data</div><div class="val" id="dData"></div></div>
<div class="field"><div class="lbl">Stack Trace</div><div class="val" id="dTrace" style="max-height:300px"></div></div>
<div class="field"><div class="lbl">Timestamp</div><div class="val" id="dTime"></div></div>
<div style="margin-top:16px;text-align:right"><button class="btn btn-secondary" onclick="closeDetail()">Close</button></div>
</div>
</div>

<script>
async function loadErrors(){
    const params = new URLSearchParams({action:'list',search:document.getElementById('searchInput').value});
    const res = await fetch('API/error_log_api.php?'+params);
    const d = await res.json();
    document.getElementById('totalCount').textContent = (d.total||0)+' error(s)';
    const list = document.getElementById('errorList');
    if (!d.rows || !d.rows.length) {
        list.innerHTML = '<div class="empty-state">No errors recorded</div>';
        return;
    }
    list.innerHTML = d.rows.map(r => {
        const msg = (r.error_message||'').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const file = (r.error_file||'').split('/').pop().split('\\').pop();
        const date = r.created_at||'';
        return `<div class="error-card" onclick="showDetail(${r.id})">
            <div class="hdr">
                <div class="msg">${msg}</div>
            </div>
            <div class="meta">
                <span>📁 ${file}:${r.error_line||'?'}</span>
                <span>👤 ${r.user_email||'—'}</span>
                <span>🕐 ${date}</span>
            </div>
        </div>`;
    }).join('');
}

async function showDetail(id){
    const res = await fetch('API/error_log_api.php?action=get&id='+id);
    const r = await res.json();
    if (r.status==='error') return showToast(r.message,'error');
    document.getElementById('dMsg').textContent = r.error_message||'—';
    document.getElementById('dFile').textContent = r.error_file||'—';
    document.getElementById('dLine').textContent = r.error_line||'—';
    document.getElementById('dUser').textContent = (r.user_email||'—')+' (ID: '+(r.user_id||'0')+')';
    document.getElementById('dUrl').textContent = r.request_url||'—';
    document.getElementById('dMethod').textContent = r.request_method||'—';
    document.getElementById('dData').textContent = r.request_data||'—';
    document.getElementById('dTrace').textContent = r.error_trace||'—';
    document.getElementById('dTime').textContent = r.created_at||'—';
    document.getElementById('detailPanel').style.display='flex';
}

function closeDetail(){document.getElementById('detailPanel').style.display='none'}

async function clearOld(){
    if (!confirm('Delete error logs older than 30 days?')) return;
    const res = await fetch('API/error_log_api.php?action=clear&older_than_days=30');
    const d = await res.json();
    showToast(d.message,d.status==='success'?'success':'error');
    if (d.status==='success') loadErrors();
}

async function deleteAll(){
    if (!confirm('Delete ALL error logs? This cannot be undone.')) return;
    const res = await fetch('API/error_log_api.php?action=delete_all');
    const d = await res.json();
    showToast(d.message,d.status==='success'?'success':'error');
    if (d.status==='success') loadErrors();
}

loadErrors();
</script>
</body>
</html>
