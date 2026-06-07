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
$user_role = $_SESSION['role'] ?? '';
$fullname = $_SESSION['fullname'] ?? 'Unknown';
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// ===== HELPERS =====
function get_approval_status_badge($status) {
    $map = [
        'draft' => 'badge-pending',
        'pending_approval' => 'badge-pending',
        'partially_approved' => 'badge-partial',
        'approved' => 'badge-paid',
        'rejected' => 'badge-expired',
        'cancelled' => 'badge-terminated',
    ];
    return $map[$status] ?? 'badge-pending';
}

function user_has_role($conn, $user_id, $role) {
    $r = $conn->query("SELECT role FROM user WHERE id = $user_id");
    if ($row = $r->fetch_assoc()) {
        return $row['role'] === $role;
    }
    return false;
}

function get_user_role($conn, $user_id) {
    $r = $conn->query("SELECT role FROM user WHERE id = $user_id");
    if ($row = $r->fetch_assoc()) return $row['role'];
    return null;
}

// Get the right workflow for a module and amount
function find_workflow($conn, $module, $amount) {
    $module = $conn->real_escape_string($module);
    $wf = $conn->query("SELECT id FROM approval_workflows WHERE module = '$module' AND is_active = 1 LIMIT 1");
    if ($wf && ($w = $wf->fetch_assoc())) {
        return $w['id'];
    }
    return null;
}

// Get required steps for a workflow that apply to this amount
function get_applicable_steps($conn, $workflow_id, $amount) {
    $steps = [];
    $r = $conn->query("SELECT * FROM approval_step_definitions
                       WHERE workflow_id = $workflow_id
                       AND $amount >= min_amount AND $amount <= max_amount
                       ORDER BY step_order");
    while ($row = $r->fetch_assoc()) {
        $steps[] = $row;
    }
    return $steps;
}

// Update the referenced item's status based on approval state
function update_reference_status($conn, $ref_type, $ref_id, $approval_status) {
    $status_map = [
        'draft' => 'draft',
        'pending_approval' => 'pending_approval',
        'partially_approved' => 'pending_approval',
        'approved' => 'approved',
        'rejected' => 'rejected',
        'cancelled' => 'cancelled',
    ];
    $new_status = $status_map[$approval_status] ?? 'draft';

    switch ($ref_type) {
        case 'expense':
            $conn->query("UPDATE expenses SET status = '$new_status' WHERE id = $ref_id");
            break;
        case 'budget':
            $conn->query("UPDATE budgets SET status = '$new_status' WHERE id = $ref_id");
            break;
        case 'contract':
            $conn->query("UPDATE supplier_contracts SET status = '$new_status' WHERE id = $ref_id");
            break;
        case 'salary_change':
            $conn->query("UPDATE employee_salaries SET is_active = 0 WHERE employee_id = (SELECT employee_id FROM employee_salaries WHERE id = $ref_id)");
            $conn->query("UPDATE employee_salaries SET is_active = 1 WHERE id = $ref_id");
            break;
        case 'payroll':
            $conn->query("UPDATE salary_payments SET status = '$new_status' WHERE id = $ref_id");
            break;
    }
}

// ===== 1. SUBMIT FOR APPROVAL =====
if ($action === 'submit_for_approval') {
    $data = json_decode(file_get_contents("php://input"), true);
    $ref_type = $conn->real_escape_string($data['reference_type']); // expense, budget, contract, salary_change, payroll
    $ref_id = (int) $data['reference_id'];
    $notes = $conn->real_escape_string($data['notes'] ?? '');
    $amount = (float) ($data['amount'] ?? 0);

    // Prevent duplicates
    $existing = $conn->query("SELECT id, status FROM approval_requests WHERE reference_type = '$ref_type' AND reference_id = $ref_id AND status NOT IN ('cancelled','rejected')");
    if ($existing && $existing->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "An active approval request already exists for this item"]);
        exit;
    }

    $workflow_id = find_workflow($conn, $ref_type, $amount);
    if (!$workflow_id) {
        if ($ref_type === 'payroll') {
            $conn->query("INSERT INTO approval_requests (module, reference_type, reference_id, workflow_id, amount, status, requested_by, notes)
                          VALUES ('$ref_type', '$ref_type', $ref_id, 0, $amount, 'approved', $user_id, '$notes')");
            $req_id = $conn->insert_id;
            update_reference_status($conn, $ref_type, $ref_id, 'approved');
            log_audit($conn, $user_id, 'auto_approved', 'approval', $ref_type, $ref_id, ['request_id' => $req_id, 'reason' => 'Payroll auto-approved']);
            echo json_encode(["status" => "success", "message" => "Payroll approved", "request_id" => $req_id]);
            exit;
        }
        echo json_encode(["status" => "error", "message" => "No approval workflow configured for $ref_type"]);
        exit;
    }

    $steps = get_applicable_steps($conn, $workflow_id, $amount);
    if (empty($steps)) {
        // Auto-approve if no steps match (amount below threshold)
        $conn->query("INSERT INTO approval_requests (module, reference_type, reference_id, workflow_id, amount, status, requested_by, notes)
                      VALUES ('$ref_type', '$ref_type', $ref_id, $workflow_id, $amount, 'approved', $user_id, '$notes')");
        $req_id = $conn->insert_id;
        update_reference_status($conn, $ref_type, $ref_id, 'approved');
        log_audit($conn, $user_id, 'auto_approved', 'approval', $ref_type, $ref_id, ['request_id' => $req_id, 'reason' => 'Below approval threshold']);
        echo json_encode(["status" => "success", "message" => "Auto-approved (below approval threshold)", "request_id" => $req_id]);
        exit;
    }

    $first_step = $steps[0];
    $conn->query("INSERT INTO approval_requests (module, reference_type, reference_id, workflow_id, current_step, amount, status, requested_by, notes)
                  VALUES ('$ref_type', '$ref_type', $ref_id, $workflow_id, {$first_step['id']}, $amount, 'pending_approval', $user_id, '$notes')");
    $req_id = $conn->insert_id;

    // Update the reference status
    update_reference_status($conn, $ref_type, $ref_id, 'pending_approval');

    // Link approval_id back to the reference
    $conn->query("UPDATE approval_requests SET id = id WHERE id = $req_id"); // no-op

    log_audit($conn, $user_id, 'submitted_for_approval', 'approval', $ref_type, $ref_id, ['request_id' => $req_id, 'amount' => $amount]);

    echo json_encode(["status" => "success", "message" => "Submitted for approval", "request_id" => $req_id]);
    exit;
}

// ===== 2. GET PENDING APPROVALS FOR CURRENT USER =====
if ($action === 'get_pending_approvals') {
    $role = get_user_role($conn, $user_id);
    $limit = (int) ($_GET['limit'] ?? 50);
    $module = $conn->real_escape_string($_GET['module'] ?? '');

    $module_filter = $module ? "AND ar.module = '$module'" : '';

    // Find requests where current user's role can approve at the current step
    $sql = "SELECT ar.*, asd.role_required, asd.label as step_label, asd.step_order,
                   COALESCE(t.fullname, e.fullname, s.fullname) as requester_name
            FROM approval_requests ar
            JOIN approval_step_definitions asd ON ar.current_step = asd.id
            LEFT JOIN user u ON ar.requested_by = u.id
            LEFT JOIN teachers t ON t.user_id = u.id
            LEFT JOIN employees e ON e.user_id = u.id
            LEFT JOIN students s ON s.user_id = u.id
            WHERE ar.status = 'pending_approval'
            AND asd.role_required = '$role'
            $module_filter
            ORDER BY ar.created_at DESC
            LIMIT $limit";

    $result = $conn->query($sql);
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        // Count total steps for this workflow/amount
        $step_count = $conn->query("SELECT COUNT(*) as c FROM approval_step_definitions
                                    WHERE workflow_id = {$row['workflow_id']}
                                    AND {$row['amount']} >= min_amount AND {$row['amount']} <= max_amount")->fetch_assoc()['c'];
        // Count how many already approved
        $approved_count = $conn->query("SELECT COUNT(*) as c FROM approval_actions
                                        WHERE request_id = {$row['id']} AND action = 'approved'")->fetch_assoc()['c'];
        $row['step_total'] = (int)$step_count;
        $row['step_progress'] = (int)$approved_count;

        // Fetch reference details
        $ref = null;
        switch ($row['reference_type']) {
            case 'expense':
                $r = $conn->query("SELECT id, category, description, amount, expense_date, vendor_name FROM expenses WHERE id = {$row['reference_id']}");
                if ($r) $ref = $r->fetch_assoc();
                break;
            case 'budget':
                $r = $conn->query("SELECT id, category, allocated_amount as amount, fiscal_year FROM budgets WHERE id = {$row['reference_id']}");
                if ($r) $ref = $r->fetch_assoc();
                break;
            case 'contract':
                $r = $conn->query("SELECT id, supplier_name, contract_value as amount, contract_type, status FROM supplier_contracts WHERE id = {$row['reference_id']}");
                if ($r) $ref = $r->fetch_assoc();
                break;
            case 'payroll':
                $r = $conn->query("SELECT sp.id, sp.month, sp.net_salary as amount, sp.status, COALESCE(t.fullname, e.fullname) as employee_name
                                   FROM salary_payments sp
                                   LEFT JOIN teachers t ON sp.employee_id = t.user_id
                                   LEFT JOIN employees e ON sp.employee_id = e.user_id
                                   WHERE sp.id = {$row['reference_id']}");
                if ($r) $ref = $r->fetch_assoc();
                break;
        }
        $row['reference'] = $ref;
        $requests[] = $row;
    }
    echo json_encode($requests);
    exit;
}

// ===== 3. TAKE ACTION (approve/reject/return) =====
if ($action === 'take_action') {
    $data = json_decode(file_get_contents("php://input"), true);
    $request_id = (int) ($data['request_id'] ?? 0);
    $action_taken = $conn->real_escape_string($data['action']); // approved, rejected, returned
    $comment = $conn->real_escape_string($data['comment'] ?? '');

    // Get the request
    $req = $conn->query("SELECT ar.*, asd.role_required, asd.step_order, asd.id as step_def_id
                         FROM approval_requests ar
                         JOIN approval_step_definitions asd ON ar.current_step = asd.id
                         WHERE ar.id = $request_id")->fetch_assoc();
    if (!$req) {
        echo json_encode(["status" => "error", "message" => "Request not found"]);
        exit;
    }

    // Verify user has the right role
    $role = get_user_role($conn, $user_id);
    if ($role !== $req['role_required'] && $role !== 'admin') {
        echo json_encode(["status" => "error", "message" => "You don't have permission to act on this request"]);
        exit;
    }

    // Check for duplicate action
    $dup = $conn->query("SELECT id FROM approval_actions WHERE request_id = $request_id AND actor_id = $user_id AND action = 'approved'");
    if ($dup && $dup->num_rows > 0) {
        echo json_encode(["status" => "error", "message" => "You have already approved this request"]);
        exit;
    }

    // Record the action
    $conn->query("INSERT INTO approval_actions (request_id, actor_id, step_definition_id, action, comment)
                  VALUES ($request_id, $user_id, {$req['step_def_id']}, '$action_taken', '$comment')");

    if ($action_taken === 'rejected' || $action_taken === 'returned') {
        // Rejection/return ends the flow
        $new_status = ($action_taken === 'rejected') ? 'rejected' : 'draft';
        $conn->query("UPDATE approval_requests SET status = '$new_status' WHERE id = $request_id");
        update_reference_status($conn, $req['reference_type'], $req['reference_id'], $new_status);
        log_audit($conn, $user_id, $action_taken, 'approval', $req['reference_type'], $req['reference_id'], ['request_id' => $request_id, 'comment' => $comment]);
        echo json_encode(["status" => "success", "message" => "Request has been " . $action_taken]);
        exit;
    }

    // Action is 'approved' - check if there are more steps
    $next_steps = $conn->query("SELECT * FROM approval_step_definitions
                                WHERE workflow_id = {$req['workflow_id']}
                                AND step_order > {$req['step_order']}
                                AND {$req['amount']} >= min_amount AND {$req['amount']} <= max_amount
                                ORDER BY step_order LIMIT 1");

    if ($next_steps && ($next = $next_steps->fetch_assoc())) {
        // Move to next step
        $conn->query("UPDATE approval_requests SET current_step = {$next['id']}, status = 'partially_approved' WHERE id = $request_id");
        log_audit($conn, $user_id, 'approved_step', 'approval', $req['reference_type'], $req['reference_id'], ['request_id' => $request_id, 'step' => $next['label'], 'comment' => $comment]);
        echo json_encode(["status" => "success", "message" => "Approved. Moving to next step: {$next['label']}"]);
    } else {
        // Final approval
        $conn->query("UPDATE approval_requests SET status = 'approved' WHERE id = $request_id");
        update_reference_status($conn, $req['reference_type'], $req['reference_id'], 'approved');
        log_audit($conn, $user_id, 'fully_approved', 'approval', $req['reference_type'], $req['reference_id'], ['request_id' => $request_id, 'comment' => $comment]);

        // If it's a payroll approval, update school account
        if ($req['reference_type'] === 'payroll') {
            $pay = $conn->query("SELECT net_salary FROM salary_payments WHERE id = {$req['reference_id']}")->fetch_assoc();
            if ($pay) {
                $bal = $conn->query("SELECT balance FROM school_account WHERE id = 1")->fetch_assoc();
                $current = floatval($bal['balance'] ?? 0);
                $net = floatval($pay['net_salary']);
                $new_bal = $current - $net;
                $conn->query("UPDATE school_account SET balance = $new_bal WHERE id = 1");
                $conn->query("INSERT INTO account_transactions (type, category, description, amount, balance_before, balance_after, reference_type, reference_id, recorded_by)
                              VALUES ('expense', 'Salaries', 'Salary payment #{$req['reference_id']}', $net, $current, $new_bal, 'payroll', {$req['reference_id']}, $user_id)");
            }
        }

        echo json_encode(["status" => "success", "message" => "Request fully approved!"]);
    }
    exit;
}

// ===== 4. GET REQUEST DETAILS =====
if ($action === 'get_request_details') {
    $request_id = (int) ($_GET['id'] ?? 0);
    $req = $conn->query("SELECT ar.*, COALESCE(t.fullname, e.fullname, s.fullname) as requester_name
                         FROM approval_requests ar
                         LEFT JOIN user u ON ar.requested_by = u.id
                         LEFT JOIN teachers t ON t.user_id = u.id
                         LEFT JOIN employees e ON e.user_id = u.id
                         LEFT JOIN students s ON s.user_id = u.id
                         WHERE ar.id = $request_id")->fetch_assoc();
    if (!$req) {
        echo json_encode(["error" => "Request not found"]);
        exit;
    }

    // Get actions
    $actions = [];
    $r = $conn->query("SELECT aa.*, COALESCE(t.fullname, e.fullname, s.fullname) as actor_name
                       FROM approval_actions aa
                       LEFT JOIN user u ON aa.actor_id = u.id
                       LEFT JOIN teachers t ON t.user_id = u.id
                       LEFT JOIN employees e ON e.user_id = u.id
                       LEFT JOIN students s ON s.user_id = u.id
                       WHERE aa.request_id = $request_id
                       ORDER BY aa.created_at");
    while ($row = $r->fetch_assoc()) $actions[] = $row;

    // Get workflow steps with status
    $steps = $conn->query("SELECT asd.*, aa.action as taken_action, aa.actor_id, COALESCE(t.fullname, e.fullname, s.fullname) as actor_name, aa.created_at as acted_at
                           FROM approval_step_definitions asd
                           LEFT JOIN approval_actions aa ON aa.step_definition_id = asd.id AND aa.request_id = $request_id
                           LEFT JOIN user u ON aa.actor_id = u.id
                           LEFT JOIN teachers t ON t.user_id = u.id
                           LEFT JOIN employees e ON e.user_id = u.id
                           LEFT JOIN students s ON s.user_id = u.id
                           WHERE asd.workflow_id = {$req['workflow_id']}
                           AND {$req['amount']} >= asd.min_amount AND {$req['amount']} <= asd.max_amount
                           ORDER BY asd.step_order");
    $step_list = [];
    while ($row = $steps->fetch_assoc()) $step_list[] = $row;

    $req['actions'] = $actions;
    $req['steps'] = $step_list;

    echo json_encode($req);
    exit;
}

// ===== 5. GET MY REQUESTS =====
if ($action === 'get_my_requests') {
    $limit = (int) ($_GET['limit'] ?? 50);
    $result = $conn->query("SELECT ar.*, COALESCE(t.fullname, e.fullname, s.fullname) as requester_name
                            FROM approval_requests ar
                            LEFT JOIN user u ON ar.requested_by = u.id
                            LEFT JOIN teachers t ON t.user_id = u.id
                            LEFT JOIN employees e ON e.user_id = u.id
                            LEFT JOIN students s ON s.user_id = u.id
                            WHERE ar.requested_by = $user_id
                            ORDER BY ar.created_at DESC
                            LIMIT $limit");
    $requests = [];
    while ($row = $result->fetch_assoc()) $requests[] = $row;
    echo json_encode($requests);
    exit;
}

// ===== 6. CANCEL REQUEST =====
if ($action === 'cancel_request') {
    $request_id = (int)($_GET['id'] ?? 0);
    $req = $conn->query("SELECT * FROM approval_requests WHERE id = $request_id AND requested_by = $user_id")->fetch_assoc();
    if (!$req) {
        echo json_encode(["status" => "error", "message" => "Request not found or access denied"]);
        exit;
    }
    if (!in_array($req['status'], ['draft', 'pending_approval', 'partially_approved'])) {
        echo json_encode(["status" => "error", "message" => "Cannot cancel a request with status: {$req['status']}"]);
        exit;
    }
    $conn->query("UPDATE approval_requests SET status = 'cancelled' WHERE id = $request_id");
    update_reference_status($conn, $req['reference_type'], $req['reference_id'], 'cancelled');
    log_audit($conn, $user_id, 'cancelled', 'approval', $req['reference_type'], $req['reference_id'], ['request_id' => $request_id]);
    echo json_encode(["status" => "success", "message" => "Request cancelled"]);
    exit;
}

// ===== 7. COUNT PENDING (for badges) =====
if ($action === 'count_pending') {
    $role = get_user_role($conn, $user_id);
    $count = 0;
    $r = $conn->query("SELECT COUNT(*) as c FROM approval_requests ar
                       JOIN approval_step_definitions asd ON ar.current_step = asd.id
                       WHERE ar.status = 'pending_approval' AND asd.role_required = '$role'");
    if ($r) $count = (int)$r->fetch_assoc()['c'];
    echo json_encode(["count" => $count]);
    exit;
}

// ===== 8. GET SUMMARY STATS =====
if ($action === 'get_approval_summary') {
    $role = get_user_role($conn, $user_id);
    // Pending for me
    $pending_for_me = $conn->query("SELECT COUNT(*) as c FROM approval_requests ar
                                    JOIN approval_step_definitions asd ON ar.current_step = asd.id
                                    WHERE ar.status = 'pending_approval' AND asd.role_required = '$role'")->fetch_assoc()['c'];

    // My submissions
    $my_pending = $conn->query("SELECT COUNT(*) as c FROM approval_requests WHERE requested_by = $user_id AND status = 'pending_approval'")->fetch_assoc()['c'];
    $my_approved = $conn->query("SELECT COUNT(*) as c FROM approval_requests WHERE requested_by = $user_id AND status = 'approved'")->fetch_assoc()['c'];
    $my_rejected = $conn->query("SELECT COUNT(*) as c FROM approval_requests WHERE requested_by = $user_id AND status = 'rejected'")->fetch_assoc()['c'];

    // Total pending across all
    $total_pending = $conn->query("SELECT COUNT(*) as c FROM approval_requests WHERE status = 'pending_approval'")->fetch_assoc()['c'];

    echo json_encode([
        "pending_for_me" => (int)$pending_for_me,
        "my_pending" => (int)$my_pending,
        "my_approved" => (int)$my_approved,
        "my_rejected" => (int)$my_rejected,
        "total_pending" => (int)$total_pending,
    ]);
    exit;
}

// ===== WORKFLOW MANAGEMENT (admin only) =====
if ($action === 'get_workflows') {
    $result = $conn->query("SELECT * FROM approval_workflows ORDER BY module, name");
    $workflows = [];
    while ($row = $result->fetch_assoc()) {
        $steps = $conn->query("SELECT * FROM approval_step_definitions WHERE workflow_id = {$row['id']} ORDER BY step_order");
        $row['steps'] = [];
        while ($s = $steps->fetch_assoc()) $row['steps'][] = $s;
        $workflows[] = $row;
    }
    echo json_encode($workflows);
    exit;
}

if ($action === 'save_workflow') {
    if ($user_role !== 'admin') { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = (int)($data['id'] ?? 0);
    $module = $conn->real_escape_string($data['module']);
    $name = $conn->real_escape_string($data['name']);
    $desc = $conn->real_escape_string($data['description'] ?? '');
    $active = (int)($data['is_active'] ?? 1);
    if ($id) {
        $conn->query("UPDATE approval_workflows SET module='$module', name='$name', description='$desc', is_active=$active WHERE id=$id");
    } else {
        $conn->query("INSERT INTO approval_workflows (module, name, description, is_active) VALUES ('$module', '$name', '$desc', $active)");
    }
    log_audit($conn, $user_id, 'save_workflow', 'approval', 'workflow', $id ?: $conn->insert_id, ['name' => $name, 'module' => $module]);
    echo json_encode(["status" => "success", "id" => $id ?: $conn->insert_id]);
    exit;
}

if ($action === 'delete_workflow') {
    if ($user_role !== 'admin') { echo json_encode(["error" => "Access denied"]); exit; }
    $id = (int)($_GET['id'] ?? 0);
    $conn->query("DELETE FROM approval_workflows WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

if ($action === 'save_workflow_step') {
    if ($user_role !== 'admin') { echo json_encode(["error" => "Access denied"]); exit; }
    $data = json_decode(file_get_contents("php://input"), true);
    $id = (int)($data['id'] ?? 0);
    $wf_id = (int)$data['workflow_id'];
    $order = (int)$data['step_order'];
    $role = $conn->real_escape_string($data['role_required']);
    $min_amt = (float)($data['min_amount'] ?? 0);
    $max_amt = (float)($data['max_amount'] ?? 999999999.99);
    $label = $conn->real_escape_string($data['label'] ?? '');
    if ($id) {
        $conn->query("UPDATE approval_step_definitions SET step_order=$order, role_required='$role', min_amount=$min_amt, max_amount=$max_amt, label='$label' WHERE id=$id");
    } else {
        $conn->query("INSERT INTO approval_step_definitions (workflow_id, step_order, role_required, min_amount, max_amount, label) VALUES ($wf_id, $order, '$role', $min_amt, $max_amt, '$label')");
    }
    echo json_encode(["status" => "success"]);
    exit;
}

if ($action === 'delete_workflow_step') {
    if ($user_role !== 'admin') { echo json_encode(["error" => "Access denied"]); exit; }
    $id = (int)($_GET['id'] ?? 0);
    $conn->query("DELETE FROM approval_step_definitions WHERE id = $id");
    echo json_encode(["status" => "deleted"]);
    exit;
}

// ===== GET ALL APPROVALS (for admin overview) =====
if ($action === 'get_all_approvals') {
    $status_filter = $conn->real_escape_string($_GET['status'] ?? '');
    $module_filter = $conn->real_escape_string($_GET['module'] ?? '');
    $limit = (int)($_GET['limit'] ?? 100);

    $sql = "SELECT ar.*, COALESCE(t.fullname, e.fullname, s.fullname) as requester_name
            FROM approval_requests ar
            LEFT JOIN user u ON ar.requested_by = u.id
            LEFT JOIN teachers t ON t.user_id = u.id
            LEFT JOIN employees e ON e.user_id = u.id
            LEFT JOIN students s ON s.user_id = u.id
            WHERE 1=1";
    if ($status_filter) $sql .= " AND ar.status = '$status_filter'";
    if ($module_filter) $sql .= " AND ar.module = '$module_filter'";
    $sql .= " ORDER BY ar.created_at DESC LIMIT $limit";

    $result = $conn->query($sql);
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $action_count = $conn->query("SELECT COUNT(*) as c FROM approval_actions WHERE request_id = {$row['id']}")->fetch_assoc()['c'];
        $row['action_count'] = (int)$action_count;
        $requests[] = $row;
    }
    echo json_encode($requests);
    exit;
}

// ===== GET MODULE STATS =====
if ($action === 'get_module_stats') {
    $module = $conn->real_escape_string($_GET['module'] ?? '');
    $module_where = $module ? "WHERE module = '$module'" : '';
    $result = $conn->query("SELECT module, status, COUNT(*) as count FROM approval_requests $module_where GROUP BY module, status ORDER BY module, status");
    $stats = [];
    while ($row = $result->fetch_assoc()) $stats[] = $row;
    echo json_encode($stats);
    exit;
}

// ===== UPDATE REQUEST =====
if ($action === 'update_request') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = (int)($data['id'] ?? 0);
    $notes = $conn->real_escape_string($data['notes'] ?? '');
    $conn->query("UPDATE approval_requests SET notes = '$notes' WHERE id = $id");
    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["error" => "Invalid action"]);
