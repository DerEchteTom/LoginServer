<?php
// functions.php

// Beispiel einer Sicherheitsfunktion für HTML-Ausgabe
function safeHtml($value) {
    return htmlspecialchars($value ?? '');
}

// Weitere Funktionen können hier hinzugefügt werden
?>
