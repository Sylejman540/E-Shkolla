<?php
date_default_timezone_set('Europe/Tirane');
// helpers/ParentEmails.php

function getParentEmailsByStudent(int $studentId, PDO $pdo): array
{
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.email
        FROM parent_student ps
        JOIN parents p ON p.id = ps.parent_id
        WHERE ps.student_id = ?
          AND p.email IS NOT NULL
          AND p.email != ''
    ");

    $stmt->execute([$studentId]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}
