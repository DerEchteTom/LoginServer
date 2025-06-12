<?php
// Datei: auth.php â€“ Zugriffsschutz fÃ¼r Admin-Tabs
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
date_default_timezone_set('Europe/Berlin');

// PrÃ¼fung auf Login
if (!isset($_SESSION['username']) || empty($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

// Optional: RollenprÃ¼fung
function requireRole($role) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
        header("Location: login.php");
        exit;
    }
}

// PrÃ¼ft, ob ein Benutzer eingeloggt ist
function isAuthenticated() {
    return isset($_SESSION['username']);
}

// PrÃ¼ft, ob Benutzer eine bestimmte Rolle hat (z.â€¯B. "admin")
function hasRole($requiredRole) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $requiredRole;
}
?>
