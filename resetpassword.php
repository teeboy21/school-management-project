<?php
include 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="external.css">
    <title>Reset Password</title>
</head>
<body>
    <div class="parent">
        <div class="child">
            <form method="POST">
                <h2>Reset Password</h2>
                <input type="text" name="username" placeholder="Username" required>
                <input type="text" name="favouratecar" placeholder="Favourate Car" required>
                <input type="password" name="new_password" placeholder="New Password" required>
                <p id="newpasswordverify"></h4>
                <button type="submit" name="reset" id="login">Reset Password</button>
                <h5><a href="login.html" >Back to Login</a></h5>
            </form>
        </div>
    </div>

</body>
</html>