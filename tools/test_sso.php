<?php
// test_sso.php - Test der AD-Authentifizierung und SSO-Feedback

session_start();
date_default_timezone_set('Europe/Berlin');

// Feedback und Fehlerausgabe aktivieren
function debug_feedback($message) {
    echo "<pre>" . htmlspecialchars($message) . "</pre>";
}

// Schritt 1: ÃœberprÃ¼fen, ob der Benutzer Ã¼ber Windows authentifiziert wurde (Kerberos/NTLM)
if (isset($_SERVER['REMOTE_USER'])) {
    $windows_user = $_SERVER['REMOTE_USER'];
    debug_feedback("Windows-Anmeldung erkannt: " . $windows_user);
} else {
    debug_feedback("Kein Windows-Benutzer gefunden, weitere Authentifizierung erforderlich.");
}

// Schritt 2: AD-Authentifizierung (Ã¼ber .envad und Hilfsaccount)
require_once 'config_support.php'; // Die Datei, die die .envad entschlÃ¼sselt

// EntschlÃ¼sseln der AD-Variablen aus der .envad-Datei
$ad_config = parseEnvFile('.envad'); // Holen der AD-Umgebungsvariablen

// Anzeige der entschlÃ¼sselten AD-Variablen
debug_feedback("EntschlÃ¼sselte AD-Konfigurationen:");
debug_feedback(print_r($ad_config, true));

// EntschlÃ¼sseln der AD-Verbindungsdaten
$ad_host = decryptValue($ad_config['AD_HOST'] ?? '');
$ad_port = (int)($ad_config['AD_PORT'] ?? 389);
$ad_binddn = decryptValue($ad_config['AD_BIND_DN'] ?? '');
$ad_bindpw = decryptValue($ad_config['AD_BIND_PW'] ?? '');
$ad_basedn = decryptValue($ad_config['AD_BASE_DN'] ?? '');

// Anzeigen der AD-Verbindungsdaten zur ÃœberprÃ¼fung
debug_feedback("AD-Verbindungsdaten:");
debug_feedback("Host: $ad_host");
debug_feedback("Port: $ad_port");
debug_feedback("Bind DN: $ad_binddn");
debug_feedback("Base DN: $ad_basedn");

// Schritt 3: ÃœberprÃ¼fung der AD-Anmeldedaten
$ldap_conn = ldap_connect($ad_host, $ad_port);
if ($ldap_conn) {
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    // Versuchen, sich mit den Bind-Daten zu verbinden
    $bind = ldap_bind($ldap_conn, $ad_binddn, $ad_bindpw);
    if ($bind) {
        debug_feedback("Erfolgreich mit AD verbunden und authentifiziert.");
    } else {
        debug_feedback("AD-Authentifizierung fehlgeschlagen.");
    }

    // Suchen des Benutzers anhand des Windows-Benutzernamens
    if (isset($windows_user)) {
        $filter = "(sAMAccountName=$windows_user)";
        $search = ldap_search($ldap_conn, $ad_basedn, $filter);
        if ($search) {
            $entries = ldap_get_entries($ldap_conn, $search);
            if ($entries['count'] > 0) {
                debug_feedback("Benutzer gefunden: " . $entries[0]['dn']);
            } else {
                debug_feedback("Benutzer nicht gefunden.");
            }
        } else {
            debug_feedback("LDAP-Suche fehlgeschlagen.");
        }
    }

    ldap_close($ldap_conn);
} else {
    debug_feedback("Verbindung zum AD-Server fehlgeschlagen.");
}

// Schritt 4: Weitere Debugging-Informationen anzeigen
debug_feedback("VollstÃ¤ndige Benutzerinformationen: ");
debug_feedback(print_r($_SERVER, true));

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Test SSO</title>
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
    <div class="container">
        <h3>Test Single Sign-On (SSO)</h3>
        <p>Die SSO-Authentifizierung wurde durchgefÃ¼hrt. Siehe die detaillierten Feedbacks oben.</p>
    </div>
</body>
</html>
