<?php
// Datei: file_write_debug.php – Fokus auf Benutzerdateien

date_default_timezone_set('Europe/Berlin');

$filename = $_POST['filename'] ?? '';
$result = '';
$raw = '';

function getPermissions($path) {
    return file_exists($path) ? substr(sprintf('%o', fileperms($path)), -3) : '---';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullPath = realpath($filename) ?: $filename;
    $dir = dirname($fullPath);

    $raw .= "Test file: $filename\n";
    $raw .= "Resolved path: $fullPath\n";
    $raw .= "Parent directory: $dir\n";

    $fileExists = file_exists($fullPath);
    $dirWritable = is_writable($dir);
    $raw .= "Directory writable: " . ($dirWritable ? 'Yes' : 'No') . "\n";
    $raw .= "File exists: " . ($fileExists ? 'Yes' : 'No') . "\n";

    if ($fileExists && is_writable($fullPath)) {
        try {
            $old = file_get_contents($fullPath);
            file_put_contents($fullPath, $old . "\n# Test at " . time());
            $written = true;
            $msg = "Append OK to $fullPath";
            $perm = getPermissions($fullPath);
            $size = filesize($fullPath);
        } catch (Exception $e) {
            $written = false;
            $msg = "Write error: " . $e->getMessage();
            $perm = getPermissions($fullPath);
            $size = '-';
        }
    } elseif (!$fileExists && $dirWritable) {
        try {
            file_put_contents($fullPath, "# Created at " . time());
            $written = true;
            $msg = "File created at $fullPath";
            $perm = getPermissions($fullPath);
            $size = filesize($fullPath);
            unlink($fullPath);
        } catch (Exception $e) {
            $written = false;
            $msg = "Create error: " . $e->getMessage();
            $perm = '-';
            $size = '-';
        }
    } else {
        $written = false;
        $msg = "Neither file is writable nor directory is creatable.";
        $perm = file_exists($fullPath) ? getPermissions($fullPath) : '-';
        $size = '-';
    }

    $result .= "<p><strong>File:</strong> " . htmlspecialchars($fullPath) . "</p>";
    $result .= "<p><strong>File exists:</strong> " . ($fileExists ? 'Yes' : 'No') . "</p>";
    $result .= "<p><strong>Directory writable:</strong> " . ($dirWritable ? 'Yes' : 'No') . "</p>";
    $result .= "<p><strong>Write result:</strong> " . ($written ? '<span style=\"color:green\">Success</span>' : '<span style=\"color:red\">Fail</span>') . "</p>";
    $result .= "<p><strong>CHMOD:</strong> $perm</p>";
    $result .= "<p><strong>File size:</strong> $size</p>";

    $raw .= "Write result: " . ($written ? 'Success' : 'Fail') . "\n";
    $raw .= "CHMOD: $perm\n";
    $raw .= "File size: $size\n";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>File Write Debug</title>
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width: 720px;">
  <h5>File Write Test</h5>
  <form method="post" class="mb-4">
    <div class="input-group">
      <input type="text" name="filename" class="form-control" placeholder="Enter filename (e.g. users.db)" required>
      <button type="submit" class="btn btn-outline-primary">Test Write</button>
    </div>
  </form>

  <?php if ($result): ?>
  <div class="card mb-3">
    <div class="card-body">
      <?= $result ?>
    </div>
  </div>
  <h6>Raw Debug Output</h6>
  <textarea class="form-control" rows="10" readonly><?= htmlspecialchars($raw) ?></textarea>
  <?php endif; ?>
</div>
</body>
</html>
