<?php
include 'config.php';

foreach ($_POST['marks'] as $student_id => $assessments) {

    foreach ($assessments as $assessment_id => $mark) {

        $stmt = $conn->prepare("
            INSERT INTO student_marks (student_id, assessment_id, mark)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE mark=VALUES(mark)
        ");

        $stmt->bind_param("iii", $student_id, $assessment_id, $mark);
        $stmt->execute();
    }
}

echo "Marks saved successfully!";
header("Location: marks.php?subject_id=".$_GET['subject_id']."&term=".$_GET['term']);
exit();
?>