<?php
// Datei: admin_tab_logs.php – Stand: 2025-04-22 09:27 Europe/Berlin

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
requireRole('admin');

$logs = ['audit' => 'audit.log', 'error' => 'error.log'];
$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log = $_POST['log'] ?? '';
    $action = $_POST['action'] ?? '';
    $file = $logs[$log] ?? null;

    try {
        if (is_string($file) && file_exists($file)) {
            if ($action === 'clear') {
                file_put_contents($file, '');
                $info = "$file wurde geleert.";
                @file_put_contents("audit.log", date('c') . " Logdatei '$file' geleert durch Admin {\$_SESSION['user']}
", FILE_APPEND);
            } elseif ($action === 'backup') {
                $copyName = $file . '_backup_' . date('Ymd_His') . '.log';
                copy($file, $copyName);
                $info = "Backup gespeichert als $copyName.";
                @file_put_contents("audit.log", date('c') . " Logdatei '$file' gesichert als '$copyName' durch Admin {\$_SESSION['user']}
", FILE_APPEND);
            }
        } else {
            $error = "Ungültiger Logtyp oder Datei nicht vorhanden.";
            @file_put_contents("error.log", date('c') . " Ungültiger Logzugriff durch Admin {\$_SESSION['user']}
", FILE_APPEND);
        }
    } catch (Exception $e) {
        $error = "Fehler beim Schreiben der Logdateien: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Logdateien</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
<?php include "admin_tab_nav.php"; ?>
<div style="width: 90%; margin: 0 auto;">
    <h4 class="mb-4">Logdateien anzeigen & verwalten</h4>

    <?php if ($info): ?><div class="alert alert-success small"><?= htmlspecialchars($info) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php foreach ($logs as $key => $file): ?>
        <div class="bg-white border p-3 rounded mb-4">
            <form method="post" class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <strong><?= ucfirst($key) ?>.log</strong><br>
                    <small class="text-muted">Datei: <?= $file ?> (<?= file_exists($file) ? round(filesize($file) / 1024, 1) . ' KB' : 'nicht vorhanden' ?>)</small>
                    <input type="hidden" name="log" value="<?= $key ?>">
                </div>
                <div>
                    <button name="action" value="clear" class="btn btn-sm btn-outline-danger me-2">Löschen</button>
                    <button name="action" value="backup" class="btn btn-sm btn-outline-secondary">Sichern</button>
                </div>
            </form>
            <div class="bg-light p-2 small border" style="max-height: 300px; overflow:auto;">
                <pre class="mb-0"><?php echo file_exists($file) ? htmlspecialchars(file_get_contents($file)) : 'Datei nicht gefunden.'; ?></pre>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
