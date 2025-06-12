<?php 
// init_cms_db.php â€“ Initialisiert 'cms.db' mit CMS-Sektionen und Konfigurationen
// Version: 2025-05-20_03 â€“ Erweiterung um Skalierungswerte

$db_file = __DIR__ . '/cms.db';
$newMessages = [];

try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // === CMS-Tabelle: Inhalte (Quill-kompatibel: nur text_content, section_name als SchlÃ¼ssel)
    $pdo->exec("CREATE TABLE IF NOT EXISTS page_content (
        section_name TEXT PRIMARY KEY,
        text_content TEXT
    )");

    // === Konfigurations-Tabelle (Generische Key-Value-Tabelle fÃ¼r systemweite Einstellungen)
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_name TEXT PRIMARY KEY,
        setting_value TEXT NOT NULL
    )");

    // === SECTION SETUP START ===
    // Sektionen, die im CMS-Editor spÃ¤ter auswÃ¤hlbar sein sollen (z.â€¯B. per Dropdown)
    // Diese Struktur ist erweiterbar â€“ jede Sektion wird eindeutig per section_name gespeichert
    $sections = ['main', 'footer', 'legal', 'help'];

    foreach ($sections as $section) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM page_content WHERE section_name = ?");
        $stmt->execute([$section]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO page_content (section_name, text_content) VALUES (?, ?)")->execute([ 
                $section, "<p>Default content for <strong>$section</strong> section.</p>"
            ]);
            $newMessages[] = "Sektion <strong>$section</strong> wurde neu angelegt.";
        }
    }
    // === SECTION SETUP END ===

    // === Default-Konfigurationen (generisch und ausbaubar) ===
    $configurations = [
        'redirect_timer' => '5',
        'site_title' => 'My CMS',
        // Skalierungswerte fÃ¼r Bilder
        'image_max_scaling' => '300', // Standardwert fÃ¼r maximale Skalierung
        'image_scaling_options' => '100,150,200,300,400' // Vordefinierte Optionen fÃ¼r Bildskalierung
    ];

    foreach ($configurations as $setting_name => $default_value) {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_name = ?");
        $stmt->execute([$setting_name]);
        $existing_value = $stmt->fetchColumn();

        if ($existing_value === false) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_name, setting_value) VALUES (?, ?)");
            $stmt->execute([$setting_name, $default_value]);
            $newMessages[] = "Konfiguration <strong>'$setting_name'</strong> mit Standardwert <em>'$default_value'</em> hinzugefÃ¼gt.";
        }
    }

} catch (PDOException $e) {
    echo "<div style='color: red; font-weight: bold;'>Datenbankfehler: " . htmlspecialchars($e->getMessage()) . "</div>";
    exit();
}

// Ausgabe nur bei tatsÃ¤chlichen Ã„nderungen
if (!empty($newMessages)) {
    echo "<div style='background: #f0f0f0; border: 1px solid #ccc; padding: 10px; font-family: sans-serif;'>";
    echo "<h4 style='margin-top: 0;'>CMS-Initialisierung abgeschlossen:</h4><ul>";
    foreach ($newMessages as $msg) {
        echo "<li>$msg</li>";
    }
    echo "</ul></div>";
}
?>
