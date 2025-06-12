<?php
// Datei: register_lowercase.php â€“ lowercase-Normalisierung â€“ Stand: 2025-05-13 Europe/Berlin

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/config_support.php';

$info = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($username && $email && $password) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "invalid email address.";
        } else {
            try {
                $db = new PDO('sqlite:users.db');
                $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $db->prepare("INSERT INTO users (username, password, email, active) VALUES (:u, :p, :e, :a)");
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([':u' => $username, ':p' => $hash, ':e' => $email, ':a' => 0]);

                file_put_contents("audit.log", date("c") . " Registrierung (inaktiv) angelegt: $username <$email>
", FILE_APPEND);
                $info = "Dein Konto wurde angelegt. Nach Freigabe durch einen Administrator kannst du dich anmelden.";

                $server = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $mail = getConfiguredMailer();
                if ($mail && $email) {
                    $mail->addAddress($email);
                    $mail->Subject = "Willkommen bei MyLoginSrv";
                    $mail->Body = "Hallo $username,

Dein Zugang wurde erstellt.

Sobald dein Zugang durch den Administrator freigeschaltet wurde, kannst du dich hier anmelden: http://$server/login.php

Mit freundlichen GrÃ¼ÃŸen.";
                    try {
                        $mail->send();
                        file_put_contents("audit.log", date('c') . " Willkommensmail an $email gesendet
", FILE_APPEND);
                    } catch (Exception $e) {
                        file_put_contents("error.log", date('c') . " Fehler beim Senden an $email: " . $mail->ErrorInfo . "
", FILE_APPEND);
                    }
                }

            } catch (Exception $e) {
                $error = "Registrierung fehlgeschlagen: " . $e->getMessage();
                file_put_contents("error.log", date("c") . " Fehler bei Registrierung $username: " . $e->getMessage() . "
", FILE_APPEND);
            }
        }
    } else {
        $error = "Bitte alle Felder ausfuellen.";
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>register</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light" style="font-size: 0.95rem;">
<div class="container mt-5" style="max-width: 540px;">
    <div class="card border border-secondary bg-white">
        <div class="card-body">
            <h5 class="mb-3 text-secondary">register page</h5>
            <?php if ($info): ?><div class="alert alert-success small"><?= htmlspecialchars($info) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">user name:</label>
                    <input type="text" name="username" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">e-mail-address</label>
                    <input type="email" name="email" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">password</label>
                    <input type="password" name="password" class="form-control form-control-sm" required>
                </div>
                <button type="submit" class="btn btn-outline-primary w-100">register</button>
                <a href="login.php" class="btn btn-link w-100 mt-2">login page</a>
            </form>
        </div>
    </div>
</div>
</body>
</html>
