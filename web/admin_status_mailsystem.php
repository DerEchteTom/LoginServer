<?php
// Datei: admin_status_mailsystem.php â€“ Stand: 2025-04-23 11:58 Europe/Berlin

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
requireRole('admin');

require_once __DIR__ . '/mailer_config.php';
$env = debugSMTPEnv();
$envRaw = file_exists('.env') ? file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$envOther = [];

foreach ($envRaw as $line) {
    if (strpos($line, '=') !== false) {
        list($k, $v) = explode('=', $line, 2);
        if (!array_key_exists(trim($k), $env)) {
            $envOther[trim($k)] = trim($v);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>e-mail configuration</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4" style="max-width: 100%;">
    <?php if (file_exists(__DIR__ . '/admin_tab_nav.php')) include __DIR__ . '/admin_tab_nav.php'; ?>

    <h4 class="mb-4">e-mail configuration (.env) and e-mail test</h4>

    <div class="bg-white border rounded p-3 small mb-4">
        <h6>SMTP variables</h6>
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light"><tr><th>variable</th><th>value</th></tr></thead>
            <tbody>
                <?php foreach ($env as $key => $val): ?>
                    <tr><td><?= htmlspecialchars($key) ?></td><td><?= htmlspecialchars($val) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($envOther)): ?>
    <div class="bg-white border rounded p-3 small">
        <h6>further .env variables</h6>
        <table class="table table-sm table-bordered align-middle mb-0">
            <thead class="table-light"><tr><th>variable</th><th>value</th></tr></thead>
            <tbody>
                <?php foreach ($envOther as $key => $val): ?>
                    <tr><td><?= htmlspecialchars($key) ?></td><td><?= htmlspecialchars($val) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="mt-4">
        <a href="admin_tab_mailtest.php" class="btn btn-outline-secondary btn-sm">e-mail-test side</a>
        <a href="admin_tab_status.php" class="btn btn-outline-secondary btn-sm">back to systemstatus</a>
    </div>
</div>
</body>
</html>