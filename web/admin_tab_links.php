<?php
// Datei: admin_tab_links.php â€“ Stand: 2025-04-23  Europe/Berlin

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
requireRole('admin');

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Pagination konfigurieren
$limit = 10; // Anzahl der Links pro Seite
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

$info = '';
$error = '';

$selectedUser = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);

// Links hinzufÃ¼gen oder bearbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_link']) && $selectedUser > 0) {
        $alias = trim($_POST['alias'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if ($alias && $url) {
            $check = $db->prepare("SELECT COUNT(*) FROM user_links WHERE user_id = ? AND alias = ?");
            $check->execute([$selectedUser, $alias]);
            if ($check->fetchColumn() == 0) {
                $stmt = $db->prepare("INSERT INTO user_links (user_id, alias, url) VALUES (?, ?, ?)");
                $stmt->execute([$selectedUser, $alias, $url]);
                $info = "Link hinzugefuegt.";
            } else {
                $error = "Alias '$alias' existiert bereits.";
            }
        } else {
            $error = "Alias und URL erforderlich.";
        }
    } elseif (isset($_POST['update_link'])) {
        $linkId = (int)($_POST['link_id'] ?? 0);
        $alias = trim($_POST['alias'] ?? '');
        $url = trim($_POST['url'] ?? '');
        if ($linkId > 0 && $alias && $url) {
            $stmt = $db->prepare("UPDATE user_links SET alias = ?, url = ? WHERE id = ?");
            $stmt->execute([$alias, $url, $linkId]);
            $info = "Link aktualisiert.";
        }
    } elseif (isset($_POST['delete_link'])) {
        $linkId = (int)($_POST['link_id'] ?? 0);
        if ($linkId > 0) {
            $stmt = $db->prepare("DELETE FROM user_links WHERE id = ?");
            $stmt->execute([$linkId]);
            $info = "Link gelÃ¶scht.";
        }
    } elseif (isset($_POST['import_default']) && $selectedUser > 0) {
        $file = 'default_links.json';
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
            if (is_array($json)) {
                foreach ($json as $entry) {
                    $alias = $entry['alias'] ?? '';
                    $url = $entry['url'] ?? '';
                    if ($alias && $url) {
                        $exists = $db->prepare("SELECT COUNT(*) FROM user_links WHERE user_id = ? AND alias = ?");
                        $exists->execute([$selectedUser, $alias]);
                        if ($exists->fetchColumn() == 0) {
                            $stmt = $db->prepare("INSERT INTO user_links (user_id, alias, url) VALUES (?, ?, ?)");
                            $stmt->execute([$selectedUser, $alias, $url]);
                        }
                    }
                }
                $info = "Standardlinks importiert.";
            } else {
                $error = "Fehler beim Lesen der JSON-Datei.";
            }
        }
    } elseif (isset($_FILES['json_file']) && $selectedUser > 0) {
    if (!empty($_FILES['json_file']['tmp_name']) && is_uploaded_file($_FILES['json_file']['tmp_name'])) {
        $jsonData = file_get_contents($_FILES['json_file']['tmp_name']);
        $json = json_decode($jsonData, true);
        if (is_array($json)) {
            foreach ($json['links'] ?? [] as $entry) {
                $alias = $entry['alias'] ?? '';
                $url = $entry['url'] ?? '';
                if ($alias && $url) {
                    $exists = $db->prepare("SELECT COUNT(*) FROM user_links WHERE user_id = ? AND alias = ?");
                    $exists->execute([$selectedUser, $alias]);
                    if ($exists->fetchColumn() == 0) {
                        $stmt = $db->prepare("INSERT INTO user_links (user_id, alias, url) VALUES (?, ?, ?)");
                        $stmt->execute([$selectedUser, $alias, $url]);
                    }
                }
            }
            $info = "Links aus JSON importiert.";
        } else {
            $error = "Ungueltige JSON-Datei.";
        }
    } else {
        $error = "Keine gueltige Datei hochgeladen.";
    }
}
if (isset($_POST['export_links']) && $selectedUser > 0) {
    $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$selectedUser]);
    $username = strtolower($stmt->fetchColumn());

    $stmt = $db->prepare("SELECT alias, url FROM user_links WHERE user_id = ?");
    $stmt->execute([$selectedUser]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $export = [
        'users' => [$username],
        'links' => array_map(function ($entry) {
            return [
                'alias' => $entry['alias'],
                'url' => $entry['url']
            ];
        }, $entries)
    ];

    $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    $filename = 'links_' . $username . '_' . date('Y-m-d_His') . '.json';
    header('Content-Type: application/json');
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo $json;
    exit;
}

}

// Daten abrufen
$users = $db->query("SELECT id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
$links = [];
$total = 0;

if ($selectedUser > 0) {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM user_links WHERE user_id = ?");
    $countStmt->execute([$selectedUser]);
    $total = $countStmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM user_links WHERE user_id = ? LIMIT ? OFFSET ?");
    $stmt->execute([$selectedUser, $limit, $offset]);
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Benutzer-Links</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
<?php include "admin_tab_nav.php"; ?>
<div style="width: 90%; margin: 0 auto;">

    <h4 class="mb-3">manage users - links</h4>

    <?php if ($info): ?><div class="alert alert-success small"><?= htmlspecialchars($info) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" class="mb-3">
        <label for="user_id" class="form-label">select user:</label>
        <select name="user_id" id="user_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0">-- please select --</option>
            <?php foreach ($users as $user): ?>
                <option value="<?= $user['id'] ?>" <?= $selectedUser == $user['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($user['username']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($selectedUser): ?>
        <form method="post" class="d-flex gap-2 mb-3" enctype="multipart/form-data">
            <input type="hidden" name="user_id" value="<?= $selectedUser ?>">
            <button name="import_default" class="btn btn-sm btn-outline-primary">import deafult links</button>
            <input type="file" name="json_file" accept=".json" class="form-control form-control-sm w-auto">
            <button type="submit" class="btn btn-sm btn-outline-primary">upload & import</button>
            <input type="hidden" name="user_id" value="<?= $selectedUser ?>">
            <button name="export_links" class="btn btn-sm btn-outline-success">export links as JSON</button>
        </form>

        <form method="post" class="row g-2 mb-4 align-items-center">
            <input type="hidden" name="user_id" value="<?= $selectedUser ?>">
            <div class="col"><input type="text" name="alias" class="form-control form-control-sm" placeholder="insert alias ..." required></div>
            <div class="col"><input type="url" name="url" class="form-control form-control-sm" placeholder="https://... " required></div>
            <div class="col-auto">
                <button type="submit" name="add_link" class="btn btn-sm btn-outline-primary" style="width: 150px;">add new link</button>
            </div>
        </form>

        <?php foreach ($links as $link): ?>
            <form method="post" class="row g-2 align-items-center mb-2">
                <input type="hidden" name="user_id" value="<?= $selectedUser ?>">
                <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                <div class="col"><input type="text" name="alias" class="form-control form-control-sm" value="<?= htmlspecialchars($link['alias']) ?>"></div>
                <div class="col"><input type="url" name="url" class="form-control form-control-sm" value="<?= htmlspecialchars($link['url']) ?>"></div>
                <div class="col-auto">
                    <button name="update_link" class="btn btn-sm btn-outline-primary">save</button>
                    <button name="delete_link" class="btn btn-sm btn-outline-danger">delete</button>
                </div>
            </form>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total > $limit): ?>
            <nav><ul class="pagination pagination-sm mt-3">
                <?php for ($i = 1; $i <= ceil($total / $limit); $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?user_id=<?= $selectedUser ?>&page=<?= $i ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul></nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>