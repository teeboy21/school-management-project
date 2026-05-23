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
    <title>Complete Registration</title>
    <link rel="stylesheet" href="assets/toast.css">
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
        box-sizing: border-box;
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
        margin: 10px 0;
    }

    button:hover {
        background: #27ae60;
    }

    .message {
        min-height: 24px;
        margin-top: 10px;
        text-align: center;
        font-weight: 600;
    }
</style>

<body>

    <div class="form-container">
        <h2>Complete Your Profile</h2>

        <form id="complete">
            <input type="text" name="fullnames" placeholder="Full Name" required>
            <input type="text" name="surname" placeholder="Surname" required>
            <select name="race" required>
                <option value="">Select Race</option>
                <option value="white">White</option>
                <option value="black">Black</option>
                <option value="asian">Asian</option>
                <option value="other">Other</option>
            </select>
            <input type="date" name="dob" required>

            <select name="gender" required>
                <option value="">Select Gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
            </select>

            <input type="text" name="phone" placeholder="Phone Number">
            <input type="text" name="kinphone" placeholder="Next of Kin Phone Number">
            <select name="kinrelationship">
                <option value="">Select Relationship</option>
                <option value="parent">Parent</option>
                <option value="guardian">Guardian</option>
                <option value="other">Other</option>
            </select>
            <input type="text" name="identification" placeholder="Identification Number/Passport">
<select name="grade_applying" id="gradeSelect">
    <option value="">Select Grade</option>
    <?php
    $grades_query = $conn->query("SELECT id, grade_name FROM grades ORDER BY grade_name");
    if ($grades_query) {
        while ($g = $grades_query->fetch_assoc()) {
            echo '<option value="' . htmlspecialchars($g['grade_name']) . '">' . htmlspecialchars($g['grade_name']) . '</option>';
        }
    }
    ?>
</select>

            <textarea name="address" placeholder="Address"></textarea>

            <button type="submit" id="submit">Submit Registration</button>
            <button type="button" onclick="window.location.href='login.html'">Exit Registration</button>
        </form>
        <p id="formMessage" class="message"></p>
    </div>

</body>

<script src="assets/toast.js"></script>
<script>
const completeForm = document.getElementById("complete");
const submitBtn = document.getElementById("submit");
const formMessage = document.getElementById("formMessage");

completeForm.addEventListener("submit", function (event) {
    event.preventDefault();

    submitBtn.disabled = true;
    submitBtn.textContent = "Submitting...";
    formMessage.textContent = "";

    const formData = new FormData(completeForm);
    const payload = {
        fullnames: formData.get("fullnames"),
        surname: formData.get("surname"),
        race: formData.get("race"),
        dob: formData.get("dob"),
        gender: formData.get("gender"),
        phone: formData.get("phone"),
        kinphone: formData.get("kinphone"),
        kinrelationship: formData.get("kinrelationship"),
        identification: formData.get("identification"),
        grade_applying: formData.get("grade_applying"),
        address: formData.get("address")
    };

    fetch("API/studentcomplete.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(response => {
        formMessage.textContent = response.message;
        formMessage.style.color = response.status === "success" ? "green" : "red";
        showToast(response.message, response.status === "success" ? "success" : "error");

        if (response.status === "success") {
            setTimeout(() => {
                window.location.href = "login.html";
            }, 1200);
        }
    })
    .catch(error => {
        formMessage.textContent = "Failed to submit registration. " + error;
        formMessage.style.color = "red";
        showToast("Failed to submit registration. " + error, "error");
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = "Submit Registration";
    });
});
</script>

</html>
