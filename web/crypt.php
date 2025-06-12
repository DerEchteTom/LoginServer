<?php
// Datei: crypt.php – Version 2025-05-05_01 – Verschlüsselung + base64 DEFAULT_KEY
// Erstellt: 2024-04-01 – Geändert: 2025-05-05

date_default_timezone_set('Europe/Berlin');

define('DEFAULT_KEY', base64_decode('MXFheTJ3c3gzZWRj')); // entspricht: 1qay2wsx3edc

function decryptValue($value, $key = DEFAULT_KEY) {
    if (!is_string($value)) return $value;
    if (str_starts_with($value, 'XOR:')) {
        $decoded = base64_decode(substr($value, 4));
        $out = '';
        for ($i = 0; $i < strlen($decoded); $i++) {
            $out .= $decoded[$i] ^ $key[$i % strlen($key)];
        }
        return $out;
    }
    if (str_starts_with($value, 'ENC:')) {
        $cipher = getBestCipher();
        $raw = base64_decode(substr($value, 4));
        $ivlen = openssl_cipher_iv_length($cipher);
        $iv = substr($raw, 0, $ivlen);
        $data = substr($raw, $ivlen);
        return openssl_decrypt($data, $cipher, substr(hash('sha256', $key), 0, 32), OPENSSL_RAW_DATA, $iv);
    }
    return $value;
}

function getBestCipher() {
    $preferred = ['aes-256-cbc', 'aes-128-cbc', 'aes-192-cbc'];
    foreach ($preferred as $cipher) {
        if (in_array($cipher, openssl_get_cipher_methods())) {
            return $cipher;
        }
    }
    return 'aes-128-cbc';
}
