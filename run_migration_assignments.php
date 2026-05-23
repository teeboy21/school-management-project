<?php
require __DIR__ . '/config.php';

$sql = file_get_contents(__DIR__ . '/migrate_assignments.sql');
$statements = explode(';', $sql);
$success = 0;
$errors = [];

foreach ($statements as $stmt) {
    $stmt = trim($stmt);
    if (empty($stmt)) continue;
    if ($conn->query($stmt)) {
        $success++;
    } else {
        // Ignore "IF NOT EXISTS" duplicate column errors
        if (strpos($conn->error, 'Duplicate column') === false) {
            $errors[] = $conn->error;
        } else {
            $success++;
        }
    }
}

echo "<h2>Migration completed</h2>";
echo "<p>$success statement(s) executed successfully.</p>";
if (!empty($errors)) {
    echo "<h3>Errors:</h3><pre>" . implode("\n", $errors) . "</pre>";
} else {
    echo "<p>No errors.</p>";
}

// Verify tables exist
$tables = ['assignments', 'assignment_submissions'];
foreach ($tables as $t) {
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    echo "<p>Table '$t': " . ($r->num_rows > 0 ? "✓ EXISTS" : "✗ MISSING") . "</p>";
}

echo "<p><a href='assignments.php'>Go to Assignments</a></p>";
