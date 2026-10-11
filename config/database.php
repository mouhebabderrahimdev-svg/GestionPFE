<?php
require_once __DIR__ . '/config.php';

/**
 * Retourne une connexion PDO unique (singleton).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('<div style="font-family:sans-serif;padding:2rem">'
                . '<h3>Connexion à la base de données impossible</h3>'
                . '<p>Vérifiez que MySQL est démarré dans Laragon et que la base <b>' . DB_NAME
                . '</b> a été importée depuis <code>database/gestion_pfe.sql</code>.</p>'
                . '<small>' . htmlspecialchars($e->getMessage()) . '</small></div>');
        }
    }
    return $pdo;
}
