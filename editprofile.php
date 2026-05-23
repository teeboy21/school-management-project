<?php
session_start();
include 'config.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];

    $phone = $_POST['phone'];
    $kinphone = $_POST['kinphone'];
    $kinrelationship = $_POST['kinrelationship'];
    $address = $_POST['address'];



    // Update user status
    $conn->query("UPDATE student SET ='profile_completed' WHERE id=$user_id");

    header("Location: userprofile.php");
} else {
    
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit information</title>

</head>
<style>
    body {
        font-family: Arial;
        background: linear-gradient(to right, #3498db, #6dd5fa);
        display: flex;
        justify-content: center;
        align-items: center;

    }

    .form-container {
        background: white;
        padding: 30px;
        border-radius: 15px;
        width: 500px;
        margin-top: 20px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    h2 {
        text-align: center;
        margin-bottom: 20px;
    }

    input,
    select,
    textarea {
        width: 100%;
        padding: 10px;
        margin: 10px 0;
        border-radius: 5px;
        border: 1px solid #ccc;
    }

    button {
        width: 100%;
        padding: 10px;
        background: #2ecc71;
        border: none;
        color: white;
        font-size: 16px;
        cursor: pointer;
        border-radius: 5px;
        margin: 10px;
    }

    button:hover {
        background: #27ae60;
    }
</style>

<body>

    <div class="form-container">
        <h2>🎓 edit Your Profile</h2>

        <form method="POST" id="complete">



            <input type="text" name="phone" placeholder="Phone Number">
            <input type="text" name="kinphone" placeholder="Next of Kin Phone Number">
            <select name="kinrelationship">
                <option value="">Select Relationship</option>
                <option value="parent">Parent</option>
                <option value="guardian">Guardian</option>
                <option value="other">Other</option>
            </select>
            <textarea name="address" placeholder="Address"></textarea>

            <button id="submit">Submit Registration</button>
            <button><a href="userprofile.php">back to profile</a></button>

        </form>
    </div>

</body>

</html>