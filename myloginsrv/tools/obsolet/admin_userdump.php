<?php
// Datei: admin_userdump.php â€“ Stand: 2025-04-23 11:54 Europe/Berlin

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$rows = $db->query("SELECT id, username, email, role, active FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Benutzerdatenbank</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4" style="max-width: 100%;">
    <?php if (file_exists(__DIR__ . '/admin_tab_nav.php')) include __DIR__ . '/admin_tab_nav.php'; ?>

    <h4 class="mb-4">Benutzerdatenbank anzeigen</h4>

    <div class="table-responsive bg-white border rounded p-3 small">
        <table class="table table-sm table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <?php if (!empty($rows[0])): ?>
                        <?php foreach (array_keys($rows[0]) as $col): ?>
                            <th><?= htmlspecialchars($col) ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $val): ?>
                            <td><?= htmlspecialchars((string)$val) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>