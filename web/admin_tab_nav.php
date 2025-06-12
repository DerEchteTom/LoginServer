<?php
// Datei: admin_tab_nav.php â€“ Stand: 2025-04-23 12:28 Europe/Berlin

if (!isset($db)) {
    $db = new PDO('sqlite:users.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

$count = $db->query("SELECT COUNT(*) FROM link_requests WHERE status = 'open'")->fetchColumn();
$inactiveCount = $db->query("SELECT COUNT(*) FROM users WHERE active = 0")->fetchColumn();
?>
<nav class="mb-4">
    <div class="btn-group d-flex flex-wrap" role="group">
        <a href="admin_tab_users.php" class="btn btn-outline-secondary btn-sm">
            user administration
            <?php if ($inactiveCount > 0): ?>
                <span class="badge bg-secondary-subtle text-dark ms-2"><?= $inactiveCount ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_tab_adimport.php" class="btn btn-outline-secondary btn-sm">ad import</a>
        <a href="admin_tab_links.php" class="btn btn-outline-secondary btn-sm">user links</a>
        <a href="admin_tab_linkrequests.php" class="btn btn-outline-secondary btn-sm">
            link requests
            <?php if ($count > 0): ?>
                <span class="badge bg-secondary-subtle text-dark ms-2"><?= $count ?></span>
            <?php endif; ?>
        </a>
	<a href="admin_tab_linkeditor.php" class="btn btn-outline-secondary btn-sm">link editor</a>
        <a href="admin_tab_logs.php" class="btn btn-outline-secondary btn-sm">log</a>
        <a href="admin_tab_mailtest.php" class="btn btn-outline-secondary btn-sm">email connect</a>
        <a href="admin_tab_ldaptest.php" class="btn btn-outline-secondary btn-sm">ad connect</a>
        <a href="admin_tab_status.php" class="btn btn-outline-secondary btn-sm">systemstatus</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">logout</a>
    </div>
</nav>
