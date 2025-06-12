<?php
// cms_edit.php â€“ CMS mit Editor, Sektionen, Bildverwaltung, Timer und Logging
session_start();
date_default_timezone_set('Europe/Berlin');
require_once 'config.php';
require_once 'auth.php';
requireRole('admin');

function log_audit($msg) {
    file_put_contents(__DIR__ . '/audit.log', "[" . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}
function log_error($msg) {
    file_put_contents(__DIR__ . '/error.log', "[" . date('Y-m-d H:i:s') . "] ERROR: $msg\n", FILE_APPEND);
}

// === Datenbank & Sektionen ===
$pdo = new PDO('sqlite:cms.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$availableSections = [];
$stmt = $pdo->query("SELECT section_name FROM page_content ORDER BY section_name ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $availableSections[] = $row['section_name'];
}
$section = $_GET['section'] ?? 'main';
if (!in_array($section, $availableSections)) {
    $section = 'main';
}

$stmt = $pdo->prepare("SELECT text_content FROM page_content WHERE section_name = ?");
$stmt->execute([$section]);
$contentHTML = $stmt->fetchColumn() ?? '';

// Feedback nach dem letzten Redirect holen
$feedback = $_SESSION['feedback'] ?? '';
unset($_SESSION['feedback']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['quill_html'])) {
        $html = $_POST['quill_html'];
        $stmt = $pdo->prepare("UPDATE page_content SET text_content = :html WHERE section_name = :section");

        if ($stmt->execute([':html' => $html, ':section' => $section])) {
            log_audit("Content updated for section '$section'");
            // cleanUnusedImages($html, __DIR__ . '/uploads'); // aktuell deaktiviert
            $_SESSION['feedback'] = "Content saved successfully for section '$section'.";
            header("Location: cms_edit.php?section=" . urlencode($section));
            exit;
        } else {
            log_error("Failed to update content for section '$section'");
            $feedback = "Failed to save content.";
        }
    }
}


    if (isset($_POST['create_section'], $_POST['new_section'])) {
        $newSection = trim($_POST['new_section']);
        if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $newSection)) {
            $feedback = "Invalid section name.";
            log_error("Invalid section name attempted: '$newSection'");
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_content WHERE section_name = ?");
            $stmt->execute([$newSection]);
            if ($stmt->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO page_content (section_name, text_content) VALUES (?, '')")->execute([$newSection]);
                $availableSections[] = $newSection;
                $feedback = "Section '$newSection' created.";
                log_audit("New section created: '$newSection'");
                header("Location: cms_edit.php?section=" . urlencode($newSection));
                exit;
            } else {
                $feedback = "Section already exists.";
            }
        }
    }

    if (isset($_POST['update_timer'], $_POST['new_timer'])) {
        $timerValue = (int) $_POST['new_timer'];
        if ($timerValue >= 0 && $timerValue <= 9999) {
            $stmt = $pdo->prepare("REPLACE INTO settings (setting_name, setting_value) VALUES ('redirect_timer', ?)");
            $stmt->execute([$timerValue]);
            $feedback = "Timer updated to $timerValue seconds.";
            log_audit("Redirect timer updated to $timerValue seconds.");
        } else {
            $feedback = "Invalid timer value.";
            log_error("Timer update failed: value '$timerValue' out of bounds.");
        }
    }


function cleanUnusedImages($html, $dir) {
    preg_match_all('/<img[^>]+src="\/uploads\/([^"]+)"/', $html, $matches);
    $used = array_map('basename', $matches[1] ?? []);
    foreach (glob($dir . '/*') as $file) {
        if (is_file($file) && !in_array(basename($file), $used)) {
            unlink($file);
            log_audit("Unused image '" . basename($file) . "' deleted during save.");
        }
    }
}

$uploadDir = __DIR__ . '/uploads/';
// Galerie zeigt nur verwendete Bilder
$stmt = $pdo->query("SELECT text_content FROM page_content");
$usedImages = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    preg_match_all('/<img[^>]+src="\/uploads\/([^"]+)"/', $row['text_content'], $matches);
    if (!empty($matches[1])) {
        $usedImages = array_merge($usedImages, $matches[1]);
    }
}
$usedImages = array_unique(array_map('basename', $usedImages));
$allImages = glob($uploadDir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
$images = array_filter($allImages, function($img) use ($usedImages) {
    return in_array(basename($img), $usedImages);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit CMS Content</title>
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
  <style>
    #editor-container { height: 600px; background: white; border-radius: 8px; }
    .ql-editor img { max-width: 100%; height: auto; border-radius: 6px; }
    .thumb {
  width: 100%;
  height: 100px;
  object-fit: cover;
  border-radius: 4px;
  margin-bottom: 8px;
}
.thumbnail-wrapper {
  width: 150px;
  text-align: center;
} max-width: 150px; height: auto; border-radius: 4px; margin-bottom: 8px; 
  width: 150px;
  height: 100px;
  object-fit: cover;}
  </style>
</head>
<body class="p-4 bg-light">
<div class="container" style="max-width: 90%;">
  <h3>Edit CMS Content</h3>
  <form method="get" class="mb-3">
    <label for="section" class="form-label">Select section:</label>
    <div class="d-flex gap-2">
      <select name="section" id="section" class="form-select w-auto" onchange="this.form.submit()">
        <?php foreach ($availableSections as $sec): ?>
          <option value="<?= htmlspecialchars($sec) ?>" <?= $sec === $section ? 'selected' : '' ?>>
            <?= htmlspecialchars(ucfirst($sec)) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
    </div>
  </form>
</div>

  <?php if ($feedback): ?>
    <div class="alert alert-info"><?= htmlspecialchars($feedback) ?></div>
  <?php endif; ?>

  <form method="POST" id="save-form">
    <div id="editor-container"></div>
    <input type="hidden" name="quill_html" id="quill_html">
    <input type="hidden" id="editor-content" value="<?= htmlspecialchars($contentHTML) ?>">
    <div class="d-flex gap-2 mt-3">
      <button type="submit" class="btn btn-outline-primary">Save Content</button>
    </div>
  </form>
</div>

  <hr class="my-5">
  <h5>Uploaded Images</h5>
  <div class="row">
    <?php foreach ($images as $img): $filename = basename($img); ?>
      <div class="col-sm-4 col-md-2 mb-4">
        <div class="thumbnail-wrapper">
<img src="/uploads/<?= htmlspecialchars($filename) ?>" alt="<?= $filename ?>" class="thumb img-thumbnail">
        <form method="POST" onsubmit="return confirm('Delete image <?= htmlspecialchars($filename) ?>?');">
<input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
          <input type="hidden" name="filename" value="<?= htmlspecialchars($filename) ?>">
          <button name="delete_image" class="btn btn-sm btn-outline-danger w-100 mt-2">Delete</button>
        </form>
</div>
      </div>
    <?php endforeach; ?>
  </div>

  <hr class="my-4">
  <h5>Add New Section</h5>
  <form method="POST" class="row g-2 align-items-center mb-4">
    <div class="col-auto">
      <input type="text" name="new_section" class="form-control" placeholder="New section name" required pattern="^[a-zA-Z0-9_-]{1,32}$">
    </div>
    <div class="col-auto">
      <button name="create_section" class="btn btn-outline-success">Add Section</button>
    </div>
  </form>
</div>

  <h5>Redirect Timer (Seconds)</h5>
  <form method="POST" class="row g-2 align-items-center mb-4">
    <?php
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'redirect_timer'");
    $stmt->execute();
    $currentTimer = $stmt->fetchColumn() ?? '5';
    ?>
    <div class="col-auto">
      <input type="number" name="new_timer" class="form-control" maxlength="4" min="0" max="9999" value="<?= htmlspecialchars($currentTimer) ?>" required>
    </div>
    <div class="col-auto">
      <button name="update_timer" class="btn btn-outline-primary">Update Timer</button>
    </div>
  </form>
</div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
const quill = new Quill('#editor-container', {
  theme: 'snow',
  placeholder: 'Edit content here...',
  modules: {
    toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered' }, { 'list': 'bullet' }], ['image', 'clean']]
  }
});
const initialContent = document.getElementById("editor-content")?.value || '';
quill.root.innerHTML = initialContent;

function uploadImage(file) {
  const formData = new FormData();
  formData.append("image", file);
  return fetch("upload_image.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.json())
  .then(result => {
    if (result.success) {
      const range = quill.getSelection();
      quill.insertEmbed(range.index, 'image', result.url);
    } else {
      alert("Upload failed: " + result.error);
    }
  });
}

quill.getModule('toolbar').addHandler('image', () => {
  const input = document.createElement('input');
  input.setAttribute('type', 'file');
  input.setAttribute('accept', 'image/*');
  input.click();
  input.onchange = () => {
    const file = input.files[0];
    if (file) uploadImage(file);
  };
});

const saveForm = document.getElementById("save-form");
saveForm.addEventListener("submit", function(e) {
  e.preventDefault();
  document.getElementById("quill_html").value = quill.root.innerHTML;
  setTimeout(() => saveForm.submit(), 10);
});
</script>
</body>
</html>
