<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../../../../db.php";

$input = json_decode(file_get_contents("php://input"), true);

$userId = (int)($input['userId'] ?? 0);
$field  = $input['field'] ?? null;
$value  = trim($input['value'] ?? '');
$schoolId = $_SESSION['user']['school_id'] ?? null;

// BASIC FIELD WHITELIST
$allowedFields = [
    "name",
    "email",
    "gender",
    "class_name",
    "date_birth",
    "status"
];

if (!$schoolId || !$userId || !in_array($field, $allowedFields)) {
    echo json_encode(["error" => "Invalid request"]);
    exit;
}

// FIELD VALIDATION
switch ($field) {
    case "name":
        if (!preg_match("/^[a-zA-ZÇçËë\s]+$/u", $value)) {
            echo json_encode(["error" => "Emri duhet të përmbajë vetëm shkronja."]);
            exit;
        }
        break;

    case "email":
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(["error" => "Email jo valid."]);
            exit;
        }
        break;

    case "gender":
        if (!in_array($value, ["male", "female", "other"])) {
            echo json_encode(["error" => "Gjinia jo valide."]);
            exit;
        }
        break;

    case "date_birth":
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $value)) {
            echo json_encode(["error" => "Formati i datës jo valid (YYYY-MM-DD)."]);
            exit;
        }
        break;

    case "status":
        if (!in_array($value, ["active", "inactive"])) {
            echo json_encode(["error" => "Status jo valid."]);
            exit;
        }
        break;
}

try {
    $stmt = $pdo->prepare("
        UPDATE students 
        SET $field = ? 
        WHERE user_id = ? AND school_id = ?
    ");
    $stmt->execute([$value, $userId, $schoolId]);

    echo json_encode(["success" => true]);
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
