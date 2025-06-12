<?php
// Datei: config_support.php Version: 2025-05-08_02 SMTP_SECURE=none Fix
date_default_timezone_set('Europe/Berlin');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

if (!defined('DEFAULT_KEY')) {
    define('DEFAULT_KEY', base64_decode('MXFheTJ3c3gzZWRj'));
}

function parseEnvFile($filename = '.env') {
    $env = [];
    if (!file_exists($filename)) return $env;
    foreach (file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

function parseRawEnv($raw) {
    $env = [];
    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $env[trim($key)] = trim($val);
    }
    return $env;
}

function saveEnvFile($filename, $assoc, $mark_encrypted = false) {
    $lines = ["# zuletzt bearbeitet: " . date('Y-m-d H:i:s') . " Europe/Berlin"];
    if ($mark_encrypted) $lines[] = "# ENCRYPTED: yes";
    foreach ($assoc as $k => $v) {
        $lines[] = "$k=$v";
    }
    file_put_contents($filename, implode("\n", $lines));
}

function encryptValue($value, $key = DEFAULT_KEY, $method = 'XOR') {
    if (isEncrypted($value) || empty($value)) return $value;
    $out = '';
    for ($i = 0; $i < strlen($value); $i++) {
        $out .= $value[$i] ^ $key[$i % strlen($key)];
    }
    return 'XOR:' . base64_encode($out);
}

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
    return $value;
}

function isEncrypted($value) {
    return is_string($value) && str_starts_with($value, 'XOR:');
}

function getEncryptionKey($env = null) {
    $env = $env ?? parseEnvFile();
    return $env['SMTP_ENCRYPTION_KEY'] ?? DEFAULT_KEY;
}

function logAction($file, $msg) {
    file_put_contents($file, '[' . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

function getConfiguredMailer() {
    $env = parseEnvFile();
    $key = getEncryptionKey($env);

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = decryptValue($env['SMTP_HOST'] ?? '', $key);
        $mail->Port = (int)($env['SMTP_PORT'] ?? 25);

        $secure = strtolower($env['SMTP_SECURE'] ?? '');
        if ($secure === 'none' || $secure === '') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $secure;
        }

        $auth = strtolower($env['SMTP_AUTH'] ?? 'false');
        if (in_array($auth, ['true', 'on', '1'])) {
            $mail->SMTPAuth = true;
            $mail->Username = decryptValue($env['SMTP_USER'] ?? '', $key);
            $mail->Password = decryptValue($env['SMTP_PW'] ?? '', $key);
        } else {
            $mail->SMTPAuth = false;
        }

        $from = decryptValue($env['SMTP_FROM'] ?? '', $key);
        $mail->setFrom($from ?: 'noreply@example.com');

        return $mail;
    } catch (Exception $e) {
        logAction('error.log', 'PHPMailer Fehler: ' . $e->getMessage());
        return null;
    }
}
?>