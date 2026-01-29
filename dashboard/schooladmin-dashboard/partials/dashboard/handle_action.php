<?php
$rawInput = file_get_contents('php://input');
file_put_contents(__DIR__ . '/debug.log', "RAW:\n".$rawInput."\n\n", FILE_APPEND);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ======================
   AUTH
====================== */
if (
    !isset($_SESSION['user']) ||
    $_SESSION['user']['role'] !== 'school_admin'
) {
    http_response_code(403);
    echo json_encode(['error' => 'I paautorizuar']);
    exit;
}

/* ======================
   INPUT
====================== */
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON i pavlefshëm']);
    exit;
}

$schoolId = (int) ($_SESSION['user']['school_id'] ?? 0);
$adminId  = (int) ($_SESSION['user']['id'] ?? 0);

$actionType  = trim($input['type'] ?? '');
$actionTitle = trim($input['title'] ?? '');
$note        = trim($input['note'] ?? '');
$context     = $input['context'] ?? [];

/* ======================
   VALIDATION
====================== */
if (
    !$schoolId ||
    !$adminId ||
    $actionType === '' ||
    $actionTitle === ''
) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Të dhëna të paplota',
        'debug' => [
            'schoolId' => $schoolId,
            'adminId'  => $adminId,
            'type'     => $actionType,
            'title'    => $actionTitle
        ]
    ]);
    exit;
}

if (!is_array($context)) {
    $context = [];
}

/* ======================
   INSERT LOG
====================== */
try {
    $stmt = $pdo->prepare("
        INSERT INTO admin_logs
            (school_id, admin_id, action_type, action_title, context, note)
        VALUES
            (?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $schoolId,
        $adminId,
        $actionType,
        $actionTitle,
        json_encode($context, JSON_UNESCAPED_UNICODE),
        $note !== '' ? $note : null
    ]);

    echo json_encode(['status' => 'ok']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Gabim në sistem',
        'message' => $e->getMessage()
    ]);
}
