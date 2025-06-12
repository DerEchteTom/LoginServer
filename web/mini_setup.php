<?php
// Datei: mini_setup.php â€“ Stand: 2025-05-16 Europe/Berlin

date_default_timezone_set('Europe/Berlin');

function fixPermissions($file, $label) {
    echo "[INFO] Checking $label ($file)\n";

    if (!file_exists($file)) {
        echo "[WARN] $label does not exist.\n";
        return;
    }

    // Schreibtest
    if (!is_writable($file)) {
        echo "[WARN] $label is not writable. Trying to fix...\n";

        $owner = posix_getpwuid(fileowner($file))['name'] ?? 'unknown';
        $perms = substr(sprintf('%o', fileperms($file)), -4);

        // Rechte setzen
        chmod($file, 0664);
        chown($file, 'www-data');

        // PrÃ¼fung erneut
        if (is_writable($file)) {
            echo "[ OK ] $label is now writable (owner: www-data, perms: 664).\n";
        } else {
            echo "[ERR] Failed to make $label writable. Current owner: $owner, perms: $perms\n";
        }
    } else {
        echo "[ OK ] $label is already writable.\n";
    }
}

// Hauptverzeichnis korrekt auflÃ¶sen
$base = __DIR__;

fixPermissions("$base/users.db", "users.db");
fixPermissions("$base/info.db", "info.db");
