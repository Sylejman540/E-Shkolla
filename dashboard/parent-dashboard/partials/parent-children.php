<?php
require_once __DIR__ . '/../index.php';
require_once __DIR__ . '/../../../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   AUTH GUARD
   ========================= */
if (!isset($_SESSION['user']['id'], $_SESSION['user']['school_id'])) {
    die('Not authenticated');
}

$userId   = (int) $_SESSION['user']['id'];
$schoolId = (int) $_SESSION['user']['school_id'];

/* =========================
   RESOLVE parent_id
   ========================= */
$stmt = $pdo->prepare("
    SELECT id
    FROM parents
    WHERE user_id = ? AND school_id = ?
");
$stmt->execute([$userId, $schoolId]);
$parentId = (int) $stmt->fetchColumn();

if (!$parentId) {
    die('Parent profile not found');
}

/* =========================
   FETCH CHILDREN
   ========================= */
$stmt = $pdo->prepare("
    SELECT
        s.student_id,
        s.name AS student_name,
        s.class_name,
        s.status,
        s.created_at
    FROM parent_student ps
    JOIN students s ON s.student_id = ps.student_id
    WHERE ps.parent_id = ?
      AND s.school_id = ?
    ORDER BY s.name ASC
");
$stmt->execute([$parentId, $schoolId]);
$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Fëmijët e Mi | E-Shkolla</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100">
<main class="lg:pl-72">
<div class="px-6 py-8 space-y-6">

    <!-- HEADER -->
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-semibold">Fëmijët e Mi 👨‍👩‍👧‍👦</h2>
        <p class="text-gray-500 text-sm">
            Lista e nxënësve të lidhur me llogarinë tuaj
        </p>
    </div>

    <!-- CHILDREN LIST -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="min-w-full">
            <thead class="bg-gray-50 text-gray-600 text-sm">
                <tr>
                    <th class="px-6 py-3 text-left">Nxënësi</th>
                    <th class="px-6 py-3 text-left">Klasa</th>
                    <th class="px-6 py-3 text-left">Statusi</th>
                    <th class="px-6 py-3 text-right">Veprime</th>
                </tr>
            </thead>
            <tbody class="divide-y">
            <?php if (!empty($children)): ?>
                <?php foreach ($children as $child): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 font-medium">
                            <?= htmlspecialchars($child['student_name']) ?>
                        </td>

                        <td class="px-6 py-4">
                            <?= htmlspecialchars($child['class_name']) ?>
                        </td>

                        <td class="px-6 py-4">
                            <span class="px-2 py-1 text-xs rounded
                                <?= $child['status'] === 'active'
                                    ? 'bg-green-100 text-green-600'
                                    : 'bg-red-100 text-red-600' ?>">
                                <?= ucfirst($child['status']) ?>
                            </span>
                        </td>

                        <td class="px-6 py-4 text-right space-x-2">
                            <a href="/E-Shkolla/parent-dashboard?student_id=<?= $child['student_id'] ?>"
                               class="text-blue-600 hover:underline text-sm">
                                Paneli
                            </a>

                            <a href="/E-Shkolla/parent-grades?student_id=<?= $child['student_id'] ?>"
                               class="text-purple-600 hover:underline text-sm">
                                Notat
                            </a>

                            <a href="/E-Shkolla/parent-attendance?student_id=<?= $child['student_id'] ?>"
                               class="text-green-600 hover:underline text-sm">
                                Prezenca
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="4" class="px-6 py-6 text-center text-gray-500">
                        Nuk ka fëmijë të lidhur me këtë llogari.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</main>
</body>
</html>
