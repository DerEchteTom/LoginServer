<?php
session_start();
date_default_timezone_set('Europe/Berlin');

// Hilfsfunktionen
function checkFile($path) {
    return [
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'writable' => is_writable($path),
        'size' => file_exists($path) ? filesize($path) : 0
    ];
}

function getFilePerms($path) {
    return file_exists($path) ? substr(sprintf('%o', fileperms($path)), -3) : '---';
}

function testPdoWrite($file, &$error = '') {
    try {
        $pdo = new PDO("sqlite:$file");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS _writetest (id INTEGER)");
        $pdo->exec("DROP TABLE IF EXISTS _writetest");
        return true;
    } catch (Exception $e) {
        $error = $e->getMessage();
        return false;
    }
}

function statusBadge($ok) {
    return $ok ? '<span class="badge border border-success text-success">Yes</span>'
               : '<span class="badge border border-danger text-danger">No</span>';
}

function getEncStatus($file) {
    if (!file_exists($file)) return "missing";
    $txt = file_get_contents($file);
    return (strpos($txt, 'ENC:') !== false || strpos($txt, 'XOR:') !== false) ? "yes" : "no";
}

function getMailerStatus() {
    $status = "No";
    $diag = [];

    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    $cls = 'PHPMailer\\PHPMailer\\PHPMailer';

    if (file_exists($autoloadPath)) {
        $diag[] = "Autoload OK";
        require_once $autoloadPath;
        if (class_exists($cls)) {
            $diag[] = "Class found";
            if (method_exists($cls, 'send')) {
                $status = "Yes";
                $diag[] = "send() available";
            } else {
                $diag[] = "send() missing";
            }
        } else {
            $diag[] = "Class missing";
        }
    } else {
        $diag[] = "Autoload missing";
    }

    return [$status, $diag];
}

// Dateiinfos
$files = [
    'users.db' => checkFile('users.db'),
    'cms.db' => checkFile('cms.db'),
    'encryption.key' => checkFile('encryption.key'),
    'audit.log' => checkFile('audit.log'),
    'error.log' => checkFile('error.log'),
    '.env' => checkFile('.env'),
    '.envad' => checkFile('.envad')
];

// PDO & CHMOD
foreach (['users.db', 'cms.db'] as $db) {
    $files[$db]['chmod'] = getFilePerms($db);
    $files[$db]['pdo_error'] = '';
    $files[$db]['pdo'] = testPdoWrite($db, $files[$db]['pdo_error']);
}

$files['.env']['enc'] = getEncStatus('.env');
$files['.envad']['enc'] = getEncStatus('.envad');

list($mailerStatus, $mailerDiag) = getMailerStatus();

// Sessions
$sessionDir = session_save_path();
$active = $admins = $users = 0;
if (!empty($sessionDir) && is_dir($sessionDir)) {
    foreach (scandir($sessionDir) as $file) {
        if (strpos($file, 'sess_') === 0) {
            $data = @file_get_contents("$sessionDir/$file");
            if ($data && preg_match('/role\|s:\d+:"(admin|user)"/', $data, $m)) {
                $active++;
                $m[1] === 'admin' ? $admins++ : $users++;
            }
        }
    }
}

// Datenbankabfragen
try {
    $dbh = new PDO("sqlite:users.db");
    $totalUsers = $dbh->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $inactiveUsers = $dbh->query("SELECT COUNT(*) FROM users WHERE active = 0")->fetchColumn();
    $adminUsers = $dbh->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $totalLinks = $dbh->query("SELECT COUNT(*) FROM user_links")->fetchColumn();
    $openRequests = $dbh->query("SELECT COUNT(*) FROM link_requests WHERE status = 'open'")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = $inactiveUsers = $adminUsers = $totalLinks = $openRequests = 0;
}

$username = $_SESSION['username'] ?? '-';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>System Status</title>
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .tooltip-box { display: none; background: #eee; border: 1px solid #aaa; padding: 10px; font-size: 0.9rem; margin-top: 5px; width: 100%; }
    .pdo-icon { margin-left: 0.5rem; color: #c00; cursor: pointer; font-weight: bold; }
  </style>
  <script>
    function toggleTooltip(id) {
      const el = document.getElementById(id);
      el.style.display = (el.style.display === 'block') ? 'none' : 'block';
    }
  </script>
</head>
<body class="bg-light" style="font-size: 0.95rem;">
<div class="container-fluid mt-4">
<div style="width: 90%; margin: auto;">
  <h5 class="mb-3">System Status</h5>
  <div class="mb-3 text-dark fw-bold">Logged in: <strong><?= htmlspecialchars($username) ?></strong> (Session-ID: <?= session_id() ?>)</div>

  <!-- Datenbank-Dateien -->
  <table class="table table-bordered table-sm bg-white shadow-sm">
    <thead class="table-light">
      <tr><th>Database File</th><th>Exists</th><th>Readable</th><th>Writable</th><th>CHMOD</th><th>Size</th><th>PDO-Write</th></tr>
    </thead>
    <tbody>
    <?php foreach (['users.db', 'cms.db'] as $db): ?>
      <tr>
        <td><?= $db ?></td>
        <td><?= statusBadge($files[$db]['exists']) ?></td>
        <td><?= statusBadge($files[$db]['readable']) ?></td>
        <td><?= statusBadge($files[$db]['writable']) ?></td>
        <td><?= $files[$db]['chmod'] ?></td>
        <td><?= $files[$db]['size'] ?> B</td>
        <td>
          <?= statusBadge($files[$db]['pdo']) ?>
          <?php if (!$files[$db]['pdo']): ?>
            <span class="pdo-icon" onclick="toggleTooltip('err-<?= $db ?>')">?</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php if (!$files[$db]['pdo']): ?>
        <tr><td colspan="7"><div class="tooltip-box" id="err-<?= $db ?>"><?= htmlspecialchars($files[$db]['pdo_error']) ?></div></td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Andere Dateien -->
  <table class="table table-sm table-bordered bg-white shadow-sm">
    <thead class="table-light">
      <tr><th>Component</th><th>Exists</th><th>Writable</th><th>CHMOD</th><th>Size</th><th>Encrypted</th></tr>
    </thead>
    <tbody>
    <?php foreach (['encryption.key','audit.log','error.log','.env','.envad'] as $f): ?>
      <tr>
        <td><?= $f ?></td>
        <td><?= statusBadge($files[$f]['exists']) ?></td>
        <td><?= statusBadge($files[$f]['writable']) ?></td>
        <td><?= getFilePerms($f) ?></td>
        <td><?= $files[$f]['size'] ?> B</td>
        <td><?= $files[$f]['enc'] ?? '-' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Server Information -->
  <table class="table table-sm table-bordered bg-white shadow-sm w-100">
    <thead class="table-light"><tr><th colspan="2">Server Information</th></tr></thead>
    <tr><td>PHP Version</td><td><?= phpversion() ?></td></tr>
    <tr><td>Web Server</td><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ?></td></tr>
    <tr><td>Operating System</td><td><?= PHP_OS ?></td></tr>
    <tr><td>PHPMailer Status</td><td><?= statusBadge($mailerStatus === "Yes") ?> – <?= implode(', ', $mailerDiag) ?></td></tr>
  </table>

  <!-- Datenbank Zusammenfassung -->
  <table class="table table-sm table-bordered bg-white shadow-sm w-100">
    <thead class="table-light"><tr><th colspan="2">Database Summary</th></tr></thead>
    <tr><td>Total Users</td><td><?= $totalUsers ?></td></tr>
    <tr><td>Inactive Users</td><td><?= $inactiveUsers ?></td></tr>
    <tr><td>Admins</td><td><?= $adminUsers ?></td></tr>
    <tr><td>Saved Links</td><td><?= $totalLinks ?></td></tr>
    <tr><td>Open Link Requests</td><td><?= $openRequests ?></td></tr>
    <tr><td>Active Sessions</td><td><?= $active ?> (<?= $admins ?> admins, <?= $users ?> users)</td></tr>
  </table>

</div>
</div>
</body>
</html>
