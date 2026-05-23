<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    header("Location: login.php");
    exit();
}

require_once 'system_check.php';

$stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = 'fees_module_enabled'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$fees_enabled = ($row && $row['setting_value'] === 'enabled');

$user_id = (int) $_SESSION['user_id'];

// Get student's internal id from students table
$stmt_student = $conn->prepare("SELECT id FROM students WHERE user_id = ?");
$stmt_student->bind_param("i", $user_id);
$stmt_student->execute();
$student_result = $stmt_student->get_result();
$student_row = $student_result->fetch_assoc();
$student_id = $student_row ? $student_row['id'] : 0;

$academic_year = date('Y') . '-' . (date('Y') + 1);

$stmt = $conn->prepare("SELECT sf.total_amount, sf.balance, sf.status, sf.due_date, 
                       COALESCE((SELECT SUM(amount) FROM payments WHERE student_id = sf.student_id), 0) as paid_amount,
                       fs.description,
                       fs.tuition_fee, fs.registration_fee, fs.exam_fee, fs.library_fee, fs.sports_fee, fs.other_fee, fs.transport_fee
                       FROM student_fees sf
                       LEFT JOIN fee_structures fs ON sf.fee_structure_id = fs.id
                       WHERE sf.student_id = ? AND sf.academic_year = ?");
$stmt->bind_param("is", $student_id, $academic_year);
$stmt->execute();
$fee_data = $stmt->get_result()->fetch_assoc();

$stmt2 = $conn->prepare("SELECT p.* FROM payments p WHERE p.student_id = ? ORDER BY p.payment_date DESC");
$stmt2->bind_param("i", $student_id);
$stmt2->execute();
$payments = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fees</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <a href="userdashboard.php" class="btn btn-ghost btn-small" style="text-decoration:none;flex-shrink:0">← Dashboard</a>
            <div class="app-header-meta">
                <div class="app-header-title">My Fees</div>
                <div class="app-header-subtitle">View your fee balance and payment history.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <?php if (!$fees_enabled): ?>
                <div class="hero-card" style="background:#fee2e2;border-color:#ef4444;">
                    <h2>Fees Module Disabled</h2>
                    <p>The fees module is currently disabled by the administrator.</p>
                </div>
            <?php elseif (!$fee_data): ?>
                <div class="hero-card">
                    <h2>No Fee Record</h2>
                    <p>You don't have a fee record for the current academic year yet. Please contact the school office if you believe this is an error.</p>
                </div>
            <?php else: ?>
                <div class="cards-grid" style="margin-bottom:20px;">
                    <div class="stat-card">
                        <div>Total Amount</div>
                        <strong>R <?= number_format($fee_data['total_amount'], 2) ?></strong>
                    </div>
                    <div class="stat-card">
                        <div>Amount Paid</div>
                        <strong>R <?= number_format($fee_data['paid_amount'] ?? 0, 2) ?></strong>
                    </div>
                    <div class="stat-card">
                        <div>Balance</div>
                        <strong style="color: <?= $fee_data['balance'] > 0 ? '#ef4444' : '#22c55e' ?>">
                            R <?= number_format($fee_data['balance'], 2) ?>
                        </strong>
                    </div>
                    <div class="stat-card">
                        <div>Due Date</div>
                        <strong><?= date('d M Y', strtotime($fee_data['due_date'] ?? '2026-12-31')) ?></strong>
                    </div>
                </div>

                <div class="cards-grid" style="margin-bottom:20px;">
                    <div class="stat-card" style="flex-basis:100%;">
                        <div>Payment Status</div>
                        <strong>
                            <span class="badge badge-<?= $fee_data['status'] ?>">
                                <?= strtoupper($fee_data['status']) ?>
                            </span>
                        </strong>
                    </div>
                </div>

                <div class="panel">
                    <h2>Fee Breakdown</h2>
                    <?php if ($fee_data['description']): ?>
                        <p style="margin-bottom:15px;"><?= htmlspecialchars($fee_data['description']) ?></p>
                    <?php endif; ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Fee Type</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>Tuition Fee</td><td>R <?= number_format($fee_data['tuition_fee'] ?? 0, 2) ?></td></tr>
                                <tr><td>Registration Fee</td><td>R <?= number_format($fee_data['registration_fee'] ?? 0, 2) ?></td></tr>
                                <tr><td>Exam Fee</td><td>R <?= number_format($fee_data['exam_fee'] ?? 0, 2) ?></td></tr>
                                <tr><td>Library Fee</td><td>R <?= number_format($fee_data['library_fee'] ?? 0, 2) ?></td></tr>
                                <tr><td>Sports Fee</td><td>R <?= number_format($fee_data['sports_fee'] ?? 0, 2) ?></td></tr>
                                <tr><td>Transport Fee</td><td>R <?= number_format($fee_data['transport_fee'] ?? 0, 2) ?></td></tr>
                                <tr><td>Other Fee</td><td>R <?= number_format($fee_data['other_fee'] ?? 0, 2) ?></td></tr>
                                <tr style="font-weight:bold;background:#f1f5f9;"><td>Total</td><td>R <?= number_format($fee_data['total_amount'], 2) ?></td></tr>
                                <tr style="font-weight:bold;background:#dcfce7;"><td>Amount Paid</td><td>R <?= number_format($fee_data['paid_amount'], 2) ?></td></tr>
                                <tr style="font-weight:bold;<?= $fee_data['balance'] > 0 ? 'background:#fee2e2;' : 'background:#dcfce7;' ?>"><td>Balance Due</td><td>R <?= number_format($fee_data['balance'], 2) ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin-top:15px;color:var(--app-muted);">Please contact the school office for fee payment arrangements or to arrange a payment plan.</p>
                </div>

                <div class="panel" style="margin-top:20px;">
                    <h2>Payment History</h2>
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">No payments recorded yet.</div>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Reference</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($p['payment_date'])) ?></td>
                                            <td>R <?= number_format($p['amount'], 2) ?></td>
                                            <td><?= ucfirst(str_replace('_', ' ', $p['payment_method'])) ?></td>
                                            <td><?= $p['reference_number'] ?: '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>