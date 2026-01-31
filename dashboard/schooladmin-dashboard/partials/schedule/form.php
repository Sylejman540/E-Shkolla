<?php
require_once __DIR__ . '/../../../../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id     = (int)$_POST['school_id'];
    $class_id      = (int)$_POST['class_id'];
    $teacher_id    = (int)$_POST['teacher_id']; // Kjo tani është ID-ja e saktë (p.sh. 70)
    $subject_id    = (int)$_POST['subject_id'];
    $day           = $_POST['day'];
    $period_number = (int)$_POST['period_number'];

    try {
        // Kontrollo nëse ekziston një orë në të njëjtën kohë për këtë klasë (opsionale)
        $check = $pdo->prepare("SELECT id FROM class_schedule WHERE class_id = ? AND day = ? AND period_number = ?");
        $check->execute([$class_id, $day, $period_number]);
        
        if ($check->fetch()) {
            // Update nëse ekziston, ose dërgo gabim
            $stmt = $pdo->prepare("UPDATE class_schedule SET teacher_id = ?, subject_id = ? WHERE class_id = ? AND day = ? AND period_number = ?");
            $stmt->execute([$teacher_id, $subject_id, $class_id, $day, $period_number]);
        } else {
            // Insert i ri
            $stmt = $pdo->prepare("INSERT INTO class_schedule (school_id, class_id, teacher_id, subject_id, day, period_number) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$school_id, $class_id, $teacher_id, $subject_id, $day, $period_number]);
        }

        // Kthehu te faqja e orarit me sukses
        header("Location: /E-Shkolla/schedule?class_id=$class_id&success=1");
        exit;
    } catch (PDOException $e) {
        // Debug: Shiko gabimin nëse dështon
        die("Gabim gjatë insertimit: " . $e->getMessage());
    }
}