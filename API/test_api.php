<?php
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';
echo json_encode(["test" => "ok"]);
