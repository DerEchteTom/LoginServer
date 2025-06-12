<?php
// Datei: admin_tab_ldaptest.php – Version: 2025-05-08_01
date_default_timezone_set('Europe/Berlin');
require_once "auth.php";
requireRole('admin');
require_once 'config_support.php';

$env_file = '.envad';
$backup_file = '.envad.bak';
$notice = '';
$debug_log = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['env_save'])) {
    $assoc = parseRawEnv($_POST['env'] ?? '');
    saveEnvFile($env_file, $assoc, false);
    $notice = "Datei gespeichert.";
    logAction("audit.log", "admin_tab_ldaptest.php: .envad gespeichert.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['env_encrypt'])) {
    $raw = $_POST['env'] ?? '';
    file_put_contents($backup_file, $raw);
    $assoc = parseRawEnv($raw);
    $key = getEncryptionKey($assoc);
    foreach ($assoc as $k => $v) {
        if (in_array($k, ['AD_HOST', 'AD_BASE_DN', 'AD_BIND_DN', 'AD_BIND_PW'], true) && !isEncrypted($v) && !empty($v)) {
            $assoc[$k] = encryptValue($v, $key, 'XOR');
        }
    }
    saveEnvFile($env_file, $assoc, true);
    $notice = "Sensible Felder verschlüsselt. Backup gespeichert unter .envad.bak.";
    logAction("audit.log", "admin_tab_ldaptest.php: Verschlüsselung durchgeführt.");
}

$env = parseEnvFile($env_file);
$key = getEncryptionKey($env);

$host     = decryptValue($env['AD_HOST'] ?? '', $key);
$port     = (int)($env['AD_PORT'] ?? 389);
$basedn   = decryptValue($env['AD_BASE_DN'] ?? '', $key);
$binduser = decryptValue($env['AD_BIND_DN'] ?? '', $key);
$bindpass = decryptValue($env['AD_BIND_PW'] ?? '', $key);
$attr     = $env['AD_USER_ATTRIBUTE'] ?? 'sAMAccountName';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password']) && $username && $password) {
    //$debug_log[] = "Verbindung zu LDAP-Server $host:$port wird aufgebaut...";
    $debug_log[] = "Verbindung zu LDAP-Server wird aufgebaut...";
    $conn = @ldap_connect($host, $port);
    if (!$conn) {
        $debug_log[] = "FEHLER: Verbindung zu $host fehlgeschlagen.";
    } else {
        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        $debug_log[] = "OK: Verbindung hergestellt.";
        //$debug_log[] = "Bind-Versuch mit technischem Benutzer: $binduser";
        $debug_log[] = "Bind-Versuch mit technischem Benutzer.";
        if (!@ldap_bind($conn, $binduser, $bindpass)) {
            $debug_log[] = "FEHLER: Bind mit AD_BIND_DN fehlgeschlagen.";
        } else {
            $debug_log[] = "OK: Bind erfolgreich.";
            $filter = "($attr=$username)";
          //  $debug_log[] = "LDAP-Suche mit Filter: $filter in $basedn";
          $debug_log[] = "LDAP-Suche mit Filter";
            $search = @ldap_search($conn, $basedn, $filter);
            if ($search) {
                $entries = ldap_get_entries($conn, $search);
                if ($entries['count'] > 0) {
                    $dn = $entries[0]['dn'];
                    //$debug_log[] = "OK: Benutzer gefunden mit DN: $dn";
                    $debug_log[] = "OK: Benutzer gefunden.";
                    if (@ldap_bind($conn, $dn, $password)) {
                        $debug_log[] = "✅ Benutzer-Login erfolgreich!";
                    } else {
                        $debug_log[] = "❌ Passwort falsch für DN: $dn";
                    }
                } else {
                    $debug_log[] = "Benutzer nicht gefunden in $basedn";
                }
            } else {
                $debug_log[] = "FEHLER: LDAP-Suche fehlgeschlagen.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>LDAP-test & configuration</title>
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container-fluid mt-4">

<?php include "admin_tab_nav.php"; ?>
<div class="container">
    <h3>LDAP-login check and edit configuration file</h3>

    <form method="post" class="mb-3">
        <div class="mb-2">
            <label for="username" class="form-label">username:</label>
            <input type="text" name="username" id="username" class="form-control" required>
        </div>
        <div class="mb-2">
            <label for="password" class="form-label">password:</label>
            <input type="password" name="password" id="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-outline-primary">check login</button>
    </form>

    <?php if (!empty($debug_log)): ?>
        <h5 class="mt-4">Debug-Ausgabe:</h5>
        <pre class="bg-light p-2 border"><?php foreach ($debug_log as $line) echo htmlspecialchars($line) . "\n"; ?></pre>
    <?php endif; ?>

    <h4 class="mt-5">edit .envad / encrypt</h4>
    <?php if (!empty($notice)): ?>
        <div class="alert alert-info"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>
    <form method="post" class="mb-3">
        <textarea name="env" rows="14" class="form-control font-monospace"><?= htmlspecialchars(file_get_contents($env_file)) ?></textarea>
        <div class="d-flex gap-2 mt-2">
            <button type="submit" name="env_save" class="btn btn-outline-secondary">save</button>
            <button type="submit" name="env_encrypt" class="btn btn-outline-warning">decrypt</button>
        </div>
    </form>

     

<?php if (isset($_GET['showdec']) && $_GET['showdec'] === '1'): ?>
<hr class="my-4">
<button class="btn btn-outline-secondary mb-2" onclick="toggleDecrypted()">Entschlüsselte ENVAD-Werte anzeigen/verbergen</button>
<div id="decrypted" style="display:none;">
    <table class="table table-bordered table-sm bg-light">
        <thead><tr><th>Schlüssel</th><th>Wert (entschlüsselt)</th></tr></thead>
        <tbody>
        <?php foreach ($env as $k => $v): ?>
            <tr><td><?= htmlspecialchars($k) ?></td><td><?= htmlspecialchars(decryptValue($v, $key)) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
//  Zum Anzeigen <code>?showdec=1</code> an die URL anhängen.
<script>
function toggleDecrypted() {
    const box = document.getElementById("decrypted");
    box.style.display = box.style.display === "none" ? "block" : "none";
}
</script>
<?php else: ?>
    <p class="text-muted mt-3">Decrypted ENVAD values are currently hidden.</p>
<?php endif; ?>

</div>
</body>
</html>