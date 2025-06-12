<?php
// Datei: session_kill.php â€“ Stand: <?= date('Y-m-d H:i') ?> Europe/Berlin

date_default_timezone_set('Europe/Berlin');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
session_unset();
session_destroy();

header("Location: login.php");
exit;
