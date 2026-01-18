    <?php
    session_start();
    header('Content-Type: application/json');
    require_once __DIR__ . '/../../../../db.php';

    $schoolId = $_SESSION['user']['school_id'] ?? null;
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$schoolId || !$data) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit;
    }

    $userId = $data['userId'] ?? null;
    $field  = $data['field'] ?? null;
    $value  = trim($data['value'] ?? '');

    $allowedFields = ['name', 'email', 'status', 'gender', 'phone', 'subject_name'];
    if (!in_array($field, $allowedFields)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Fushë e pavlefshme']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Get the OLD name before updating (needed to find matches in subjects table)
        $oldName = null;
        if ($field === 'name') {
            $nameStmt = $pdo->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
            $nameStmt->execute([$userId]);
            $oldName = $nameStmt->fetchColumn();
        }

        // 2. Email uniqueness check
        if ($field === 'email') {
            $emailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $emailStmt->execute([$value, $userId]);
            if ($emailStmt->fetch()) {
                $pdo->rollBack();
                echo json_encode(['status' => 'error', 'message' => 'Ky email ekziston në sistem.']);
                exit;
            }
        }

        // 3. Update Teachers Table
        $stmt1 = $pdo->prepare("UPDATE teachers SET $field = ? WHERE user_id = ? AND school_id = ?");
        $stmt1->execute([$value, $userId, $schoolId]);

        // 4. Update Users Table (for shared login fields)
        $sharedFields = ['name', 'email', 'status'];
        if (in_array($field, $sharedFields)) {
            $stmt2 = $pdo->prepare("UPDATE users SET $field = ? WHERE id = ? AND school_id = ?");
            $stmt2->execute([$value, $userId, $schoolId]);
        }

        // 5. SYNC SUBJECTS TABLE
        // If name changed, update every subject where this teacher was assigned by name
if (in_array($field, ['name', 'subject_name'])) {
    $stmt2 = $pdo->prepare("UPDATE subjects SET $field = ? WHERE user_id = ? AND school_id = ?");
    $stmt2->execute([$value, $userId, $schoolId]);
}

        $pdo->commit();
        echo json_encode(['status' => 'success']);

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Gabim në server: ' . $e->getMessage()]);
    }