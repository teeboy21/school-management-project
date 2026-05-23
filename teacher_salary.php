<?php
session_start();
require "config.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "teacher") {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$salary = $conn->query("SELECT * FROM employee_salaries WHERE employee_id = $user_id AND is_active = 1")->fetch_assoc();
$payments = $conn->query("SELECT * FROM salary_payments WHERE employee_id = $user_id ORDER BY payment_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Salary</title>
<link rel="stylesheet" href="internal.css">
<style>
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px; }
.stat-card { background: var(--app-card); padding: 20px; border-radius: 8px; border: 1px solid var(--app-border); }
.stat-card .label { font-size: 12px; color: var(--app-muted); text-transform: uppercase; }
.stat-card .value { font-size: 24px; font-weight: 700; margin-top: 5px; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--app-border); }
.data-table th { background: var(--app-bg); font-weight: 600; font-size: 12px; }
.hero-card { background: var(--app-card); padding: 30px; border-radius: 8px; border: 1px solid var(--app-border); margin-bottom: 30px; }
.badge { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
.badge-paid { background: #d4edda; color: #155724; }
.badge-pending { background: #fff3cd; color: #856404; }
.empty-state { text-align: center; padding: 40px; color: var(--app-muted); }
</style>
</head>
<body>
<div class="app-shell">
<main class="app-main">
<header class="app-header">
<a href="teacherdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
<div class="app-header-title">My Salary</div>
<div class="app-header-subtitle">View your salary and payment history</div>
</header>
<section class="app-content">
<?php if (!$salary): ?>
<div class="hero-card">
<h2>No Salary Set</h2>
<p>Your salary has not been configured yet. Please contact the administrator.</p>
</div>
<?php else: ?>
<div class="stats-grid">
<div class="stat-card"><div class="label">Basic Salary</div><div class="value">R <?= number_format($salary["basic_salary"] ?? 0, 2) ?></div></div>
<div class="stat-card"><div class="label">Housing</div><div class="value">R <?= number_format($salary["housing_allowance"] ?? 0, 2) ?></div></div>
<div class="stat-card"><div class="label">Transport</div><div class="value">R <?= number_format($salary["transport_allowance"] ?? 0, 2) ?></div></div>
<div class="stat-card"><div class="label">Medical</div><div class="value">R <?= number_format($salary["medical_allowance"] ?? 0, 2) ?></div></div>
<div class="stat-card"><div class="label">Other Allowances</div><div class="value">R <?= number_format($salary["other_allowances"] ?? 0, 2) ?></div></div>
<div class="stat-card"><div class="label">Deductions</div><div class="value">R <?= number_format($salary["deductions"] ?? 0, 2) ?></div></div>
</div>
<div class="hero-card">
<?php
$basic = (float) ($salary["basic_salary"] ?? 0);
$housing = (float) ($salary["housing_allowance"] ?? 0);
$transport = (float) ($salary["transport_allowance"] ?? 0);
$medical = (float) ($salary["medical_allowance"] ?? 0);
$other = (float) ($salary["other_allowances"] ?? 0);
$deductions = (float) ($salary["deductions"] ?? 0);
$gross = $basic + $housing + $transport + $medical + $other;
$net = $gross - $deductions;
?>
<div style="display:flex;justify-content:space-between;align-items:center;">
<div><div class="label">Gross Salary</div><div class="value" style="font-size:28px;">R <?= number_format($gross, 2) ?></div></div>
<div><div class="label">Net Salary</div><div class="value" style="font-size:28px;color:#22c55e;">R <?= number_format($net, 2) ?></div></div>
</div>
<div style="margin-top:15px;color:var(--app-muted);">Effective from: <?= date("d M Y", strtotime($salary["effective_date"] ?? date("Y-m-1"))) ?></div>
</div>
<div class="panel">
<h2>Payment History</h2>
<?php if (empty($payments)): ?>
<p class="empty-state">No payments recorded yet.</p>
<?php else: ?>
<table class="data-table">
<thead><tr><th>Month</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th><th>Date</th></tr></thead>
<tbody><?php foreach ($payments as $p): ?>
<tr>
<td><?= htmlspecialchars($p["month"]) ?></td>
<td>R <?= number_format($p["gross_salary"] ?? 0, 2) ?></td>
<td>R <?= number_format($p["total_deductions"] ?? 0, 2) ?></td>
<td>R <?= number_format($p["net_salary"] ?? 0, 2) ?></td>
<td><span class="badge badge-<?= $p["status"] ?>"><?= htmlspecialchars($p["status"]) ?></span></td>
<td><?= date("d M Y", strtotime($p["payment_date"] ?? $p["created_at"])) ?></td>
</tr>
<?php endforeach; ?></tbody>
</table>
<?php endif; ?>
</div>
<?php endif; ?>
</section>
</main>
</div>
</body>
</html>