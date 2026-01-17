<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Content-Type: application/json");

require_once __DIR__ . "/../../../../db.php";

$input = json_decode(file_get_contents("php://input"), true);

$userId   = (int)($input["userId"] ?? 0);
$field    = $input["field"] ?? null;
$value    = trim($input["value"] ?? "");
$schoolId = $_SESSION["user"]["school_id"] ?? null;

// Allowed editable fields
$allowedFields = [
    "name",
    "email",
    "gender",
    "class_name",
    "date_birth",
    "status"
];

// Basic validation
if (!$schoolId || !$userId || !in_array($field, $allowedFields, true)) {
    echo json_encode(["error" => "Kërkesë e pavlefshme."]);
    exit;
}

// Field-specific validation
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

        // UNIQUE EMAIL CHECK IN users TABLE
        $check = $pdo->prepare("
            SELECT id 
            FROM users 
            WHERE email = ? AND id != ?
        ");
        $check->execute([$value, $userId]);

        if ($check->fetch()) {
            echo json_encode(["error" => "Ky email është i regjistruar nga një përdorues tjetër."]);
            exit;
        }
        break;

    case "gender":
        if (!in_array($value, ["male", "female", "other"], true)) {
            echo json_encode(["error" => "Gjinia nuk është valide."]);
            exit;
        }
        break;

    case "date_birth":
        if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $value)) {
            echo json_encode(["error" => "Formati i datës duhet të jetë YYYY-MM-DD."]);
            exit;
        }
        break;

    case "status":
        if (!in_array($value, ["active", "inactive"], true)) {
            echo json_encode(["error" => "Status jo valid."]);
            exit;
        }
        break;
}

try {

    // UPDATE students table
    $stmtStudent = $pdo->prepare("
        UPDATE students
        SET $field = ?
        WHERE user_id = ? AND school_id = ?
    ");
    $stmtStudent->execute([$value, $userId, $schoolId]);


    // IF EMAIL OR NAME IS CHANGED → ALSO UPDATE users TABLE
    if ($field === "email" || $field === "name") {

        $stmtUser = $pdo->prepare("
            UPDATE users
            SET $field = ?
            WHERE id = ?
        ");
        $stmtUser->execute([$value, $userId]);
    }

    echo json_encode(["success" => true]);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
