<?php
// Datei: links.php â€“ Stand: 2025-04-22 11:20 Europe/Berlin

date_default_timezone_set('Europe/Berlin');
session_start();

require_once __DIR__ . '/config.php';

$error = '';
$info = '';

// Nutzerkennung ermitteln
$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$username = $_SESSION['username'] ?? '';
$stmt = $db->prepare("SELECT * FROM users WHERE username = :u");
$stmt->execute([':u' => $username]);
$userdata = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userdata) {
    header("Location: login.php");
    exit;
}

$uid = $userdata['id'];
$username = $userdata['username'];

// Linkanfrage verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['alias'], $_POST['url'])) {
    $alias = trim($_POST['alias']);
    $url = trim($_POST['url']);

    if ($alias && $url && filter_var($url, FILTER_VALIDATE_URL)) {
        try {
            $stmt = $db->prepare("INSERT INTO link_requests (user_id, alias, url, created_at, status) VALUES (:uid, :a, :u, :c, 'open')");
            $stmt->execute([
                ':uid' => $uid,
                ':a' => $alias,
                ':u' => $url,
                ':c' => date('Y-m-d H:i:s')
            ]);
            $info = "Anfrage wurde Ã¼bermittelt. Ein Admin wird sie prÃ¼fen.";
            @file_put_contents("audit.log", date('c') . " Linkanfrage von Benutzer $username (ID $uid): $alias â†’ $url
", FILE_APPEND);
        } catch (Exception $e) {
            $error = "Fehler beim Speichern der Anfrage.";
            @file_put_contents("error.log", date('c') . " Fehler bei Linkanfrage $username: " . $e->getMessage() . "
", FILE_APPEND);
        }
    } else {
        $error = "Bitte gÃ¼ltigen Alias und vollstÃ¤ndige URL eingeben.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Meine Links</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4" style="max-width:700px;">
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Links von <?= htmlspecialchars($username) ?></h5>
            <ul class="list-group mb-3">
                <?php
                $links = $db->prepare("SELECT * FROM user_links WHERE user_id = :uid ORDER BY alias ASC");
                $links->execute([':uid' => $uid]);
                foreach ($links as $link): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?= htmlspecialchars($link['alias']) ?></span>
                        <a href="<?= htmlspecialchars($link['url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">open</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="text-muted small">Fehlt ein Link? Fordere ihn unten an:</p>

            <?php if ($info): ?><div class="alert alert-success small"><?= htmlspecialchars($info) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="post" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="alias" class="form-control form-control-sm" placeholder="alias" required>
                </div>
                <div class="col-md-5">
                    <input type="url" name="url" class="form-control form-control-sm" placeholder="https://..." required>
                </div>
                <div class="col-md-2 d-grid">
                    <button type="submit" class="btn btn-sm btn-outline-success">send request</button>
                </div>
            </form>
        </div>
    </div>
    <a href="logout.php" class="btn btn-outline-dark btn-sm">logout</a>
</div>
</body>
</html>
