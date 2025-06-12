<?php
// Datei: admin_tab_status.php – Stand: 2025-06-12
session_start();
date_default_timezone_set('Europe/Berlin');

// --- Helper Functions ---
function checkFile($path) {
    return [
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'writable' => is_writable($path),
        'size' => file_exists($path) ? filesize($path) : 0
    ];
}

function statusBadge($ok) {
    return $ok ? '<span class="badge border border-success text-success">Yes</span>'
               : '<span class="badge border border-danger text-danger">No</span>';
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

function getEncStatus($file) {
    if (!file_exists($file)) return "-";
    $txt = file_get_contents($file);
    if (strpos($txt, 'ENC:') !== false || strpos($txt, 'XOR:') !== false) return "yes";
    return "no";
}

function getMailerStatus() {
    $status = "No";
    $diag = [];
    $autoloadPath = __DIR__ . '/vendor/autoload.php';
    $cls = 'PHPMailer\\PHPMailer\\PHPMailer';

    if (file_exists($autoloadPath)) {
        $diag[] = "Autoload available";
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
        $diag[] = "Autoload not found";
    }

    return [$status, $diag];
}

// --- File Checks ---
$files = [
    'users.db' => checkFile('users.db'),
    'cms.db' => checkFile('cms.db'),
    'encryption.key' => checkFile('encryption.key'),
    'audit.log' => checkFile('audit.log'),
    'error.log' => checkFile('error.log'),
    '.env' => checkFile('.env'),
    '.envad' => checkFile('.envad')
];

foreach (['.env', '.envad'] as $f) {
    $files[$f]['enc'] = getEncStatus($f);
}

foreach (['users.db', 'cms.db'] as $db) {
    $files[$db]['chmod'] = getFilePerms($db);
    $files[$db]['pdo_error'] = '';
    $files[$db]['pdo'] = testPdoWrite($db, $files[$db]['pdo_error']);
}

// Mail test
list($mailerStatus, $mailerDiag) = getMailerStatus();

// Session count
$active = $admins = $users = 0;
$sessionDir = session_save_path();
if (!empty($sessionDir) && is_dir($sessionDir)) {
    foreach (scandir($sessionDir) as $file) {
        if (strpos($file, 'sess_') === 0) {
            $data = @file_get_contents("$sessionDir/$file");
            if ($data && preg_match('/role\|s:\d+:"(admin|user)"/', $data, $m)) {
                $active++;
                if ($m[1] === 'admin') $admins++;
                if ($m[1] === 'user') $users++;
            }
        }
    }
}

// DB Summary
try {
    $db = new PDO("sqlite:users.db");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $inactiveUsers = $db->query("SELECT COUNT(*) FROM users WHERE active = 0")->fetchColumn();
    $adminUsers = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $totalLinks = $db->query("SELECT COUNT(*) FROM user_links")->fetchColumn();
    $openRequests = $db->query("SELECT COUNT(*) FROM link_requests WHERE status = 'open'")->fetchColumn();
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
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
<style>
/* Typ 1: Erste Spalte fix 15 %, weitere Spalten dynamisch */
.table-type1 {
  table-layout: fixed;
  width: 100%;
}

.table-type1 th:first-child,
.table-type1 td:first-child {
  width: 20%;
  white-space: nowrap;
}

.table-type1 th:not(:first-child),
.table-type1 td:not(:first-child) {
  white-space: normal;
}

/* Typ 2: Zwei-Spalten-Tabelle mit fixer erster Spalte */
.table-type2 {
  table-layout: fixed;
  width: 100%;
}

.table-type2 th:first-child,
.table-type2 td:first-child {
  width: 20%;
  white-space: nowrap;
  font-weight: normal; /* Schrift nicht fett */
}

.tooltip-box {
  background-color: #f1f1f1;
  border: 1px solid #aaa;
  padding: 6px 10px;
  border-radius: 5px;
  color: #333;
  font-size: 0.85rem;
  max-width: 80%;
  margin: 8px auto;
  display: none;
}

</style>
  <script>
    function toggleTooltip(id) {
      const el = document.getElementById(id);
      el.style.display = (el.style.display === 'block') ? 'none' : 'block';
    }
  </script>
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
<?php include "admin_tab_nav.php"; ?>
<div style="width: 80%; margin: 0 auto;">

  <h5 class="mb-3">System Status</h5>
  <p><strong>Logged in:</strong> <?= htmlspecialchars($username) ?> (Session-ID: <?= session_id() ?>)</p>

  <h5>Database Files</h5>
  <table class="table table-sm table-bordered bg-white table-type1">
    <thead class="table-light">
      <tr>
        <th>File</th><th>Exists</th><th>Writable</th><th>CHMOD</th><th>Size</th><th>PDO-Write</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (['users.db','cms.db'] as $db): ?>
        <tr>
          <td><?= $db ?></td>
          <td><?= statusBadge($files[$db]['exists']) ?></td>
          <td><?= statusBadge($files[$db]['writable']) ?></td>
          <td><?= $files[$db]['chmod'] ?></td>
          <td><?= $files[$db]['size'] ?> B</td>
          <td>
            <?= statusBadge($files[$db]['pdo']) ?>
            <?php if (!$files[$db]['pdo']): ?>
            <a href="javascript:void(0)"
            class="btn btn-outline-secondary btn-sm ms-2 px-2 py-0"
            onclick="toggleTooltip('err-<?= $db ?>')"
            title="Show error">?</a>
  <div class="tooltip-box" id="err-<?= $db ?>"><?= htmlspecialchars($files[$db]['pdo_error']) ?></div>
<?php endif; ?>

          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <h5>Other Files</h5>
  <table class="table table-sm table-bordered bg-white table-type1">
    <thead class="table-light">
      <tr>
        <th>File</th><th>Exists</th><th>Writable</th><th>CHMOD</th><th>Size</th><th>Encrypted</th>
      </tr>
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

  <h5>Server Information</h5>
  <table class="table table-sm table-bordered bg-white table-type2">
  <tr><th>PHP Version</th><td><?= phpversion() ?></td></tr>
  <tr><th>Web Server</th><td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ?></td></tr>
  <tr><th>Operating System</th><td><?= PHP_OS ?></td></tr>
  <tr><th>PHPMailer</th><td><?= statusBadge($mailerStatus === "Yes") ?> – <?= implode(", ", $mailerDiag) ?></td></tr> 
  </table>

  <h5>User & Link Summary</h5>
  <table class="table table-sm table-bordered bg-white table-type2">
  <tr><th>Total users</th><td><?= $totalUsers ?></td></tr>
  <tr><th>Inactive users</th><td><?= $inactiveUsers ?></td></tr>
  <tr><th>Admin users</th><td><?= $adminUsers ?></td></tr>
  <tr><th>Saved links</th><td><?= $totalLinks ?></td></tr>
  <tr><th>Open link requests</th><td><?= $openRequests ?></td></tr>
  <tr><th>Session Check</th>
  <td><a href="session_check.php" class="btn btn-outline-primary btn-sm">Open Diagnostic</a></td></tr>
 </table>
</div>
</div>
</body>
</html>
