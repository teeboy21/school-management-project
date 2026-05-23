<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'finance_manager'])) {
    header("Location: login.html");
    exit();
}
require_once 'config.php';
$dash_map = ['admin'=>'admindashboard.php','finance_manager'=>'finance_dashboard.php','principal'=>'principal_dashboard.php','hr_manager'=>'hr_dashboard.php','it_technician'=>'it_dashboard.php'];
$dashboard_url = $dash_map[$_SESSION['role'] ?? ''] ?? 'admindashboard.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Donations - <?= htmlspecialchars(get_school_info($conn, 'school_name')) ?></title>
<link rel="stylesheet" href="internal.css">
<style>
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
.form-grid .full { grid-column: 1 / -1; }
.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
.content-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); }
.content-card h3 { margin: 0 0 10px; font-size: 16px; }
.content-card p { margin: 5px 0; color: var(--app-muted); font-size: 14px; }
.content-card strong { color: var(--app-text); }
.page-actions { margin-top: 15px; display: flex; gap: 10px; }
.empty-state { text-align: center; padding: 40px; color: var(--app-muted); }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="<?= $dashboard_url ?>" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-meta">
<div class="app-header-title">Donations</div>
<div class="app-header-subtitle">Record and manage donations</div>
</div>
</header>
<section class="app-content">
<div class="panel" style="margin-bottom: 30px;">
<h2>Record Donation</h2>
<form id="donationForm" class="form-grid">
<input type="hidden" id="donationId">
<div><label>Donor Name</label><input type="text" id="donorName" required></div>
<div><label>Donor Type</label><select id="donorType"><option value="individual">Individual</option><option value="organization">Organization</option><option value="company">Company</option></select></div>
<div><label>Contact</label><input type="text" id="donorContact"></div>
<div><label>Email</label><input type="email" id="donorEmail"></div>
<div><label>Amount</label><input type="number" id="donationAmount" step="0.01" required></div>
<div><label>Type</label><select id="donationType"><option value="cash">Cash</option><option value="goods">Goods</option><option value="service">Service</option></select></div>
<div><label>Date</label><input type="date" id="donationDate" value="<?= date('Y-m-d') ?>"></div>
<div><label>Purpose</label><input type="text" id="donationPurpose"></div>
<div><label>Receipt #</label><input type="text" id="donationReceipt"></div>
<div class="full"><label>Notes</label><textarea id="donationNotes"></textarea></div>
<div class="full page-actions">
<button type="submit" class="btn btn-primary">Save Donation</button>
<button type="button" class="btn" onclick="clearForm()">Clear</button>
</div>
</form>
</div>
<div class="panel">
<h2>Donation Records</h2>
<div id="donationsList" class="cards-grid"></div>
</div>
</section>
</main>
</div>
<script>
function formatCurrency(a) { return 'R ' + parseFloat(a||0).toLocaleString('en-ZA',{minimumFractionDigits:2}); }

const donationForm = document.getElementById('donationForm');
if (donationForm) {
donationForm.addEventListener('submit', async (e) => {
e.preventDefault();
const payload = {
id: document.getElementById('donationId').value||null,
donor_name: document.getElementById('donorName').value,
donor_type: document.getElementById('donorType').value,
donor_contact: document.getElementById('donorContact').value,
donor_email: document.getElementById('donorEmail').value,
amount: parseFloat(document.getElementById('donationAmount').value),
donation_type: document.getElementById('donationType').value,
donation_date: document.getElementById('donationDate').value,
purpose: document.getElementById('donationPurpose').value,
receipt_number: document.getElementById('donationReceipt').value,
notes: document.getElementById('donationNotes').value
};
try {
const res = await fetch('API/finance_api.php?action=save_donation', {
method: 'POST', headers: {'Content-Type': 'application/json'},
body: JSON.stringify(payload)
});
const data = await res.json();
if (data.status === 'success') {
alert('Donation saved successfully!');
clearForm(); loadDonations();
} else {
alert(data.message || 'Failed to save donation');
}
} catch (e) {
alert('Error: ' + e.message);
}
});
}

function clearForm() {
document.getElementById('donationForm').reset();
document.getElementById('donationId').value = '';
document.getElementById('donationDate').value = '<?= date('Y-m-d') ?>';
}

async function loadDonations() {
try {
const res = await fetch('API/finance_api.php?action=get_donations');
const data = await res.json();
console.log('Donations response:', data);
if (data.error) {
document.getElementById('donationsList').innerHTML = '<div class="empty-state">Error: ' + data.error + '</div>';
return;
}
if(!data.length) {
document.getElementById('donationsList').innerHTML = '<div class="empty-state">No donations recorded</div>';
return;
}
document.getElementById('donationsList').innerHTML = data.map(d => `
<div class="content-card">
<h3>${d.donor_name}</h3>
<p>${d.donor_type} | ${d.donation_type}</p>
<p>Amount: <strong>${formatCurrency(d.amount)}</strong></p>
<p>Date: ${d.donation_date}</p>
<div class="page-actions">
<button class="btn btn-small" onclick="editDonation(${d.id})">Edit</button>
<button class="btn btn-small btn-danger" onclick="deleteDonation(${d.id})">Delete</button>
</div>
</div>`).join('');
} catch(e) {
console.error(e);
document.getElementById('donationsList').innerHTML = '<div class="empty-state">Error loading donations</div>';
}
}

function editDonation(id) {
fetch('API/finance_api.php?action=get_donations').then(r=>r.json()).then(data=>{
const d = data.find(x=>x.id===id);
if(d){
document.getElementById('donationId').value = d.id;
document.getElementById('donorName').value = d.donor_name;
document.getElementById('donorType').value = d.donor_type;
document.getElementById('donorContact').value = d.donor_contact||'';
document.getElementById('donorEmail').value = d.donor_email||'';
document.getElementById('donationAmount').value = d.amount;
document.getElementById('donationType').value = d.donation_type;
document.getElementById('donationDate').value = d.donation_date;
document.getElementById('donationPurpose').value = d.purpose||'';
document.getElementById('donationReceipt').value = d.receipt_number||'';
document.getElementById('donationNotes').value = d.notes||'';
}
});
}

async function deleteDonation(id) {
if(!confirm('Delete this donation?')) return;
await fetch('API/finance_api.php?action=delete_donation&id='+id);
loadDonations();
}

loadDonations();
console.log('Donations page loaded');
</script>
</body>
</html>