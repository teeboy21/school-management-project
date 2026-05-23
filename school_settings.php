<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin','principal'])) {
    header("Location: login.html"); exit;
}
$role = $_SESSION['role'];
$dashboard_url = match(true){
    $role === 'admin' => 'admindashboard.php',
    $role === 'principal' => 'principal_dashboard.php',
    default => 'login.html'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>School Settings</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
<style>
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-grid .full{grid-column:1/-1}
.form-grid label{display:block;font-size:13px;font-weight:600;margin-bottom:4px;color:var(--app-text)}
.form-grid input,.form-grid textarea{width:100%;padding:9px 12px;border-radius:8px;border:1px solid var(--app-border);font-family:inherit;font-size:14px;box-sizing:border-box}
.form-grid textarea{min-height:80px;resize:vertical}
.panel{background:var(--app-card);border-radius:12px;padding:24px;border:1px solid var(--app-border)}
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">School Settings</div>
<div class="app-header-subtitle">Manage school name, contact info, hours, and policies</div>
</div>
</header>
<section class="app-content">
<div class="hero-card">
<h2>School Information</h2>
<p>These values are used site-wide. Changes take effect immediately.</p>
</div>
<div class="panel">
<div class="form-grid" id="settingsForm"></div>
<div style="margin-top:20px;text-align:right">
<button class="btn btn-primary" onclick="saveSettings()">Save Changes</button>
</div>
</div>
</section>
</main>
</div>
<script src="assets/toast.js"></script>
<script>
const fields = [
    {key:'school_name',label:'School Name',type:'text',full:true},
    {key:'school_motto',label:'School Motto',type:'text',full:true},
    {key:'school_email',label:'School Email',type:'email'},
    {key:'school_phone',label:'Phone Number',type:'text'},
    {key:'school_whatsapp',label:'WhatsApp Number',type:'text'},
    {key:'school_website',label:'Website',type:'url'},
    {key:'school_address',label:'Address',type:'text',full:true},
    {key:'operating_hours',label:'Operating Hours',type:'text',full:true},
    {key:'terms_and_conditions',label:'Terms & Conditions',type:'textarea',full:true},
    {key:'email_notifications_enabled',label:'Email Notifications',type:'select',options:{on:'On',off:'Off'}},
    {key:'resend_api_key',label:'Resend API Key',type:'text',full:true},
    {key:'resend_from_email',label:'Sender Email Address',type:'email'}
];

let data = {};

async function loadSettings(){
    const res = await fetch('API/school_info_api.php?action=get');
    const rows = await res.json();
    if (!Array.isArray(rows)) return;
    rows.forEach(r => data[r.setting_key] = r.setting_value);
    renderForm();
}

function renderForm(){
    const container = document.getElementById('settingsForm');
    container.innerHTML = fields.map(f => {
        const val = (data[f.key] || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const cls = f.full ? 'full' : '';
        let input;
        if (f.type === 'textarea') {
            input = `<textarea id="${f.key}" rows="4">${val}</textarea>`;
        } else if (f.type === 'select') {
            input = `<select id="${f.key}">${Object.entries(f.options).map(([v,l]) => `<option value="${v}" ${val===v?'selected':''}>${l}</option>`).join('')}</select>`;
        } else {
            input = `<input type="${f.type}" id="${f.key}" value="${val}">`;
        }
        return `<div class="${cls}"><label for="${f.key}">${f.label}</label>${input}</div>`;
    }).join('');
}

async function saveSettings(){
    const payload = {};
    fields.forEach(f => payload[f.key] = document.getElementById(f.key).value.trim());
    const res = await fetch('API/school_info_api.php?action=save', {
        method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload)
    });
    const d = await res.json();
    showToast(d.message, d.status === 'success' ? 'success' : 'error');
}

loadSettings();
</script>
</body>
</html>
