<?php
/**
 * Téléchargement sécurisé des documents d'un stage (convention, rapport).
 * Les fichiers ne sont jamais servis directement depuis /uploads.
 */
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$stage = get_stage((int) ($_GET['id'] ?? 0));
$type = $_GET['type'] ?? '';
$column = ['convention' => 'fichier_convention', 'rapport' => 'fichier_rapport'][$type] ?? null;

if (!$stage || !$column || !can_view_stage($stage, $user) || empty($stage[$column])) {
    http_response_code(404);
    die('Document introuvable.');
}

$path = UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($stage[$column]);
if (!is_file($path)) {
    http_response_code(404);
    die('Fichier manquant sur le serveur.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: inline; filename="' . $type . '_stage_' . $stage['id'] . '.pdf"');
header('X-Content-Type-Options: nosniff');
readfile($path);
