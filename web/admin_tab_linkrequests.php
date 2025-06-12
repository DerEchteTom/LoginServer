<?php
// Datei: admin_tab_linkrequests.php â€“ Stand: 2025-04-22 11:29 Europe/Berlin

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/auth.php';
requireRole('admin');

$db = new PDO('sqlite:users.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$info = '';
$error = '';

// Aktion verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];

    if ($action === 'approve') {
        $stmt = $db->prepare("SELECT * FROM link_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($request) {
            $stmt = $db->prepare("INSERT INTO user_links (user_id, alias, url) VALUES (:uid, :a, :u)");
            $stmt->execute([
                ':uid' => $request['user_id'],
                ':a' => $request['alias'],
                ':u' => $request['url']
            ]);
            $db->prepare("DELETE FROM link_requests WHERE id = :id")->execute([':id' => $id]);
            $info = "Anfrage Ã¼bernommen: " . htmlspecialchars($request['alias']);
            file_put_contents("audit.log", date('c') . " Link freigegeben: " . $request['alias'] . " durch Admin\n", FILE_APPEND);
        }
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("DELETE FROM link_requests WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $info = "Anfrage gelÃ¶scht (ID $id)";
        file_put_contents("audit.log", date('c') . " Linkanfrage gelÃ¶scht ID $id durch Admin\n", FILE_APPEND);
    }
}

$requests = $db->query("SELECT lr.id, lr.alias, lr.url, u.username FROM link_requests lr JOIN users u ON u.id = lr.user_id ORDER BY lr.id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>link requests</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
<?php include "admin_tab_nav.php"; ?>
<div style="width: 90%; margin: 0 auto;">
    <h4 class="mb-4">new link request from users</h4>

    <?php if ($info): ?><div class="alert alert-success small"><?= htmlspecialchars($info) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <table class="table table-sm table-bordered bg-white">
        <thead class="table-light">
            <tr>
                <th>id</th>
                <th>user</th>
                <th>alias</th>
                <th>url</th>
                <th class="text-end">action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $req): ?>
            <tr>
                <td><?= htmlspecialchars($req['id']) ?></td>
                <td><?= htmlspecialchars($req['username']) ?></td>
                <td><?= htmlspecialchars($req['alias']) ?></td>
                <td><a href="<?= htmlspecialchars($req['url']) ?>" target="_blank"><?= htmlspecialchars($req['url']) ?></a></td>
                <td class="text-end">
                    <form method="post" class="d-inline">
                        <input type="hidden" name="id" value="<?= $req['id'] ?>">
                        <button name="action" value="approve" class="btn btn-sm btn-outline-success">accept</button>
                    </form>
                    <form method="post" class="d-inline ms-1">
                        <input type="hidden" name="id" value="<?= $req['id'] ?>">
                        <button name="action" value="delete" class="btn btn-sm btn-outline-danger">delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
