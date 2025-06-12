<?php
$db = new PDO('sqlite:users.db');
$db->exec("CREATE TABLE IF NOT EXISTS test (id INTEGER)");
$db->exec("INSERT INTO test (id) VALUES (1)");
echo "âœ… Datenbank-Schreibtest erfolgreich.";
?>
