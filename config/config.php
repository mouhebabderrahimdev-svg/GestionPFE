<?php
/**
 * Configuration générale de l'application.
 * Adapter les paramètres MySQL si besoin (Laragon : root sans mot de passe).
 */

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'gestion_pfe');
define('DB_USER', 'root');
define('DB_PASS', '');

define('APP_NAME', 'Gestion PFE');
define('APP_ROOT', realpath(__DIR__ . '/..'));
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 Mo

date_default_timezone_set('Africa/Tunis');

/**
 * URL de base détectée automatiquement, pour fonctionner aussi bien avec
 * http://localhost/GestionPFE/ qu'avec http://gestionpfe.test/ (Laragon).
 */
if (!defined('BASE_URL')) {
    $base = '';
    $scriptFile = isset($_SERVER['SCRIPT_FILENAME']) ? realpath($_SERVER['SCRIPT_FILENAME']) : false;
    if ($scriptFile && isset($_SERVER['SCRIPT_NAME']) && stripos($scriptFile, APP_ROOT) === 0) {
        $relative = str_replace('\\', '/', substr($scriptFile, strlen(APP_ROOT)));
        $base = substr($_SERVER['SCRIPT_NAME'], 0, strlen($_SERVER['SCRIPT_NAME']) - strlen($relative));
    }
    define('BASE_URL', rtrim($base, '/'));
}
