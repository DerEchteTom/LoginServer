<?php
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

$pdo = new PDO('sqlite:cms.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$section = $_GET['section'] ?? 'main';
$stmt = $pdo->prepare("SELECT section_name FROM page_content");
$stmt->execute();
$sections = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($section, $sections)) $section = 'main';

$stmt = $pdo->prepare("SELECT text_content FROM page_content WHERE section_name = ?");
$stmt->execute([$section]);
$contentHTML = $stmt->fetchColumn() ?: '';

// Feedback-Handling
$feedback = $_SESSION['feedback'] ?? '';
unset($_SESSION['feedback']);

// Fetch max scaling value
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'image_max_scaling'");
$stmt->execute();
$max_scaling = $stmt->fetchColumn() ?? '300'; // Standardwert

// Fetch scaling options
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'image_scaling_options'");
$stmt->execute();
$scaling_options = explode(',', $stmt->fetchColumn() ?? '100,150,200,300,400'); // Default options

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Save content
    if (isset($_POST['quill_html'])) {
        $html = $_POST['quill_html'];
        $stmt = $pdo->prepare("UPDATE page_content SET text_content = :html WHERE section_name = :section");
        if ($stmt->execute([':html' => $html, ':section' => $section])) {
            log_audit("Content updated for section '$section'");
            $_SESSION['feedback'] = "Content updated.";
            header("Location: cms_edit.php?section=" . urlencode($section));
            exit;
        }
    }

    // Create new section
    if (isset($_POST['new_section'], $_POST['create_section'])) {
        $new = trim($_POST['new_section']);
        if (preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $new)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_content WHERE section_name = ?");
            $stmt->execute([$new]);
            if ($stmt->fetchColumn() == 0) {
                $pdo->prepare("INSERT INTO page_content (section_name, text_content) VALUES (?, '')")->execute([$new]);
                $_SESSION['feedback'] = "Section '$new' created.";
                header("Location: cms_edit.php?section=" . urlencode($new));
                exit;
            }
        }
    }

    // Delete section
    if (isset($_POST['delete_section'])) {
        if ($section !== 'main') {
            $pdo->prepare("DELETE FROM page_content WHERE section_name = ?")->execute([$section]);
            log_audit("Section deleted: $section");
            $_SESSION['feedback'] = "Section deleted.";
            header("Location: cms_edit.php?section=main");
            exit;
        }
    }

    // Update timer
    if (isset($_POST['update_timer'], $_POST['new_timer'])) {
        $val = (int) $_POST['new_timer'];
        if ($val >= 0 && $val <= 9999) {
            $stmt = $pdo->prepare("REPLACE INTO settings (setting_name, setting_value) VALUES ('redirect_timer', ?)");
            $stmt->execute([$val]);
            $_SESSION['feedback'] = "Timer updated to $val.";
            log_audit("Timer updated to $val");
            header("Location: cms_edit.php?section=" . urlencode($section));
            exit;
        }
    }

    // Update image scaling
    if (isset($_POST['update_scaling'], $_POST['max_scaling'])) {
        $val = (int) $_POST['max_scaling'];
        if (in_array($val, $scaling_options)) {
            $stmt = $pdo->prepare("REPLACE INTO settings (setting_name, setting_value) VALUES ('image_max_scaling', ?)");
            $stmt->execute([$val]);
            $_SESSION['feedback'] = "Image scaling updated to $val.";
            log_audit("Image scaling updated to $val");
            header("Location: cms_edit.php?section=" . urlencode($section));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit CMS Content</title>
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
  <style>
    #editor-container { height: 500px; background: white; border-radius: 6px; }
  </style>
</head>
<body class="bg-light p-4">
<div class="container-fluid mt-4">
<?php include "admin_tab_nav.php"; ?>
<div style="width: 90%; margin: 0 auto;">
  <h3>Edit miniCMS Content</h3>

  <?php if ($feedback): ?>
    <div class="alert alert-info"><?= htmlspecialchars($feedback) ?></div>
  <?php endif; ?>

  <form method="get" class="mb-3">
    <select name="section" class="form-select w-auto d-inline" onchange="this.form.submit()">
      <?php foreach ($sections as $s): ?>
        <option value="<?= htmlspecialchars($s) ?>" <?= $s === $section ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm ms-2">Back</a>
  </form>

  <form method="post" id="save-form">
    <div id="editor-container"></div>
    <input type="hidden" name="quill_html" id="quill_html">
    <input type="hidden" id="editor-content" value="<?= htmlspecialchars($contentHTML) ?>">
    <button class="btn btn-outline-primary mt-3" type="submit">Save Content</button>
  </form>

  <hr>
  <h5>Manage Sections</h5>
  <div class="d-flex flex-wrap gap-2 align-items-center mb-4">
    <form method="post" class="d-flex gap-2">
      <input type="text" name="new_section" class="form-control" placeholder="New section" required pattern="^[a-zA-Z0-9_-]{1,32}$" style="min-width:200px;">
      <button name="create_section" class="btn btn-outline-success btn-sm" style="min-width:120px;">Add Section</button>
    </form>
    <form method="post" class="ms-3">
      <button name="delete_section" class="btn btn-outline-danger btn-sm">Delete Section</button>
    </form>
    <form method="post" class="ms-auto d-flex align-items-center gap-2">
      <label class="form-label m-0">Timer for Dashboard:</label>
      <input type="number" name="new_timer" min="0" max="9999" class="form-control form-control-sm" style="width:80px;" required value="<?php
        $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_name = 'redirect_timer'");
        echo htmlspecialchars($stmt->fetchColumn() ?: '5');
      ?>">
      <button name="update_timer" class="btn btn-sm btn-outline-primary">Save</button>
    </form>

    <!-- Dropdown for Image Scaling -->
    <form method="post" class="d-flex gap-2 ms-3">
      <label class="form-label m-0">Image Scaling:</label>
      <select name="max_scaling" class="form-select form-select-sm" required style="width: 120px;">
        <?php foreach ($scaling_options as $option): ?>
          <option value="<?= htmlspecialchars($option) ?>" <?= $option == $max_scaling ? 'selected' : '' ?>><?= htmlspecialchars($option) ?> x <?= htmlspecialchars($option) ?></option>
        <?php endforeach; ?>
      </select>
      <button name="update_scaling" class="btn btn-sm btn-outline-primary">Save Scaling</button>
    </form>
  </div>
</div>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
const quill = new Quill('#editor-container', {
  theme: 'snow',
  modules: {
  toolbar: [
    [{ 'header': [1, 2, 3, false] }],
    [{ 'font': [] }, { 'size': [] }],
    ['bold', 'italic', 'underline', 'strike'],
    [{ 'color': [] }, { 'background': [] }],
    [{ 'align': [] }],
    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
    [{ 'indent': '-1'}, { 'indent': '+1' }],
    ['link', 'image', 'code-block'],
    ['clean']
  ]
}
});
quill.root.innerHTML = document.getElementById('editor-content').value;
document.getElementById('save-form').addEventListener('submit', function(e) {
  document.getElementById('quill_html').value = quill.root.innerHTML;
});
</script>
</body>
</html>
