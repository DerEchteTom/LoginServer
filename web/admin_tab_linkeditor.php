<?php
// File: admin_tab_linkeditor.php Visual Link Editor + Export + Apply Version: 2025-05-15 Europe/Berlin
date_default_timezone_set('Europe/Berlin');
require_once "auth.php";
requireRole('admin');
require_once "config_support.php";

$db = new PDO("sqlite:users.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$users = $db->query("SELECT username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Visual Link Editor</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        td input { width: 100%; }
        tr:hover td { background: #f2f2f2; }
        textarea { font-family: monospace; font-size: 0.9rem; }
        .user-cols { display: flex; gap: 1rem; flex-wrap: wrap; }
        .user-cols select { flex: 1; min-width: 200px; }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid mt-4">
<?php include "admin_tab_nav.php"; ?>
<div style="width: 90%; margin: 0 auto;">
<h4>link assignment editor</h4>
<form id="linkForm" method="post">
    <div class="mb-3">
        <label class="form-label">edit links:</label>
        <table class="table table-bordered table-sm bg-white" id="linkTable">
            <thead><tr><th>alias</th><th>url</th><th style="width:40px;"></th></tr></thead>
            <tbody>
                <tr>
                    <td><input type="text" name="alias[]" class="form-control"></td>
                    <td><input type="text" name="url[]" class="form-control"></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">delete</button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()">+ add new link</button>
    </div>

    <div class="mb-3">
        <label class="form-label">assign to users:</label>
        <div class="user-cols">
            <select name="users[]" id="userSelect" class="form-select" multiple size="8">
                <?php foreach ($users as $u): ?>
                    <option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" id="to_all" name="to_all" onchange="toggleAll(this)">
            <label class="form-check-label" for="to_all">assign to all users</label>
        </div>
    </div>

    <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-outline-primary" onclick="exportJSON()">refresh JSON view</button>
        <button type="button" class="btn btn-outline-success" onclick="downloadJSON()">export JSON</button>
        <label class="form-label m-0 small">import JSON file:</label>
        <input type="file" accept=".json" onchange="importJSON(event)" class="form-control form-control-sm" style="max-width: 300px;">
    </div>

    <textarea id="exportArea" class="form-control mt-3" rows="10" placeholder="Exported JSON will appear here..." readonly></textarea>

    <div class="mt-3 d-flex gap-2 align-items-center">
        <button type="button" class="btn btn-outline-primary" onclick="assignLinks()">assign links now</button>
        <span id="assignStatus" class="ms-3 text-muted"></span>
    </div>
</form>
<script>
function addRow() {
    const table = document.querySelector("#linkTable tbody");
    const row = document.createElement("tr");
    row.innerHTML = `
        <td><input type="text" name="alias[]" class="form-control"></td>
        <td><input type="text" name="url[]" class="form-control"></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">delete“</button></td>`;
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

    const now = new Date();
    const fileName = "link_export_" + now.toISOString().slice(0, 16).replace(/[:T]/g, '-') + ".json";

    a.download = fileName;
    a.click();
    URL.revokeObjectURL(url);
}

function importJSON(event) {
    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const obj = JSON.parse(e.target.result);
            if (!obj.links || !Array.isArray(obj.links)) throw new Error("Missing 'links' array.");

            const tbody = document.querySelector("#linkTable tbody");
            tbody.innerHTML = "";

            obj.links.forEach(link => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td><input type="text" name="alias[]" class="form-control" value="${link.alias ?? ''}"></td>
                    <td><input type="text" name="url[]" class="form-control" value="${link.url ?? ''}"></td>
                    <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">delete</button></td>`;
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
            alert("Import error: " + err.message);
        }
    };
    reader.readAsText(event.target.files[0]);
}

function toggleAll(checkbox) {
    const select = document.getElementById("userSelect");
    select.disabled = checkbox.checked;
}

function assignLinks() {
    const json = document.getElementById('exportArea').value.trim();
    if (!json) {
        alert("No JSON data to assign.");
        return;
    }

    fetch("linkeditor_apply.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: json
    })
    .then(r => r.text())
    .then(response => {
        document.getElementById("assignStatus").innerHTML =
            '<span class="text-success">assignment completed</span>';
        alert("Link assignment completed:\n\n" + response);
    })
    .catch(err => {
        document.getElementById("assignStatus").innerHTML =
            '<span class="text-danger">âœ– Error during assignment</span>';
        console.error(err);
    });
}
</script>
<div class="container mt-4">
    <label for="exampleTextArea" class="form-label">info</label>
    <textarea class="form-control" id="exampleTextArea" rows="5" placeholder="There are two ways to add links - manually by entering the URL and alias in the forms above or import pre-configured links based on the .json
Format. Please select the corresponding users they should be assigned to the new links or enable the all users function to asign for. The
refresh function (please always use this button!) shows then the current JSON status, which can be later assigned directly or exported again."></textarea>
</div>
</div>
</div>
</body>
</html>
