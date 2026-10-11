<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

if (isset($_GET['all'])) {
    db()->prepare('UPDATE notifications SET lu = 1 WHERE utilisateur_id = ?')->execute([$user['id']]);
    redirect(home_for_role($user['role']));
}

$stmt = db()->prepare('SELECT * FROM notifications WHERE id = ? AND utilisateur_id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0), $user['id']]);
$notif = $stmt->fetch();

if ($notif) {
    db()->prepare('UPDATE notifications SET lu = 1 WHERE id = ?')->execute([$notif['id']]);
    if ($notif['lien']) {
        redirect($notif['lien']);
    }
}
redirect(home_for_role($user['role']));
