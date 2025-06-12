<?php
// Datei: logout.php
require_once __DIR__ . '/config.php';

session_unset();
session_destroy();

// Cookie lÃ¶schen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

header("Location: index.php");
exit;
