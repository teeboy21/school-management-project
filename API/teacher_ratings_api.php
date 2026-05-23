<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

function ratings_enabled($conn) {
    $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'teacher_ratings_enabled'");
    $row = $r->fetch_assoc();
    return ($row['setting_value'] ?? 'off') === 'on';
}

// Student: get teachers they can rate (from their approved subjects)
if ($action === 'get_my_teachers') {
    if ($role !== 'student') { echo json_encode(["error" => "Access denied"]); exit; }
    $result = $conn->query("SELECT DISTINCT t.user_id as teacher_id, t.fullname, sub.subject_name, sub.id as subject_id
        FROM student_subject ss
        JOIN teacher_subject ts ON ts.subject_id = ss.subject_id
        JOIN teachers t ON t.user_id = ts.teacher_id
        JOIN subjects sub ON sub.id = ss.subject_id
        WHERE ss.student_id = $user_id AND ss.status = 'approved'
        ORDER BY t.fullname");
    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $rated = $conn->query("SELECT id, rating, comment FROM teacher_ratings WHERE teacher_id = {$row['teacher_id']} AND student_id = $user_id")->fetch_assoc();
        $row['rated'] = $rated ? true : false;
        $row['my_rating'] = $rated ? (int)$rated['rating'] : null;
        $row['my_comment'] = $rated ? $rated['comment'] : null;
        $teachers[] = $row;
    }
    echo json_encode($teachers);
    exit;
}

// Student: submit rating
if ($action === 'submit_rating') {
    if ($role !== 'student') { echo json_encode(["error" => "Access denied"]); exit; }
    if (!ratings_enabled($conn)) { echo json_encode(["error" => "Teacher ratings are currently disabled"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $teacher_id = (int)($data['teacher_id'] ?? 0);
    $rating = (int)($data['rating'] ?? 0);
    $comment = $conn->real_escape_string($data['comment'] ?? '');
    if ($rating < 1 || $rating > 5) { echo json_encode(["error" => "Rating must be between 1 and 5"]); exit; }
    $check = $conn->query("SELECT id FROM teacher_ratings WHERE teacher_id = $teacher_id AND student_id = $user_id");
    if ($check && $check->num_rows > 0) {
        $conn->query("UPDATE teacher_ratings SET rating = $rating, comment = '$comment' WHERE teacher_id = $teacher_id AND student_id = $user_id");
        echo json_encode(["status" => "success", "message" => "Rating updated"]);
    } else {
        $conn->query("INSERT INTO teacher_ratings (teacher_id, student_id, rating, comment) VALUES ($teacher_id, $user_id, $rating, '$comment')");
        echo json_encode(["status" => "success", "message" => "Rating submitted"]);
    }
    exit;
}

// HR / Principal: get aggregated ratings by teacher
if ($action === 'get_ratings') {
    if (!in_array($role, ['hr_manager', 'principal', 'admin'])) { echo json_encode(["error" => "Access denied"]); exit; }
    $result = $conn->query("SELECT t.user_id as teacher_id, t.fullname,
        COUNT(tr.id) as rating_count,
        ROUND(AVG(tr.rating), 1) as avg_rating,
        ROUND(AVG(CASE WHEN tr.rating >= 4 THEN 1 ELSE 0 END) * 100, 0) as satisfaction_pct
        FROM teachers t
        LEFT JOIN teacher_ratings tr ON tr.teacher_id = t.user_id
        GROUP BY t.user_id
        ORDER BY avg_rating DESC");
    $ratings = [];
    while ($row = $result->fetch_assoc()) {
        $distribution = $conn->query("SELECT rating, COUNT(*) as cnt FROM teacher_ratings WHERE teacher_id = {$row['teacher_id']} GROUP BY rating ORDER BY rating");
        $dist = [1=>0,2=>0,3=>0,4=>0,5=>0];
        while ($d = $distribution->fetch_assoc()) $dist[(int)$d['rating']] = (int)$d['cnt'];
        $row['distribution'] = $dist;
        $ratings[] = $row;
    }
    echo json_encode($ratings);
    exit;
}

// Check if feature is enabled
if ($action === 'check_enabled') {
    echo json_encode(["enabled" => ratings_enabled($conn)]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);
