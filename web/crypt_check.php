<?php
// Datei: crypt_check.php
echo "<h3>OpenSSL Cipher-Test</h3><ul>";
$ciphers = openssl_get_cipher_methods();
foreach ($ciphers as $cipher) {
    echo "<li>" . htmlspecialchars($cipher) . "</li>";
}
echo "</ul>";

echo "<h4>Aktiver Modus:</h4>";
include_once "crypt.php";
$active = getBestCipher();
if ($active) {
    echo "<p><strong>✔️ " . htmlspecialchars($active['cipher']) . "</strong></p>";
} else {
    echo "<p><strong>❌ Kein Cipher verfügbar</strong></p>";
}
?>
