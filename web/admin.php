<?php
// Datei: admin.php â€“ geschÃ¼tzter Adminbereich
require_once "auth.php";
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Adminbereich</title>
    <link rel="stylesheet" href="./assets/css/bootstrap.min.css">
</head>
<body class="p-3 bg-light">
<?php include "admin_tab_nav.php"; ?>
<div class="container mt-3">
    <h3>Willkommen im Adminbereich</h3>
    <p>Benutzer: <strong><?= htmlspecialchars($_SESSION['username'] ?? '-') ?></strong></p>
    <p>Rolle: <strong><?= htmlspecialchars($_SESSION['role'] ?? '-') ?></strong></p>
</div>
</body>
</html>
