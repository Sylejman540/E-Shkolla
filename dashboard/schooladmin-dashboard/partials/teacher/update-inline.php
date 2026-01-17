<?php
session_start();
require_once '../../../../db.php';

// Validate session and role
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['role'], ['super_admin', 'school_admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get and validate JSON input
$input = file_get_contents('php://input');
if (empty($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty request']);
    exit;
}

$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$userId = filter_var($data['userId'] ?? 0, FILTER_VALIDATE_INT);
$field  = trim($data['field'] ?? '');
$value  = trim($data['value'] ?? '');

if (!$userId || $userId <= 0 || !$field) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID or field']);
    exit;
}

// Additional security: School admins can only edit users from their school
if ($_SESSION['user']['role'] === 'school_admin') {
    $schoolId = $_SESSION['user']['school_id'] ?? 0;
    
    // Verify the user belongs to the same school
    $checkStmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM users u
        LEFT JOIN teachers t ON u.id = t.user_id
        LEFT JOIN students s ON u.id = s.user_id
        LEFT JOIN parents p ON u.id = p.user_id
        WHERE u.id = ? 
        AND (
            t.school_id = ? OR 
            s.school_id = ? OR 
            p.school_id = ? OR
            u.school_id = ?
        )
    ");
    $checkStmt->execute([$userId, $schoolId, $schoolId, $schoolId, $schoolId]);
    
    if ($checkStmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Cannot edit users from other schools']);
        exit;
    }
}

// Define allowed fields with validation rules
$allowedFields = [
    'status' => [
        'table' => 'users',
        'validation' => fn($v) => in_array($v, ['Active', 'Inactive', 'Suspended', 'Pending'], true),
        'max_length' => 20
    ],
    'name' => [
        'table' => 'users',
        'validation' => fn($v) => preg_match('/^[a-zA-Z\s\-\.\']{2,100}$/', $v),
        'max_length' => 100
    ],
    'email' => [
        'table' => 'users',
        'validation' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) && strlen($v) <= 255,
        'max_length' => 255
    ],
    'phone' => [
        'table' => 'teachers',
        'validation' => fn($v) => preg_match('/^[\d\s\-\+\(\)]{6,20}$/', $v),
        'max_length' => 20
    ],
    'gender' => [
        'table' => 'teachers',
        'validation' => fn($v) => in_array($v, ['Male', 'Female', 'Other'], true),
        'max_length' => 10
    ],
    'subject_name' => [
        'table' => 'subjects',
        'validation' => fn($v) => preg_match('/^[a-zA-Z\s\-]{2,100}$/', $v),
        'max_length' => 100
    ],
    'description' => [
        'table' => 'subjects',
        'validation' => fn($v) => strlen($v) <= 500,
        'max_length' => 500
    ]
];

// Check if field is allowed
if (!isset($allowedFields[$field])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid field']);
    exit;
}

// Get field configuration
$fieldConfig = $allowedFields[$field];

// Validate value
if (strlen($value) > $fieldConfig['max_length']) {
    http_response_code(400);
    echo json_encode(['error' => 'Value too long']);
    exit;
}

if (!$fieldConfig['validation']($value)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid value format']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Update main table based on field
    $table = $fieldConfig['table'];
    $query = "UPDATE `$table` SET `$field` = ? WHERE ";
    
    // Determine WHERE clause based on table
    switch ($table) {
        case 'users':
            $query .= "id = ?";
            $params = [$value, $userId];
            break;
            
        case 'teachers':
            // Verify user is actually a teacher before updating
            $checkTeacher = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
            $checkTeacher->execute([$userId]);
            if (!$checkTeacher->fetch()) {
                throw new Exception('User is not a teacher');
            }
            $query .= "user_id = ?";
            $params = [$value, $userId];
            break;
            
        case 'subjects':
            // Verify user is actually a subject (if applicable)
            $checkSubject = $pdo->prepare("SELECT id FROM subjects WHERE user_id = ?");
            $checkSubject->execute([$userId]);
            if (!$checkSubject->fetch()) {
                throw new Exception('User is not associated with subjects');
            }
            $query .= "user_id = ?";
            $params = [$value, $userId];
            break;
            
        default:
            throw new Exception('Invalid table');
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    // For name field, also update name in teacher/subject tables if they exist
    if ($field === 'name' && $table === 'users') {
        // Update teachers table if user is a teacher
        $pdo->prepare("UPDATE teachers SET name = ? WHERE user_id = ?")
            ->execute([$value, $userId]);
        
        // Update subjects table if user is associated with subjects
        $pdo->prepare("UPDATE subjects SET name = ? WHERE user_id = ?")
            ->execute([$value, $userId]);
    }
    
    // For status field, update all associated tables
    if ($field === 'status') {
        // Only update if the record exists
        $pdo->prepare("UPDATE teachers SET status = ? WHERE user_id = ?")
            ->execute([$value, $userId]);
        
        $pdo->prepare("UPDATE subjects SET status = ? WHERE user_id = ?")
            ->execute([$value, $userId]);
    }
    
    $pdo->commit();
    
    // Log the action
    $logStmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action, target_user_id, details, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $logStmt->execute([
        $_SESSION['user']['id'],
        'user_update',
        $userId,
        json_encode(['field' => $field, 'value' => $value]),
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Updated successfully']);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Update failed: ' . $e->getMessage()]);
    error_log("User update error: " . $e->getMessage());
}