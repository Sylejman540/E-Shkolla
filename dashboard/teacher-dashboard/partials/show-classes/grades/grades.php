<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../index.php'; 
require_once __DIR__ . '/../../../../../db.php';

$stmt = $pdo->prepare("SELECT * FROM assignments ORDER BY created_at DESC");
$stmt->execute();
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// simple stats
$total = count($assignments);
$completed = 0; // later you can calculate real values
$active = $total - $completed;
?>
<!DOCTYPE html>
<html lang="sq">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>E-Shkolla | Detyrat</title>

  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50">
<main class="lg:pl-72">
  <div class="px-4 py-8 sm:px-6 lg:px-8">

  </div>
</main>

<script>
const btn = document.getElementById('addSchoolBtn');
const form = document.getElementById('addSchoolForm');
const cancel = document.getElementById('cancel');

btn?.addEventListener('click', () => {
  form.classList.remove('hidden');
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

cancel?.addEventListener('click', () => {
  form.classList.add('hidden');
});
</script>

</body>
</html>
