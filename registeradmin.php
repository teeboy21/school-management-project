<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Admin</title>
<link rel="stylesheet" href="internal.css">
<link rel="stylesheet" href="assets/toast.css">
</head>
<body>
<div class="app-shell">
<main class="app-main">
        <header class="app-header">
            <div class="app-header-meta">
                <div class="app-header-title">Register Another Admin</div>
                <div class="app-header-subtitle">Create admin accounts for other staff members.</div>
            </div>
            <div class="app-user">
                <div class="app-user-name"><?= htmlspecialchars($_SESSION['fullname'] ?? 'Administrator') ?></div>
                <div class="app-user-email"><?= htmlspecialchars($_SESSION['email'] ?? '') ?></div>
            </div>
        </header>
        <section class="app-content">
            <div class="panel">
                <form id="registerAdminForm" class="form-grid">
                    <div>
                        <label for="admin_fullname">Full Name</label>
                        <input type="text" id="admin_fullname" name="admin_fullname" placeholder="Administrator full name" required>
                    </div>
                    <div>
                        <label for="admin_email">Email</label>
                        <input type="email" id="admin_email" name="admin_email" placeholder="admin@example.com" required>
                    </div>
                    <div>
                        <label for="admin_password">Password</label>
                        <input type="password" id="admin_password" name="admin_password" placeholder="8+ chars, uppercase, lowercase, number, symbol" required>
                    </div>
                    <div>
                        <label for="admin_confirm_password">Confirm Password</label>
                        <input type="password" id="admin_confirm_password" name="admin_confirm_password" placeholder="Repeat password" required>
                    </div>
                    <div class="full">
                        <label for="admin_access_level">Access Level</label>
                        <select id="admin_access_level" name="admin_access_level">
                            <option value="full_admin">Full Admin</option>
                            <option value="academic_admin">Academic Admin</option>
                            <option value="operations_admin">Operations Admin</option>
                            <option value="finance_admin">Finance Admin</option>
                        </select>
                    </div>
                    <div class="page-actions">
                        <button type="submit" class="btn btn-primary">Create Admin Account</button>
                        <a href="admindashboard.php" class="btn btn-secondary">Back To Dashboard</a>
                    </div>
                </form>
            </div>
        </section>
    </main>
</div>
<script src="assets/toast.js"></script>
<script>
document.getElementById('registerAdminForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const password = document.getElementById('admin_password').value;
    const confirmPassword = document.getElementById('admin_confirm_password').value;
    
    if (password !== confirmPassword) {
        Toast.show('Passwords do not match', 'error');
        return;
    }
    
    const data = {
        email: document.getElementById('admin_email').value,
        password: password,
        fullname: document.getElementById('admin_fullname').value,
        access_level: document.getElementById('admin_access_level').value
    };
    
    try {
        const res = await fetch('API/register_admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await res.json();
        
        if (result.status === 'success') {
            Toast.show(result.message, 'success');
            document.getElementById('registerAdminForm').reset();
        } else {
            Toast.show(result.message, 'error');
        }
    } catch (err) {
        Toast.show('Failed to create admin account', 'error');
    }
});
</script>
</body>
</html>