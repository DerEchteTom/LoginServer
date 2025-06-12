<?php
// Datei: debug_helper.php â€“ Version: 2025-05-09_01
// Beschreibung: Umschaltbare Debug-Ausgabe fÃ¼r Admin- und Loginmodule

function renderDebugBox(array $debugMessages, bool $show = true): void {
    $toggleState = $show ? 'block' : 'none';
    echo '<div class="card border-secondary mt-3">';
    echo '<div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">';
    echo '<span>Debug-Ausgabe</span>';
    echo '<button class="btn btn-sm btn-light" onclick="toggleDebug()">anzeigen/verbergen</button>';
    echo '</div>';
    echo '<div id="debug-box" class="card-body" style="display:' . $toggleState . ';">';
    echo '<pre style="font-size: 0.9em;">';
    foreach ($debugMessages as $line) {
        echo htmlspecialchars($line) . "\n";
    }
    echo '</pre>';
    echo '</div>';
    echo '</div>';
}
?>
