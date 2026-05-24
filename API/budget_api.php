<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Content-Type: application/json");
session_start();
require __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$action = $_GET['action'] ?? '';
$user_id = (int) $_SESSION['user_id'];

if ($action === 'submit_budget') {
    $data = json_decode(file_get_contents("php://input"), true);
    $budget_id = (int) ($data['budget_id'] ?? 0);

    $budget = $conn->query("SELECT * FROM budgets WHERE id = $budget_id")->fetch_assoc();
    if (!$budget) {
        echo json_encode(["status" => "error", "message" => "Budget not found"]);
        exit;
    }
    if ($budget['status'] !== 'draft' && $budget['status'] !== 'pending_approval') {
        echo json_encode(["status" => "error", "message" => "Budget is not in draft status"]);
        exit;
    }

    // Route through approval system
    $ch = curl_init();
    $payload = json_encode([
        'reference_type' => 'budget',
        'reference_id' => $budget_id,
        'amount' => (float)$budget['allocated_amount'],
        'notes' => 'Budget approval: ' . $budget['category'] . ' (' . $budget['fiscal_year'] . ')'
    ]);
    curl_close($ch);

    // Use internal HTTP call or direct function
    $_payload = json_decode($payload, true);
    $amount = (float)$budget['allocated_amount'];
    $ref_type = 'budget';
    $ref_id = $budget_id;
    $notes = $conn->real_escape_string('Budget approval: ' . $budget['category'] . ' (' . $budget['fiscal_year'] . ')');

    // Check existing request
    $existing = $conn->query("SELECT id FROM approval_requests WHERE reference_type = '$ref_type' AND reference_id = $ref_id AND status NOT IN ('cancelled','rejected')");
    if ($existing && $existing->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "Already submitted for approval"]);
        exit;
    }

    $workflow = $conn->query("SELECT id FROM approval_workflows WHERE module = 'budget' AND is_active = 1 LIMIT 1");
    if (!$workflow || !($wf = $workflow->fetch_assoc())) {
        echo json_encode(["status" => "error", "message" => "No budget approval workflow configured"]);
        exit;
    }
    $wf_id = $wf['id'];

    $steps = $conn->query("SELECT * FROM approval_step_definitions WHERE workflow_id = $wf_id AND $amount >= min_amount AND $amount <= max_amount ORDER BY step_order LIMIT 1");
    if (!$steps || !($step = $steps->fetch_assoc())) {
        echo json_encode(["status" => "error", "message" => "No approval step matches this amount"]);
        exit;
    }

    $conn->query("INSERT INTO approval_requests (module, reference_type, reference_id, workflow_id, current_step, amount, status, requested_by, notes)
                  VALUES ('budget', '$ref_type', $ref_id, $wf_id, {$step['id']}, $amount, 'pending_approval', $user_id, '$notes')");
    $req_id = $conn->insert_id;

    $conn->query("UPDATE budgets SET status = 'pending_approval', approval_id = $req_id WHERE id = $budget_id");

    echo json_encode(["status" => "success", "message" => "Budget submitted for approval", "request_id" => $req_id]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);
