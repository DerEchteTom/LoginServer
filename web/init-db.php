<?php
// Datei: init-db.php â€“ Stand: 2025-05-15  Europe/Berlin

date_default_timezone_set('Europe/Berlin');

function logInfo($msg)    { echo "[INFO] $msg\n"; }
function logSuccess($msg) { echo "[ OK ] $msg\n"; }
function logError($msg)   {
    echo "[ERR!] $msg\n";
    file_put_contents("error.log", date('c') . " $msg\n", FILE_APPEND);
}

try {
    logInfo("Connecting to SQLite...");
    $db = new PDO('sqlite:users.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    logInfo("Checking database structure...");

    // Tabelle 'users'
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT DEFAULT NULL,
        email TEXT,
        role TEXT NOT NULL DEFAULT 'user',
        active INTEGER NOT NULL DEFAULT 1,
        redirect_urls TEXT DEFAULT '[]',
        reset_token TEXT DEFAULT NULL,
        reset_expires INTEGER DEFAULT NULL
    )");
    logSuccess("Tabelle 'users' OK.");

    // Admin anlegen, wenn leer
    $exists = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ((int)$exists === 0) {
        $adminPass = password_hash('adminpass', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, role, active, redirect_urls)
                              VALUES ('admin', :p, '', 'admin', 1, '[]')");
        $stmt->execute([':p' => $adminPass]);
        logSuccess("Admin user 'admin' created with password 'adminpass'.");
    }

    // Tabelle 'link_requests'
    $db->exec("CREATE TABLE IF NOT EXISTS link_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        alias TEXT NOT NULL,
        url TEXT NOT NULL,
        created_at TEXT NOT NULL,
        status TEXT DEFAULT 'open'
    )");
    logSuccess("Tabelle 'link_requests' OK.");

    // Tabelle 'user_links'
    $db->exec("CREATE TABLE IF NOT EXISTS user_links (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        alias TEXT NOT NULL,
        url TEXT NOT NULL
    )");
    logSuccess("Tabelle 'user_links' OK.");

    // Log-Dateien pruefen
    foreach (['audit.log', 'error.log'] as $logfile) {
        if (!file_exists($logfile)) {
            file_put_contents($logfile, '');
            logSuccess("Logfile '$logfile' created.");
        } else {
            logInfo("Logfile '$logfile' already exists.");
        }
    }

    // .env pruefen und ggf. ergaenzen
    $envFile = __DIR__ . '/.env';
    $defaultEnv = [
        'SMTP_HOST'   => '',
        'SMTP_PORT'   => '',
        'SMTP_FROM'   => '',
        'SMTP_SECURE' => 'none',
        'SMTP_AUTH'   => 'off',
        'ADMIN_EMAIL' => ''
    ];

    if (!file_exists($envFile)) {
        $content = "";
        foreach ($defaultEnv as $key => $val) {
            $content .= "$key=$val\n";
        }
        file_put_contents($envFile, $content);
        logSuccess(".env created.");
    } else {
        $envLines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $envKeys = array_map(fn($line) => explode('=', $line, 2)[0], $envLines);
        $newLines = [];

        foreach ($defaultEnv as $key => $default) {
            if (!in_array($key, $envKeys)) {
                $newLines[] = "$key=$default";
                logInfo("$key added to .env.");
            }
        }

        if ($newLines) {
            file_put_contents($envFile, "\n" . implode("\n", $newLines), FILE_APPEND);
        }
    }

    logSuccess("Initialization .env complete.");

    // .envad pruefen und ggf. ergaenzen
    $envadFile = __DIR__ . '/.envad';
    $defaultEnvad = [
        'AD_HOST'   => '',
        'AD_PORT'   => '',
        'AD_BASE_DN'   => '',
        'AD_USER_ATTRIBUTE'   => '',
        'AD_DOMAIN'   => '',
        'AD_BIND_DN'   => '',
        'AD_BIND_PW'   => '',
    ];

    if (!file_exists($envadFile)) {
        $content = "";
        foreach ($defaultEnvad as $key => $val) {
            $content .= "$key=$val\n";
        }
        file_put_contents($envadFile, $content);
        logSuccess(".envad created.");
    } else {
        $envadLines = file($envadFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $envadKeys = array_map(fn($line) => explode('=', $line, 2)[0], $envadLines);
        $newLines = [];

        foreach ($defaultEnvad as $key => $default) {
            if (!in_array($key, $envadKeys)) {
                $newLines[] = "$key=$default";
                logInfo("$key added to .envad.");
            }
        }

        if ($newLines) {
            file_put_contents($envadFile, "\n" . implode("\n", $newLines), FILE_APPEND);
        }
    }

    logSuccess("Initialization .envad complete.");

} catch (Exception $e) {
    logError("Exception in init-db.php: " . $e->getMessage());
    exit(1);
}
logSuccess("Initialization complete.");
exit(0);
