<?php
// Datei: admin_upload_users.php â€“ Stand: 2025-04-24 12:15 Uhr Europe/Berlin

$importSuccess = 0;
$importFailed = 0;
$importInvalid = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['userfile'])) {
    $file = $_FILES['userfile']['tmp_name'];
    if (is_uploaded_file($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $db = new PDO('sqlite:users.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            foreach ($data as $user) {
                $username = trim($user['username'] ?? '');
                $email    = trim($user['email'] ?? '');
                $password = $user['password'] ?? '';
                $role     = $user['role'] ?? 'user';
                $active   = isset($user['active']) ? (int)$user['active'] : 0;

                if ($username && $email && $password) {
                    $existsU = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :u");
                    $existsU->execute([':u' => $username]);
                    $existsE = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :e");
                    $existsE->execute([':e' => $email]);

                    if ($existsU->fetchColumn() > 0) {
                        $importFailed++;
                        file_put_contents("error.log", date("c") . " Benutzername '$username' bereits vorhanden â€“ Ã¼bersprungen\n", FILE_APPEND);
                        continue;
                    }
                    if ($existsE->fetchColumn() > 0) {
                        $importFailed++;
                        file_put_contents("error.log", date("c") . " E-Mail '$email' bereits vorhanden â€“ Ã¼bersprungen\n", FILE_APPEND);
                        continue;
                    }

                    try {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("INSERT INTO users (username, password, email, role, active) VALUES (:u, :p, :e, :r, :a)");
                        $stmt->execute([':u' => $username, ':p' => $hash, ':e' => $email, ':r' => $role, ':a' => $active]);
                        file_put_contents("audit.log", date("c") . " Import: Benutzer '$username' erfolgreich importiert\n", FILE_APPEND);
                        $importSuccess++;
                    } catch (Exception $e) {
                        $importFailed++;
                        file_put_contents("error.log", date("c") . " Fehler beim Import von '$username': {$e->getMessage()}\n", FILE_APPEND);
                    }
                } else {
                    $importInvalid++;
                    file_put_contents("error.log", date("c") . " UngÃ¼ltiger Eintrag im JSON (fehlende Felder): " . json_encode($user) . "\n", FILE_APPEND);
                }
            }
        } else {
            echo "<div class='alert alert-danger small mt-2'>Fehler: Datei konnte nicht als gÃ¼ltiges JSON interpretiert werden.</div>";
        }
    }

    echo "<div class='alert alert-info small mt-2'>Import abgeschlossen: $importSuccess erfolgreich, $importFailed doppelt oder fehlgeschlagen, $importInvalid ungÃ¼ltig.</div>";
}
?>

<div class="row g-2 align-items-center mb-3">
    <!-- Upload-Formular -->
    <div class="col-auto">
        <form method="post" enctype="multipart/form-data" class="d-flex align-items-center">
            <label class="form-label mb-0 me-2">Benutzerliste</label>
            <input type="file" name="userfile" accept=".json" class="form-control form-control-sm me-2">
            <button type="submit" class="btn btn-sm btn-outline-primary">Importieren</button>
        </form>
    </div>

    <!-- Suchformular -->
    <div class="col">
        <form method="get" class="d-flex justify-content-end">
            <input type="text" name="search" placeholder="Suche Benutzer/E-Mail" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" class="form-control form-control-sm me-2">
            <button type="submit" class="btn btn-sm btn-outline-secondary">Suchen</button>
        </form>
    </div>
</div>

<?php if ($importSuccess > 0 || $importFailed > 0 || $importInvalid > 0): ?>
<script>
    setTimeout(() => {
        window.location.reload();
    }, 1500);
</script>
<?php endif; ?>
