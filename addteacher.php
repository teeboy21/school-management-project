<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['admin', 'hr_manager'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Register Teacher</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="assets/toast.css">
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #0f172a, #1d4ed8);
    min-height: 100vh;
}
.container {
    width: min(720px, calc(100% - 32px));
    margin: 32px auto;
    background: #fff;
    padding: 28px;
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.28);
}
h2 { margin-top: 0; }
.grid {
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
}
.grid .full { grid-column: 1 / -1; }
input, select, textarea, button {
    width: 100%;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    box-sizing: border-box;
}
textarea { min-height: 110px; resize: vertical; }
.actions {
    display:flex;
    gap:12px;
    margin-top:16px;
}
button {
    border:none;
    font-weight:700;
    cursor:pointer;
}
.primary { background:#0ea5e9; color:white; }
.secondary { background:#e2e8f0; color:#0f172a; }
@media (max-width: 700px) {
    .grid { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="container">
    <h2>Register Teacher</h2>
    <p>Create a teacher account through the API and keep the dashboard consistent.</p>

    <form id="teacherForm">
        <div class="grid">
            <input type="text" name="fullname" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="phone" placeholder="Phone">
            <div>
                <label>Date of Birth</label>
                <input type="date" name="dob" required>
            </div>
            <input type="text" name="identitynumber" placeholder="Identity Number">
            <select name="race" required>
                <option value="">Select Race</option>
                <option value="black">Black</option>
                <option value="white">White</option>
                <option value="asian">Asian</option>
                <option value="other">Other</option>
            </select>
            <select name="gender" required>
                <option value="">Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>
            <div class="full">
                <textarea name="address" placeholder="Address"></textarea>
            </div>
            <div class="full">
                <label>Hire Date</label>
                <input type="date" name="hire_date" required>
            </div>
        </div>

        <div class="actions">
            <button class="primary" type="submit" id="submitBtn">Register Teacher</button>
            <button class="secondary" type="button" onclick="window.location.href='registerTeacher.php'">Manage Teachers</button>
        </div>
    </form>
</div>

<script src="assets/toast.js"></script>
<script>
const teacherForm = document.getElementById("teacherForm");
const submitBtn = document.getElementById("submitBtn");

teacherForm.addEventListener("submit", function (event) {
    event.preventDefault();
    submitBtn.disabled = true;
    submitBtn.textContent = "Saving...";

    const payload = Object.fromEntries(new FormData(teacherForm).entries());

    fetch("API/create_teacher.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(response => {
        if (response.status !== "success") {
            throw new Error(response.message || "Failed to register teacher");
        }
        teacherForm.reset();
        showToast(response.message, "success");
    })
    .catch(error => showToast(error.message, "error"))
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = "Register Teacher";
    });
});
</script>
</body>
</html>
