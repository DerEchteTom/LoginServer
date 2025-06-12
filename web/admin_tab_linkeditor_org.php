<?php
// Datei: admin_tab_linkeditor_visual_v3.php â€“ Visual Linkeditor + Export + Verarbeitung â€“ Stand: 2025-05-13 Europe/Berlin
date_default_timezone_set('Europe/Berlin');
require_once "auth.php";
requireRole('admin');
require_once "config_support.php";

$db = new PDO("sqlite:users.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$users = $db->query("SELECT username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Visueller Linkeditor</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        td input { width: 100%; }
        tr:hover td { background: #f8f9fa; }
        textarea { font-family: monospace; font-size: 0.9rem; }
        .user-cols { display: flex; gap: 1rem; flex-wrap: wrap; }
        .user-cols select { flex: 1; min-width: 200px; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
<?php include "admin_tab_nav.php"; ?>
<div style="width: 90%; margin: 0 auto;">
<h4>Visueller Linkeditor mit Benutzer-Zuweisung</h4>

<form id="linkForm" method="post">
    <div class="mb-3">
        <label class="form-label">Links bearbeiten:</label>
        <table class="table table-bordered table-sm bg-white" id="linkTable">
            <thead>
                <tr><th>Alias</th><th>URL</th><th style="width:40px;"></th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="text" name="alias[]" class="form-control"></td>
                    <td><input type="text" name="url[]" class="form-control"></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">â€“</button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addRow()">+ Link hinzufÃ¼gen</button>
    </div>

    <div class="mb-3">
        <label class="form-label">Benutzer zuweisen:</label>
        <div class="user-cols">
            <select name="users[]" id="userSelect" class="form-select" multiple size="8">
                <?php foreach ($users as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="to_all" name="to_all" onchange="toggleAll(this)">
            <label class="form-check-label" for="to_all">An alle Benutzer zuweisen</label>
        </div>
    </div>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-outline-primary" onclick="exportJSON()">JSON anzeigen</button>
        <button type="button" class="btn btn-outline-success" onclick="downloadJSON()">Download</button>
        <label class="form-label m-0 small">JSON-Datei laden:</label>
        <input type="file" accept=".json" onchange="importJSON(event)" class="form-control form-control-sm" style="max-width: 300px;">
    </div>

    <textarea id="exportArea" class="form-control mt-3" rows="10" placeholder="Exportiertes JSON erscheint hier..." readonly></textarea>

    <div class="mt-3">
        <button type="submit" formaction="linkeditor_apply.php" class="btn btn-outline-danger">Links jetzt zuweisen</button>
    </div>
</form>

</div>
</div>

<script>
function addRow() {
    const table = document.querySelector("#linkTable tbody");
    const row = document.createElement("tr");
    row.innerHTML = `
        <td><input type="text" name="alias[]" class="form-control"></td>
        <td><input type="text" name="url[]" class="form-control"></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">â€“</button></td>`;
    table.appendChild(row);
}

function removeRow(btn) {
    const row = btn.closest("tr");
    row.remove();
}

function exportJSON() {
    const aliases = document.querySelectorAll('input[name="alias[]"]');
    const urls = document.querySelectorAll('input[name="url[]"]');
    const links = [];

    for (let i = 0; i < aliases.length; i++) {
        const alias = aliases[i].value.trim();
        const url = urls[i].value.trim();
        if (alias && url) {
            links.push({ alias, url });
        }
    }

    let users;
    if (document.getElementById("to_all").checked) {
        users = "all";
    } else {
        users = Array.from(document.getElementById("userSelect").selectedOptions).map(opt => opt.value);
    }

    const json = JSON.stringify({ users, links }, null, 2);
    document.getElementById("exportArea").value = json;
    return json;
}

function downloadJSON() {
    const json = exportJSON();
    const blob = new Blob([json], { type: "application/json" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "link_export.json";
    a.click();
    URL.revokeObjectURL(url);
}

function importJSON(event) {
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const obj = JSON.parse(e.target.result);
            if (!obj.links || !Array.isArray(obj.links)) throw new Error("Fehlende 'links'-Daten.");

            const tbody = document.querySelector("#linkTable tbody");
            tbody.innerHTML = "";

            obj.links.forEach(link => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td><input type="text" name="alias[]" class="form-control" value="${link.alias ?? ''}"></td>
                    <td><input type="text" name="url[]" class="form-control" value="${link.url ?? ''}"></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">â€“</button></td>`;
                tbody.appendChild(row);
            });

            if (obj.users === "all") {
                document.getElementById("to_all").checked = true;
                toggleAll(document.getElementById("to_all"));
            } else if (Array.isArray(obj.users)) {
                document.getElementById("to_all").checked = false;
                toggleAll(document.getElementById("to_all"));
                const select = document.getElementById("userSelect");
                Array.from(select.options).forEach(opt => {
                    opt.selected = obj.users.includes(opt.value);
                });
            }

            document.getElementById("exportArea").value = JSON.stringify(obj, null, 2);

        } catch (err) {
            alert("Fehler beim Importieren: " + err.message);
        }
    };
    reader.readAsText(event.target.files[0]);
}

function toggleAll(checkbox) {
    const select = document.getElementById("userSelect");
    select.disabled = checkbox.checked;
}
</script>
</body>
</html>
