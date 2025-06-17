<?php
// Datei: shared_includes.php â€“ Version: 2025-05-09_01
// Beschreibung: Zentrale Funktionssammlung fÃ¼r Logging, Mail, Debug
// Erstellt: 2025-05-09

require_once 'config_support.php';      // enthÃ¤lt Mailversand & VerschlÃ¼sselung
require_once 'debug_helper.php';        // enthÃ¤lt renderDebugBox()
date_default_timezone_set('Europe/Berlin');

// Debug-Wrapper (optional)
function addDebug(array &$log, string $msg): void {
    $log[] = $msg;
}

// Sicheres Logging
function logAudit(string $msg): void {
    file_put_contents('audit.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

function logError(string $msg): void {
    file_put_contents('error.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// Willkommensmail mit config_support.php senden
function sendWelcomeMail(string $to, string $username): void {
    if (empty($to)) return;
    $subject = "Willkommen bei MyLoginServer";
    $body = "Hallo {$username},\n\nIhr Konto wurde erstellt.\nLogin: https://yourdomain/login.php\n\nViele Gruesse";
    $sent = sendMailSMTP($to, $subject, $body);
    if ($sent) {
        logAudit("Willkommensmail an {$to} gesendet.");
    } else {
        logError("Fehler beim Senden der Willkommensmail an {$to}.");
    }
}
?>
