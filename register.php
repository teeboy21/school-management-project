<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

include 'config.php';

$data = json_decode(file_get_contents("php://input"),true);


$email = $data['email'] ?? '';
$password = $data['password'] ?? '';

$stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
 if($result->num_rows > 0){
  echo json_encode([
        "status" => "error",
        "message" => " email already exist"
    ]);
   // echo json_encode($response);
    //exit();
 }

 $hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO user (email, password, status) VALUES (?, ?, 'account_created')");
$stmt->bind_param("ss", $email, $hashed_password);

if($stmt->execute()){
    echo json_encode( [
        "status" => "success",
        "message" => "Account created successfully"
    ]);
   // echo json_encode($response);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Failed to create account"
    ]);
    // echo json_encode($response);
}
?>