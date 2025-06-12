<?php
// admin_images.php – Übersicht aller Upload-Bilder mit Löschfunktion
require_once 'config.php';
require_once 'auth.php';
requireRole('admin');

$uploadDir = __DIR__ . './uploads';
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['filename'])) {
    $filename = basename($_POST['filename']);
    $filepath = $uploadDir . $filename;
    if (file_exists($filepath) && is_file($filepath)) {
        if (unlink($filepath)) {
            $messages[] = "Bild <strong>$filename</strong> wurde gelöscht.";
        } else {
            $messages[] = "Fehler beim Löschen von <strong>$filename</strong>.";
        }
    }
}

$images = glob($uploadDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Image Manager</title>
  <link rel="stylesheet" href="./assets/css/bootstrap.min.css">
  <style>
    .thumb { max-width: 150px; height: auto; margin-bottom: 10px; border-radius: 6px; }
  </style>
</head>
<body class="p-4 bg-light">
  <div class="container">
    <h3>Uploaded Images</h3>
    <?php foreach ($messages as $msg): ?>
      <div class="alert alert-info"><?= $msg ?></div>
    <?php endforeach; ?>

    <div class="row">
      <?php foreach ($images as $img): 
        $filename = basename($img);
        ?>
        <div class="col-md-3 mb-4">
          <img src="./uploads<?= $filename ?>" alt="<?= $filename ?>" class="thumb img-thumbnail">
          <form method="POST">
            <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
            <button name="delete" class="btn btn-sm btn-outline-danger mt-2 w-100">Delete</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <a href="dashboard.php" class="btn btn-outline-secondary mt-4">Back to Dashboard</a>
  </div>
</body>
</html>
