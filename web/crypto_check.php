<?php
// Datei: crypto_check.php
// Version: 2025-04-26_01
// Kurzer Selbsttest zur Verschlüsselungsfähigkeit

header('Content-Type: text/html; charset=utf-8');

$phpVersion = PHP_VERSION;
$opensslAvailable = extension_loaded('openssl');
$functionsAvailable = function_exists('openssl_encrypt') && function_exists('openssl_random_pseudo_bytes');

$ciphers = openssl_get_cipher_methods();
$aes256cbcSupported = in_array('AES-256-CBC', $ciphers);

$status = [
    'PHP Version' => $phpVersion,
    'OpenSSL Extension' => $opensslAvailable ? '✅ Verfügbar' : '❌ Nicht verfügbar',
    'Funktionen vorhanden' => $functionsAvailable ? '✅ Vorhanden (openssl_encrypt, openssl_random_pseudo_bytes)' : '❌ Funktionen fehlen',
    'AES-256-CBC Unterstützung' => $aes256cbcSupported ? '✅ Unterstützt' : '❌ Nicht unterstützt',
];

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Crypto Check</title>
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container-fluid mt-4" style="max-width: 100%;">
<?php if (file_exists(__DIR__ . '/admin_tab_nav.php')) include __DIR__ . '/admin_tab_nav.php'; ?>
    <h4 class="mb-4">PHP Crypto Check</h4>

    <table class="table table-bordered bg-white shadow-sm">
        <tbody>
            <?php foreach ($status as $key => $value): ?>
            <tr>
                <th><?= htmlspecialchars($key) ?></th>
                <td><?= htmlspecialchars($value) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <hr>

    <h5>Zusammenfassung</h5>
    <?php if ($opensslAvailable && $functionsAvailable && $aes256cbcSupported): ?>
        <div class="alert alert-success">
            ✅ Deine PHP-Installation unterstützt sichere Verschlüsselung!
        </div>
    <?php else: ?>
        <div class="alert alert-danger">
            ❌ Achtung: Dein PHP-Server ist NICHT bereit für sichere Verschlüsselung! use fallback to AES-128-CBC or AES-64
        </div>
    <?php endif; ?>
</div>
</body>
</html>
