<?php
// image_gallery_test.php – einfache Bildanzeige zum Testen
$uploadDir = __DIR__ . '/uploads/';
$imageFiles = glob($uploadDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Image Gallery Test</title>
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .img-thumb { height: 120px; object-fit: cover; }
  </style>
</head>
<body class="bg-light p-4">
<div class="container">
  <h3>Gallery Test: /uploads/</h3>
  <?php if (empty($imageFiles)): ?>
    <p>No images found in uploads/.</p>
  <?php else: ?>
    <div class="row g-2">
    <?php foreach ($imageFiles as $path):
      $file = basename($path); ?>
      <div class="col-auto text-center" style="width: 130px;">
        <img src="/uploads/<?= htmlspecialchars($file) ?>" class="img-thumbnail img-thumb mb-1">
        <div class="text-muted small"><?= htmlspecialchars($file) ?></div>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
