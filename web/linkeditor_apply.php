<?php
// Datei: linkeditor_apply.php â€“ Assign links to users based on JSON input â€“ Stand: 2025-05-16 Europe/Berlin
date_default_timezone_set("Europe/Berlin");
require_once "auth.php";
requireRole("admin");

header("Content-Type: text/plain; charset=utf-8");
$json = file_get_contents("php://input");
$data = json_decode($json, true);

$db = new PDO("sqlite:users.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];
$added = 0;
$targetUsers = [];

// Check basic structure
if (!is_array($data) || !isset($data['links']) || !is_array($data['links'])) {
    echo "Error: Invalid JSON structure. Must contain 'users' and 'links'.";
    exit;
}

$allUsers = $db->query("SELECT username FROM users")->fetchAll(PDO::FETCH_COLUMN);
$allUsersLower = array_map('strtolower', $allUsers);

// Determine target users
if ($data['users'] === "all") {
    $targetUsers = $allUsers;
} elseif (is_array($data['users'])) {
    foreach ($data['users'] as $u) {
        $lu = strtolower(trim($u));
        if (in_array($lu, $allUsersLower)) {
            $idx = array_search($lu, $allUsersLower);
            $targetUsers[] = $allUsers[$idx];
        } else {
            $errors[] = "User '$u' not found in database.";
        }
    }
} else {
    echo "Error: 'users' must be array or 'all'.";
    exit;
}

if (count($targetUsers) === 0) {
    echo "Error: No valid users selected.";
    exit;
}

// Assign links
foreach ($targetUsers as $user) {
    $uidStmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = :u");
    $uidStmt->execute([':u' => strtolower($user)]);
    $userId = $uidStmt->fetchColumn();

    if (!$userId) {
        $errors[] = "User ID not found for '$user'.";
        continue;
    }

    foreach ($data['links'] as $link) {
        $alias = trim($link['alias'] ?? '');
        $url = trim($link['url'] ?? '');

        if ($alias && $url) {
            $exists = $db->prepare("SELECT COUNT(*) FROM user_links WHERE user_id = :uid AND alias = :alias");
            $exists->execute([':uid' => $userId, ':alias' => $alias]);
            if ($exists->fetchColumn() == 0) {
                $ins = $db->prepare("INSERT INTO user_links (user_id, alias, url) VALUES (:uid, :alias, :url)");
                $ins->execute([':uid' => $userId, ':alias' => $alias, ':url' => $url]);
                $added++;
            }
        }
    }
}

if ($added > 0) {
    echo "$added link(s) assigned to " . count($targetUsers) . " user(s).";
} elseif (empty($errors)) {
    echo "No new links added.";
} else {
    echo "Error(s):\n" . implode("\n", $errors);
}
