<?php
// Datei: forgot_flexible.php â€“ Passwort-Reset mit Benutzername oder E-Mail â€“ Stand: 2025-05-13 Europe/Berlin
date_default_timezone_set('Europe/Berlin');
require_once 'config_support.php';

$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_user = strtolower(trim($_POST['username'] ?? ''));
    $input_mail = strtolower(trim($_POST['email'] ?? ''));

    $db = new PDO('sqlite:users.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = null;
    $user = null;

    if ($input_user) {
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = :v");
        $stmt->execute([':v' => $input_user]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($input_mail) {
        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(email) = :v");
        $stmt->execute([':v' => $input_mail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($user) {
        $username = $user['username'];
        $email = $user['email'];
        $token = bin2hex(random_bytes(16));
        $reset_url = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/reset.php?token=$token";

        $tmpfile = __DIR__ . "/reset_" . $token . ".txt";
        file_put_contents($tmpfile, $username . "
" . time());

        $mail = getConfiguredMailer();
        if ($mail && $email) {
            $mail->addAddress($email);
            $mail->Subject = "Passwort-Zuruecksetzung";
            $mail->Body = "Hallo $username,

Zur Zuruecksetzung deines Passworts klicke bitte auf den folgenden Link:
$reset_url

Dieser Link ist fÃ¼r 1 Stunde gÃ¼ltig.
";

            try {
                $mail->send();
                file_put_contents("audit.log", date("c") . " Reset-Link gesendet an $email
", FILE_APPEND);
                $info = "Ein Link zur ZurÃ¼cksetzung wurde gesendet.";
            } catch (Exception $e) {
                file_put_contents("error.log", date("c") . " Fehler beim Senden an $email: " . $mail->ErrorInfo . "
", FILE_APPEND);
                $error = "Fehler beim Mailversand.";
            }
        } else {
            $error = "Mailkonfiguration oder E-Mail-Adresse ungueltig.";
        }
    } else {
        $error = "Benutzer nicht gefunden.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>password lost</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5" style="max-width: 540px;">
    <div class="card bg-white border border-secondary">
        <div class="card-body">
            <h5 class="text-secondary">password lost?</h5>
            <?php if ($info): ?><div class="alert alert-success"><?= htmlspecialchars($info) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label">user name (optional)</label>
                    <input type="text" name="username" class="form-control form-control-sm">
                </div>
                <div class="mb-3">
                    <label class="form-label">e-mail-address (optional)</label>
                    <input type="email" name="email" class="form-control form-control-sm">
                </div>
                <div class="mb-2 small text-muted">please insert user name or e-mail address.</div>
                <button type="submit" class="btn btn-outline-primary w-100">reset passwort</button>
                <a href="login.php" class="btn btn-link w-100 mt-2">login page</a>
            </form>
        </div>
    </div>
</body>
</html>
