<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Registration</title>
    <link rel="stylesheet" href="external.css?v=4">
    <link rel="stylesheet" href="assets/toast.css">
</head>
<body class="public-page">
    <div class="parent">
        <div class="form-container">
            <h2>Complete Your Profile</h2>
            <p class="auth-subtitle">Fill in your details to complete your student registration.</p>

            <form id="complete">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px">
                    <div>
                        <label>Full Name</label>
                        <input type="text" name="fullnames" required placeholder="e.g. John">
                    </div>
                    <div>
                        <label>Surname</label>
                        <input type="text" name="surname" required placeholder="e.g. Doe">
                    </div>
                    <div>
                        <label>Race</label>
                        <select name="race" required>
                            <option value="">Select Race</option>
                            <option value="white">White</option>
                            <option value="black">Black</option>
                            <option value="asian">Asian</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label>Gender</label>
                        <select name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label>Date of Birth</label>
                        <input type="date" name="dob" required>
                    </div>
                    <div>
                        <label>Grade Applying For</label>
                        <select name="grade_applying" required>
                            <option value="">Select Grade</option>
                            <?php
                            $grades_query = $conn->query("SELECT id, name FROM grades ORDER BY name");
                            if ($grades_query) {
                                while ($g = $grades_query->fetch_assoc()) {
                                    echo '<option value="' . (int)$g['id'] . '">' . htmlspecialchars($g['name']) . '</option>';
                                }
                            } else {
                                echo '<option value="">No grades available</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="+27 XX XXX XXXX">

                <label>Next of Kin Phone</label>
                <input type="text" name="kinphone" placeholder="Next of kin phone number">

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:0 16px">
                    <div>
                        <label>Kin Relationship</label>
                        <select name="kinrelationship">
                            <option value="">Select Relationship</option>
                            <option value="parent">Parent</option>
                            <option value="guardian">Guardian</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label>ID / Passport</label>
                        <input type="text" name="identification" placeholder="ID or passport number">
                    </div>
                </div>

                <label>Address</label>
                <textarea name="address" placeholder="Street, City, Province"></textarea>

                <div class="auth-actions">
                    <button type="submit" id="submit">Submit Registration</button>
                </div>
                <button type="button" class="secondary-link-btn" onclick="window.location.href='login.html'" style="margin-top:10px">Exit Registration</button>
            </form>
            <p id="formMessage" class="message"></p>
        </div>
    </div>

    <script src="assets/toast.js"></script>
    <script>
    document.getElementById("complete").addEventListener("submit", function (event) {
        event.preventDefault();
        const btn = document.getElementById("submit");
        const msg = document.getElementById("formMessage");
        btn.disabled = true;
        btn.textContent = "Submitting...";
        msg.textContent = "";

        const fd = new FormData(this);
        const payload = {
            fullnames: fd.get("fullnames"),
            surname: fd.get("surname"),
            race: fd.get("race"),
            dob: fd.get("dob"),
            gender: fd.get("gender"),
            phone: fd.get("phone"),
            kinphone: fd.get("kinphone"),
            kinrelationship: fd.get("kinrelationship"),
            identification: fd.get("identification"),
            grade_applying: fd.get("grade_applying"),
            address: fd.get("address")
        };

        fetch("API/studentcomplete.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(response => {
            msg.textContent = response.message;
            msg.style.color = response.status === "success" ? "green" : "red";
            showToast(response.message, response.status === "success" ? "success" : "error");
            if (response.status === "success") {
                setTimeout(() => { window.location.href = "login.html"; }, 1200);
            }
        })
        .catch(error => {
            msg.textContent = "Failed to submit. " + error;
            msg.style.color = "red";
            showToast("Failed to submit registration. " + error, "error");
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = "Submit Registration";
        });
    });
    </script>
</body>
</html>
