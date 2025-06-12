<?php
// Datei: linkeditor_import.php â€“ Stand: 2025-05-16 Europe/Berlin
date_default_timezone_set('Europe/Berlin');
require_once 'auth.php';
requireRole('admin');
require_once 'config_support.php';

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$feedback = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = trim($_POST['json_data'] ?? '');
    if (!$raw) {
        $errors[] = "No JSON data submitted.";
    } else {
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['users']) || !isset($data['links'])) {
            $errors[] = "Invalid format: JSON must include 'users' and 'links'.";
        } else {
            $users = $data['users'];
            $links = $data['links'];

            if ($users === "all") {
                $usernames = $db->query("SELECT username FROM users")->fetchAll(PDO::FETCH_COLUMN);
            } elseif (is_array($users)) {
                $usernames = $users;
            } else {
                $errors[] = "Invalid 'users' value â€“ must be 'all' or an array.";
                $usernames = [];
            }

            $inserted = 0;
            $skipped = 0;
            foreach ($usernames as $username) {
                $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(:u)");
                $stmt->execute([':u' => $username]);
                $uid = $stmt->fetchColumn();
                if (!$uid) {
                    $errors[] = "User '$username' not found.";
                    continue;
                }

                foreach ($links as $entry) {
                    $alias = strtolower($entry['alias'] ?? '');
                    $url = strtolower($entry['url'] ?? '');
                    if (!$alias || !$url) continue;

                    $exists = $db->prepare("SELECT COUNT(*) FROM user_links WHERE user_id = ? AND alias = ?");
                    $exists->execute([$uid, $alias]);
                    if ($exists->fetchColumn() == 0) {
                        $add = $db->prepare("INSERT INTO user_links (user_id, alias, url) VALUES (?, ?, ?)");
                        $add->execute([$uid, $alias, $url]);
                        $inserted++;
                    } else {
                        $skipped++;
                    }
                }
            }

            $feedback = "$inserted links added, $skipped skipped (duplicates).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Import JSON Links</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
  <?php include "admin_tab_nav.php"; ?>
  <h4 class="mb-3">Link Assignment via JSON</h4>
  <?php if ($feedback): ?>
    <div class="alert alert-success"><?= htmlspecialchars($feedback) ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <strong>Error during import:</strong><br>
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post">
    <label for="json_data" class="form-label">Paste JSON here:</label>
    <textarea name="json_data" id="json_data" rows="15" class="form-control font-monospace" placeholder='{"users":["admin"],"links":[{"alias":"heise","url":"https://heise.de"}]}' required><?= htmlspecialchars($_POST['json_data'] ?? '') ?></textarea>

    <div class="mt-3">
      <button type="submit" class="btn btn-outline-primary">Import Now</button>
    </div>
  </form>

  <p class="text-muted mt-4 small">
    This tool allows manual insertion of JSON link assignments. Supports both specific usernames and <code>"users": "all"</code>.
  </p>
</div>
</body>
</html>
