<?php
// upload.php â€“ Einfacher Bild-Upload und Speicherung des Bildpfads in der Datenbank

session_start();
date_default_timezone_set('Europe/Berlin');
require_once 'config.php';  // Deine Konfigurationsdatei

// PrÃ¼fen, ob der Benutzer angemeldet und Admin ist
if (!isset($_SESSION['username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access denied');
}

// ÃœberprÃ¼fen, ob das Bild hochgeladen wurde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    // Bildinformationen
    $file_info = $_FILES['image'];
    $file_tmp = $file_info['tmp_name'];
    $file_name = basename($file_info['name']);
    $file_type = mime_content_type($file_tmp);

    // ÃœberprÃ¼fen des MIME-Typs (nur JPEG, PNG erlauben)
    $allowed_types = ['image/jpeg', 'image/png'];
    if (in_array($file_type, $allowed_types)) {
        // Zielpfad fÃ¼r das Bild
        $uploadDir = './uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true); // Falls das Verzeichnis nicht existiert, wird es erstellt
        }

        $ext = pathinfo($file_name, PATHINFO_EXTENSION);
        $filename = uniqid('img_', true) . '.' . $ext;
        $upload_path = $uploadDir . $filename;

        // Bild speichern
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Erfolgreicher Upload, Bildpfad in der Datenbank speichern

            try {
                // Verbindung zur Datenbank herstellen
                $pdo = new PDO('sqlite:cms.db');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Bildpfad fÃ¼r die 'header' Sektion speichern
                $stmt = $pdo->prepare("UPDATE page_content SET image_path = ? WHERE section_name = 'header'");
                $stmt->execute([$upload_path]);

                echo "Bild erfolgreich hochgeladen und gespeichert.";
            } catch (PDOException $e) {
                echo "Fehler beim Speichern des Bildes in der Datenbank: " . $e->getMessage();
            }
        } else {
            echo "Fehler beim Speichern des Bildes.";
        }
    } else {
        echo "UngÃ¼ltiger Bildtyp. Nur JPEG und PNG sind erlaubt.";
    }
} else {
    echo "Kein Bild zum Hochladen ausgewÃ¤hlt.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Image</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h4>Upload Image for Header</h4>
    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="image" class="form-label">Select Image</label>
            <input type="file" id="image" name="image" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-outline-success">Upload Image</button>
    </form>
</div>
</body>
</html>
