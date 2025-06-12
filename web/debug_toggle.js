// Datei: debug_toggle.js â€“ Version: 2025-05-09_01
// Beschreibung: JavaScript zur Umschaltung der Debug-Anzeige (Debug-Box)

function toggleDebug() {
    const box = document.getElementById("debug-box");
    if (box) {
        box.style.display = box.style.display === "none" ? "block" : "none";
    }
}
