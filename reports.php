<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager', 'finance_manager', 'principal'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reports</title>
<link rel="stylesheet" href="internal.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Reports</div>
                <div class="app-header-subtitle">A clearer landing page for reporting areas and future exports.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="cards-grid">
                <div class="quick-card"><div>Student Performance</div><strong>Ready</strong><p>Use this area for academic summaries and printable reports.</p></div>
                <div class="quick-card"><div>Finance Summary</div><strong>Ready</strong><p>Use this area for fee, payment, and expense summaries.</p></div>
                <div class="quick-card"><div>Attendance Reports</div><strong>Planned</strong><p>Prepare attendance exports once attendance tracking is added.</p></div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
