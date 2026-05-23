<?php

header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

$action = $_GET['action'] ?? '';

$isAdmin = in_array($_SESSION['role'] ?? '', ['admin', 'principal']);

/* ================= GET EVENTS ================= */
if ($action === 'get') {

    $role = $_SESSION['role'] ?? 'guest';

    if ($isAdmin) {
        $sql = "SELECT * FROM events ORDER BY event_date ASC, event_time ASC";
    } else {
        $sql = "SELECT * FROM events 
                WHERE audience IN ('all', '$role') 
                AND status = 'published'
                ORDER BY event_date ASC, event_time ASC";
    }

    $result = $conn->query($sql);
    $events = [];

    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }

    echo json_encode($events);
    exit();
}

/* ================= SAVE ================= */
if ($action === 'save') {

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied"]);
        exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    $id = $data['id'] ?? null;
    $title = $data['title'];
    $date = $data['date'];
    $time = $data['time'];
    $audience = $data['audience'];
    $location = $data['location'];
    $description = $data['description'];

    if ($id) {
        // UPDATE
        $stmt = $conn->prepare("UPDATE events 
            SET title=?, event_date=?, event_time=?, audience=?, location=?, description=?, updated_at=NOW()
            WHERE id=?");

        $stmt->bind_param("ssssssi", $title, $date, $time, $audience, $location, $description, $id);
        $stmt->execute();

    } else {
        // INSERT
        $stmt = $conn->prepare("INSERT INTO events 
            (title, event_date, event_time, audience, location, description, status, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'published', ?, NOW())");

        $user_id = $_SESSION['user_id'];
        $stmt->bind_param("ssssssi", $title, $date, $time, $audience, $location, $description, $user_id);
        $stmt->execute();
    }

    echo json_encode(["status" => "success"]);
    exit();
}

/* ================= DELETE ================= */
if ($action === 'delete') {

    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Access denied"]);
        exit;
    }

    $id = $_GET['id'];

    $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    echo json_encode(["status" => "deleted"]);
    exit();
}