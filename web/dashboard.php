<?php
// dashboard.php – Darstellung aller gefüllten CMS-Sektionen
session_start();
date_default_timezone_set('Europe/Berlin');
require_once 'config.php';
require_once 'auth.php';

$isAdmin = isAuthenticated() && hasRole("admin");
$username = $_SESSION['username'] ?? 'guest';
$role = $_SESSION['role'] ?? 'user';
$target = ($role === 'admin') ? 'admin.php' : 'links.php';

$pdo = new PDO('sqlite:cms.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Timer laden (default: 5s)
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'redirect_timer'");
$stmt->execute();
$delay = (int) ($stmt->fetchColumn() ?? 5);

// Maximalen Skalierungswert für Bilder laden
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = 'image_max_scaling'");
$stmt->execute();
$max_scaling = (int) ($stmt->fetchColumn() ?? 300); // Standardwert ist 300

// Nur Sektionen mit Inhalt laden (außer 'Main')
$stmt = $pdo->query("SELECT section_name, text_content, image_path FROM page_content ORDER BY section_name ASC");
$sections = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Überprüfen, ob der Inhalt leer ist (Platzhalterwert <p><br></p> oder leere Felder)
    $isEmptyContent = trim($row['text_content']) === '<p><br></p>' || empty($row['text_content']);
    $isEmptyImage = empty($row['image_path']);

    // Sektionen ohne Inhalt (leer oder nur Platzhalter) ausblenden
    if (!$isEmptyContent || !$isEmptyImage) {
        $sections[$row['section_name']] = [
            'text_content' => $row['text_content'],
            'image_path' => $row['image_path']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .ql-editor img {
      max-width: <?= $max_scaling ?>px;
      max-height: <?= $max_scaling ?>px;
      width: auto;
      height: auto;
    }
    .ql-editor {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      margin-bottom: 30px;
      box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }
  </style>
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width: 90%;">

  <h4 class="mb-3">Welcome, <?= htmlspecialchars($username) ?> <?= $isAdmin ? '(Admin)' : '' ?></h4>
  <p class="text-muted">You will be redirected in <span id="timer"><?= $delay ?></span> seconds.</p>
  <div class="d-flex gap-2 mb-4">
    <button onclick="pauseRedirect()" class="btn btn-sm btn-outline-warning">Pause Redirect</button>
    <button onclick="resumeRedirect()" class="btn btn-sm btn-outline-success" id="resume-btn" style="display: none;">Resume Redirect</button>
    <a href="<?= htmlspecialchars($target) ?>" class="btn btn-sm btn-outline-primary" id="go-now-btn">Go Now</a>
    <?php if ($isAdmin): ?>
      <a href="cms_edit.php" class="btn btn-sm btn-outline-success">Edit CMS Content</a>
    <?php endif; ?>
  </div>

  <!-- CMS-Inhalte -->
  <?php if (empty($sections)): ?>
    <p class="text-muted">No content available.</p>
  <?php else: ?>
    <?php foreach ($sections as $section => $data): ?>
      <div class="ql-editor">
        <!-- Anzeige des Inhalts -->
        <?php if (!empty($data['text_content']) || !empty($data['image_path'])): ?>
          <div>
            <?= !empty($data['text_content']) ? $data['text_content'] : '' ?>
            <?php if (!empty($data['image_path'])): ?>
              <img src="<?= htmlspecialchars($data['image_path']) ?>" alt="Image for <?= htmlspecialchars($section) ?>">
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

</div>

<script>
let timerElement = document.getElementById("timer");
let redirectTimeout;
let remainingTime = <?= $delay ?>;
let isPaused = false; // Zustand für den Timer (gestartet oder pausiert)

function startRedirect() {
  const interval = setInterval(() => {
    if (remainingTime > 0 && !isPaused) {
      remainingTime--;
      timerElement.textContent = remainingTime;
    }
  }, 1000);

  redirectTimeout = setTimeout(() => {
    window.location.href = <?= json_encode($target) ?>;
  }, remainingTime * 1000);
}

function pauseRedirect() {
  clearTimeout(redirectTimeout); // Stoppt die Weiterleitung
  isPaused = true; // Timer pausieren
  document.getElementById("go-now-btn").style.display = "inline-block"; // "Go Now" Button anzeigen
  document.getElementById("resume-btn").style.display = "inline-block"; // "Resume" Button anzeigen
  document.getElementById("timer").textContent = '⏸️'; // Anzeige von "Pause"
}

function resumeRedirect() {
  if (isPaused) {
    isPaused = false; // Timer fortsetzen
    document.getElementById("go-now-btn").style.display = "none"; // "Go Now" Button ausblenden
    document.getElementById("resume-btn").style.display = "none"; // "Resume" Button ausblenden
    document.getElementById("timer").textContent = remainingTime; // Timer fortsetzen
    startRedirect(); // Timer wieder starten
  }
}

window.onload = startRedirect;
</script>
</body>
</html>
