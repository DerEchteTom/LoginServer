<?php
// Datei: login_combined_lowercase.php â€“ Kombinierter Login mit lowercase â€“ Stand: 2025-05-13 Europe/Berlin
session_start();
date_default_timezone_set('Europe/Berlin');
require_once 'config_support.php';

$db = new SQLite3('users.db');
$debug = [];
$info = '';
$error = '';

// Lokaler Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['local_login'])) {
    $username = strtolower(trim($_POST['local_user'] ?? ''));
    $password = $_POST['local_pass'] ?? '';

    $debug[] = "Lokaler Loginversuch: $username";
    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = :u");
    $stmt->bindValue(':u', $username);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'] ?? '')) {
        if ((int)$user['active'] === 1) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                echo "<p class='text-muted'>Login erfolgreich. Du wirst gleich weitergeleitet...</p>";
                renderDebugBox($debug, true);
                sleep(3);
                header("Location: dashboard.php");
                exit;
            } else {
                header("Location: dashboard.php");
                exit;
            }
        } else {
            $info = "Dein Konto ist vorhanden, aber noch nicht aktiviert.";
        }
    } else {
        $error = "UngÃ¼ltiger Benutzername oder Passwort.";
    }
}

// AD-Login
function try_ad_login($username, $password, &$debug) {
    $env = parseEnvFile('.envad');
    $host = decryptValue($env['AD_HOST'] ?? '', getEncryptionKey());
    $port = (int)($env['AD_PORT'] ?? 389);
    $binddn = decryptValue($env['AD_BIND_DN'] ?? '', getEncryptionKey());
    $bindpw = decryptValue($env['AD_BIND_PW'] ?? '', getEncryptionKey());
    $basedn = decryptValue($env['AD_BASE_DN'] ?? '', getEncryptionKey());

    $debug[] = "AD-Login: $username â†’ Host=$host, Port=$port";
    $conn = @ldap_connect($host, $port);
    if (!$conn) return false;
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    if (!@ldap_bind($conn, $binddn, $bindpw)) return false;

    $filter = "(sAMAccountName=$username)";
    $search = @ldap_search($conn, $basedn, $filter);
    if (!$search) return false;

    $entries = ldap_get_entries($conn, $search);
    if ($entries['count'] > 0) {
        $dn = $entries[0]['dn'];
        $mail = strtolower($entries[0]['mail'][0] ?? '');
        if (@ldap_bind($conn, $dn, $password)) {
            return ['dn' => $dn, 'email' => $mail];
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ad_login'])) {
    $username = strtolower(trim($_POST['ad_user'] ?? ''));
    $password = $_POST['ad_pass'] ?? '';
    $debug[] = "AD-Loginversuch: $username";

    if ($username && $password) {
        $result = try_ad_login($username, $password, $debug);
        if ($result) {
            $check = $db->prepare("SELECT * FROM users WHERE LOWER(username) = :u");
            $check->bindValue(':u', $username);
            $exists = $check->execute()->fetchArray(SQLITE3_ASSOC);
            if ($exists && (int)$exists['active'] === 1) {
                $_SESSION['username'] = $exists['username'];
                $_SESSION['role'] = $exists['role'] ?? 'user';
                header("Location: dashboard.php");
                exit;
            } elseif ($exists) {
                $info = "Dein Konto ist vorhanden, aber noch nicht aktiviert.";
            } else {
                $email = $result['email'] ?? '';
                $stmt = $db->prepare("INSERT INTO users (username, password, email, role, active) VALUES (:u, NULL, :e, 'user', 0)");
                $stmt->bindValue(':u', $username);
                $stmt->bindValue(':e', $email);
                $stmt->execute();
                $info = "AD-Login erkannt. Benutzer wurde lokal eingetragen und ist inaktiv.";

                $link = "http://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/login.php";
                $mail = getConfiguredMailer();
                if ($mail && $email) {
                    $mail->addAddress($email);
                    $mail->Subject = "Willkommen bei MyLoginServer";
                    $mail->Body = "Hallo $username,

Dein Konto wurde ueber AD erkannt.
Ein Admin muss dich noch aktivieren.

Login: $link
";
                    try {
                        $mail->send();
                        $debug[] = "Willkommensmail an $email gesendet.";
                        logAction("audit.log", "Willkommensmail an $email gesendet.");
                    } catch (Exception $e) {
                        logAction("error.log", "Mailfehler an $email: " . $mail->ErrorInfo);
                    }
                }
            }
        } else {
            $error = "AD-Login nicht erfolgreich oder Benutzer nicht gefunden.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>login page local- /ad*login</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <script src="debug_toggle.js"></script>
</head>
<body class="container mt-4">
    <h4> </h4>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!empty($info)): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($info) ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <form method="post" class="mb-4 border p-3 bg-light rounded">
                <h5>local login</h5>
                <input type="hidden" name="local_login" value="1">
                <div class="mb-2">
                    <label>user name:</label>
                    <input type="text" name="local_user" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label>password:</label>
                    <input type="password" name="local_pass" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-outline-primary">login</button>
            </form>
        </div>
        <div class="col-md-6">
            <form method="post" class="mb-4 border p-3 bg-light rounded">
                <h5>ad-login</h5>
                <input type="hidden" name="ad_login" value="1">
                <div class="mb-2">
                    <label>user name:</label>
                    <input type="text" name="ad_user" class="form-control" required>
                </div>
                <div class="mb-2">
                    <label>password:</label>
                    <input type="password" name="ad_pass" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-outline-primary">ad-login</button>
            </form>
        </div>
    </div>

    <div class="d-flex gap-2 justify-content-center my-4">
        <a href="register.php" class="btn btn-sm btn-outline-secondary">register</a>
        <a href="forgot.php" class="btn btn-sm btn-outline-secondary">password lost?</a>
    </div>

    <?php
    require_once 'debug_helper.php';
    renderDebugBox($debug, false);
    ?>
</body>
</html>
