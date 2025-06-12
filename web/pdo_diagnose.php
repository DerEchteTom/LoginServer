<?php
// Datei: pdo_diagnose.php
date_default_timezone_set('Europe/Berlin');

$inputName = $_GET['file'] ?? '';
$filename = $inputName !== '' ? basename($inputName) : 'users.db';
$fullPath = __DIR__ . '/' . $filename;

$error = '';
$result = '';
$pdoOK = false;

function testPdoWrite($file, &$errorOut) {
    try {
        $pdo = new PDO("sqlite:$file");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("CREATE TABLE IF NOT EXISTS _writetest (id INTEGER)");
        $pdo->exec("DROP TABLE IF EXISTS _writetest");
        return true;
    } catch (Exception $e) {
        $errorOut = $e->getMessage();
        return false;
    }
}

if (file_exists($fullPath)) {
    $pdoOK = testPdoWrite($fullPath, $error);
    $fileSize = filesize($fullPath);
    $writable = is_writable($fullPath);
    $chmod = substr(sprintf('%o', fileperms($fullPath)), -3);
} else {
    $error = "File does not exist.";
    $fileSize = 0;
    $writable = false;
    $chmod = '---';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>PDO Write Test</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width: 700px;">
  <h4>SQLite PDO Write Test</h4>
  <form method="get" class="mb-4">
    <label for="file">Database file name:</label>
    <div class="input-group">
      <input type="text" name="file" id="file" class="form-control" placeholder="e.g. users.db" value="<?= htmlspecialchars($filename) ?>">
      <button type="submit" class="btn btn-outline-primary">Test</button>
    </div>
  </form>

  <table class="table table-bordered bg-white">
    <tr><th>File</th><td><?= htmlspecialchars($filename) ?></td></tr>
    <tr><th>Exists</th><td><?= file_exists($fullPath) ? 'Yes' : 'No' ?></td></tr>
    <tr><th>Writable</th><td><?= $writable ? 'Yes' : 'No' ?></td></tr>
    <tr><th>CHMOD</th><td><?= $chmod ?></td></tr>
    <tr><th>Size</th><td><?= $fileSize ?> B</td></tr>
    <tr><th>PDO Write</th><td><?= $pdoOK ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?></td></tr>
    <?php if (!$pdoOK && $error): ?>
      <tr><th>Error</th><td><pre class="text-danger"><?= htmlspecialchars($error) ?></pre></td></tr>
    <?php endif; ?>
  </table>

  <p class="text-muted small">Only files in the current directory are allowed. No subfolders permitted.</p>
</div>
</body>
</html>
