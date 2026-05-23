document.getElementById("signupform").addEventListener("submit", function(e){
  e.preventDefault();

  const password = document.getElementById("password").value;
  const confirmPassword = document.getElementById("confirmpassword").value;
  const message = document.getElementById("confirmpasswordError");
  const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/;
  const termsChecked = document.getElementById("termsCheck").checked;

  if (password !== confirmPassword) {
    message.innerText = "Passwords do not match.";
    showToast("Passwords do not match.", "error");
    return;
  }

  if (!strongPassword.test(password)) {
    message.innerText = "Use 8+ chars with uppercase, lowercase, number, and symbol.";
    showToast("Use 8+ chars with uppercase, lowercase, number, and symbol.", "error");
    return;
  }

  if (!termsChecked) {
    message.innerText = "You must accept the Terms and Conditions before registration.";
    showToast("You must accept the Terms and Conditions before registration.", "error");
    return;
  }

  const btn = document.getElementById("login");
  btn.disabled = true;
  btn.innerText = "creating...";

  const data = {
    email: document.getElementById("email").value,
    password: password,
  };

  fetch("API/signup.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify(data)
  })
  .then(res => {
    if (!res.ok) throw new Error("Server error: " + res.status);
    return res.json();
  })
  .then(response => {
    message.innerText = response.message;
    showToast(response.message, response.status === "success" ? "success" : "error");

    if(response.status === "success"){
      setTimeout(() => {
        window.location.href = "login.html";
      }, 1500);
    }

    btn.disabled = false;
    btn.innerText = "signup";
  })
  .catch((error) => {
    message.innerText = "An error occurred. Please try again. " + error;
    showToast("Registration failed. " + error, "error");
    btn.disabled = false;
    btn.innerText = "signup";
  });
});
