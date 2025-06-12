<?php
// Datei: session_diagnose.php – Diagnose für PHP-Sessions

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
$_SESSION['diagnose'] = 'Session test value';

$sessionSavePath = session_save_path() ?: sys_get_temp_dir();
$sessionId = session_id();
$sessionFile = rtrim($sessionSavePath, '/\\') . '/sess_' . $sessionId;
$sessionHandler = ini_get('session.save_handler');
$isWritable = is_writable($sessionSavePath);
$sessionStatus = session_status();

function readableBytes($bytes) {
    $units = ['B','KB','MB','GB'];
    for ($i = 0; $bytes >= 1024 && $i < 3; $i++) $bytes /= 1024;
    return round($bytes, 2) . ' ' . $units[$i];
}

$allSessions = [];
if (is_dir($sessionSavePath) && is_readable($sessionSavePath)) {
    $files = scandir($sessionSavePath);
    foreach ($files as $file) {
        if (strpos($file, 'sess_') === 0) {
            $path = $sessionSavePath . '/' . $file;
            $allSessions[] = [
                'name' => $file,
                'size' => file_exists($path) ? readableBytes(filesize($path)) : 'n/a',
                'mtime' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : 'n/a',
                'owner' => file_exists($path) ? posix_getpwuid(fileowner($path))['name'] : 'n/a'
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Session Diagnose</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
<div class="container">
    <h2 class="mb-4">PHP Session Diagnostic</h2>

    <table class="table table-bordered table-sm bg-white">
        <tr><th>Session Status</th><td><?= ['disabled','none','active'][$sessionStatus] ?></td></tr>
        <tr><th>Session ID</th><td><?= htmlspecialchars($sessionId) ?></td></tr>
        <tr><th>Session Save Path</th><td><?= htmlspecialchars($sessionSavePath) ?></td></tr>
        <tr><th>Session Save Handler</th><td><?= htmlspecialchars($sessionHandler) ?></td></tr>
        <tr><th>Session File</th><td><?= htmlspecialchars($sessionFile) ?></td></tr>
        <tr><th>Session Path Writable?</th><td><?= $isWritable ? 'Yes' : 'No' ?></td></tr>
        <tr><th>Session Variable</th><td><?= $_SESSION['diagnose'] ?? 'n/a' ?></td></tr>
        <tr><th>Session File Exists?</th><td><?= file_exists($sessionFile) ? 'Yes' : 'No' ?></td></tr>
        <tr><th>Session File Size</th><td><?= file_exists($sessionFile) ? readableBytes(filesize($sessionFile)) : 'n/a' ?></td></tr>
        <tr><th>Session File Owner</th><td><?= file_exists($sessionFile) ? posix_getpwuid(fileowner($sessionFile))['name'] : 'n/a' ?></td></tr>
        <tr><th>Session File ModTime</th><td><?= file_exists($sessionFile) ? date('Y-m-d H:i:s', filemtime($sessionFile)) : 'n/a' ?></td></tr>
    </table>

    <h4 class="mt-5">Detected Session Files (<?= count($allSessions) ?>)</h4>
    <?php if (count($allSessions) > 0): ?>
    <table class="table table-bordered table-hover table-sm bg-white">
        <thead><tr><th>File</th><th>Size</th><th>Modified</th><th>Owner</th></tr></thead>
        <tbody>
        <?php foreach ($allSessions as $s): ?>
            <tr>
                <td><?= htmlspecialchars($s['name']) ?></td>
                <td><?= $s['size'] ?></td>
                <td><?= $s['mtime'] ?></td>
                <td><?= htmlspecialchars($s['owner']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
        <p class="text-muted">No session files could be listed. Possibly due to permission restrictions or empty directory.</p>
    <?php endif; ?>
   </div>
</body>
</html>
