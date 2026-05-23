<?php
require __DIR__ . '/../config.php';

echo "<h2>Teachers Table Check</h2>";
$r = $conn->query("DESCRIBE teachers");
echo "<pre>";
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";

echo "<h2>Sample Teachers</h2>";
$r = $conn->query("SELECT * FROM teachers LIMIT 3");
if ($r && $r->num_rows > 0) {
    while ($row = $r->fetch_assoc()) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "No teachers found in database";
}

echo "<h2>User Table Check</h2>";
$r = $conn->query("DESCRIBE user");
echo "<pre>";
while ($row = $r->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
echo "</pre>";