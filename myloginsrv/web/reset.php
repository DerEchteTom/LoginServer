<?php
// Datei: reset.php

require_once __DIR__ . '/config_support.php';

$error = "";
$info = "";
$token = $_GET['token'] ?? '';

if (!$token) {
    $error = "Ungültiger oder fehlender Token.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (!$token || !$password || !$confirm) {
        $error = "Alle Felder müssen ausgefüllt werden.";
    } elseif ($password !== $confirm) {
        $error = "Die Passwörter stimmen nicht überein.";
    } else {
        try {
            $db = new PDO('sqlite:users.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT * FROM users WHERE reset_token = :token AND reset_expires > :now");
            $stmt->execute([':token' => $token, ':now' => time()]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $hash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $db->prepare("UPDATE users SET password = :p, reset_token = NULL, reset_expires = NULL WHERE id = :id");
                $stmt->execute([':p' => $hash, ':id' => $user['id']]);

                file_put_contents("audit.log", date('c') . " 🔐 Passwort zurückgesetzt für {$user['username']} (ID={$user['id']})\n", FILE_APPEND);

                header("Location: login.php?reset=success");
                exit;
            } else {
                $error = "Der Link ist ungültig oder abgelaufen.";
            }
        } catch (Exception $e) {
            $error = "Fehler beim Zurücksetzen des Passworts.";
            file_put_contents("error.log", date('c') . " ❌ Fehler in reset.php: " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Passwort zurücksetzen – MyLoginSrv</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5" style="max-width: 450px;">
    <div class="card shadow-sm">
        <div class="card-body">
            <h4 class="mb-4 text-center">Neues Passwort setzen</h4>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="mb-3">
                    <label class="form-label">Neues Passwort</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Bestätigen</label>
                    <input type="password" name="confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Passwort speichern</button>
                <a href="login.php" class="btn btn-link w-100 mt-2">Zurück zum Login</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
