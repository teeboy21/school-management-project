<?php
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Check if email exists
    $check = $conn->prepare("SELECT id FROM user WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        echo "Email already exists!";
        exit();
    }

    // Insert user
    $stmt = $conn->prepare("INSERT INTO user (email, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $email, $password);

    if ($stmt->execute()) {

        $user_id = $stmt->insert_id;

        // Assign student role (assume role_id = 3)
        $role_id = 1;
        $conn->query("INSERT INTO user_roles (user_id, role_id) VALUES ($user_id, $role_id)");

        echo "Registration successful. Please login.";
        header("Location: login.php");
        exit();
    } else {
        echo "Error occurred.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Settings</title>

<style>
body{font-family:Arial;background:#f1f5f9;padding:20px;}

.container{
    max-width:800px;
    margin:auto;
    background:white;
    padding:20px;
    border-radius:10px;
}

h2{margin-bottom:15px;}

input{
    width:100%;
    padding:10px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:5px;
}

button{
    padding:10px;
    background:#1e293b;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}
</style>
</head>

<body>

<div class="container">
     <li><a href="admindashboard.php">Dashboard</a></li>

<h2>Account Settings</h2>
<form method="post">
    <input type="text" placeholder="New Username" name="username">
    <input type="password" placeholder="New Password" name="password">
    <button type="submit">Update Account</button>
</form>

<h2>School Settings</h2>
<form method="post">
    <input type="text" placeholder="School Name" name="school">
    <input type="text" placeholder="Contact Email" name="email">
    <input type="file" name="logo">
    <button type="submit">Save Settings</button>
</form>
<h2>add admin</h2>
<form method="post">
    <input type="text" placeholder=" Email" name="email">
    <input type="password" placeholder="password" name="password">
    <input type="password" placeholder="confirm-password" name="confirm-password">
    <button type="submit">Save Admin</button>
</form>

</div>

</body>
</html>