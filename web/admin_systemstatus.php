<?php
// Datei: admin_systemstatus.php â€“ Stand: 2025-05-09 Europe/Berlin
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    exit("Nicht erlaubt");
}

function checkFile($path) {
    return [
        'exists' => file_exists($path),
        'writable' => is_writable($path),
        'readable' => is_readable($path),
        'size' => file_exists($path) ? filesize($path) : 0
    ];
}

function statusBadge($bool) {
    return $bool ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-danger">Nein</span>';
}

$dbStatus = checkFile("users.db");
$auditLog = checkFile("audit.log");
$errorLog = checkFile("error.log");
$envFile = checkFile(".env");
$mailerInstalled = class_exists('PHPMailer\PHPMailer\PHPMailer');
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Systemstatus</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <h4 class="mb-3">Systemstatus</h4>

    <table class="table table-sm table-bordered bg-white shadow-sm">
        <thead class="table-light">
            <tr>
                <th>Komponente</th>
                <th>Existiert</th>
                <th>Lesbar</th>
                <th>Schreibbar</th>
                <th>GrÃ¶ÃŸe</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>users.db</strong></td>
                <td><?= statusBadge($dbStatus['exists']) ?></td>
                <td><?= statusBadge($dbStatus['readable']) ?></td>
                <td><?= statusBadge($dbStatus['writable']) ?></td>
                <td><?= $dbStatus['size'] ?> Bytes</td>
            </tr>
            <tr>
                <td>audit.log</td>
                <td><?= statusBadge($auditLog['exists']) ?></td>
                <td><?= statusBadge($auditLog['readable']) ?></td>
                <td><?= statusBadge($auditLog['writable']) ?></td>
                <td><?= $auditLog['size'] ?> Bytes</td>
            </tr>
            <tr>
                <td>error.log</td>
                <td><?= statusBadge($errorLog['exists']) ?></td>
                <td><?= statusBadge($errorLog['readable']) ?></td>
                <td><?= statusBadge($errorLog['writable']) ?></td>
                <td><?= $errorLog['size'] ?> Bytes</td>
            </tr>
            <tr>
                <td>.env</td>
                <td><?= statusBadge($envFile['exists']) ?></td>
                <td><?= statusBadge($envFile['readable']) ?></td>
                <td><?= statusBadge($envFile['writable']) ?></td>
                <td><?= $envFile['size'] ?> Bytes</td>
            </tr>
        </tbody>
    </table>

    <h5 class="mt-4">Serverinformationen</h5>
    <ul class="list-group list-group-flush mb-4">
        <li class="list-group-item">PHP-Version: <strong><?= phpversion() ?></strong></li>
        <li class="list-group-item">PHPMailer installiert: <?= statusBadge($mailerInstalled) ?></li>
        <li class="list-group-item">Webserver: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'unbekannt' ?></li>
        <li class="list-group-item">System: <?= PHP_OS ?></li>
        <li class="list-group-item">Max. Upload-GrÃ¶ÃŸe: <?= ini_get('upload_max_filesize') ?></li>
        <li class="list-group-item">Max. Post-GrÃ¶ÃŸe: <?= ini_get('post_max_size') ?></li>
        <li class="list-group-item">Session-Speicherpfad: <?= session_save_path() ?></li>
    </ul>

    <a href="admin.php" class="btn btn-outline-primary">ZurÃ¼ck zum Adminbereich</a>
</div>
</body>
</html>
