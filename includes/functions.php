<?php
/**
 * Fonctions utilitaires communes : session, sécurité, authentification,
 * messages flash, statuts, historique, notifications et téléversements.
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

/* ---------------------------------------------------------------------
 *  Helpers généraux
 * ------------------------------------------------------------------- */

/** Échappe une valeur pour un affichage HTML sûr. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Construit une URL absolue à partir d'un chemin relatif à l'application. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function format_date(?string $date, bool $withTime = false): string
{
    if (!$date) {
        return '—';
    }
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', strtotime($date));
}

/** Année universitaire courante, ex : 2026-2027 (bascule en septembre). */
function annee_universitaire(): string
{
    $y = (int) date('Y');
    return (int) date('n') >= 9 ? $y . '-' . ($y + 1) : ($y - 1) . '-' . $y;
}

/* ---------------------------------------------------------------------
 *  Protection CSRF
 * ------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(400);
        die('Jeton de sécurité invalide. Rechargez la page et réessayez.');
    }
}

/* ---------------------------------------------------------------------
 *  Authentification et rôles
 * ------------------------------------------------------------------- */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'     => (int) $user['id'],
        'nom'    => $user['nom'],
        'prenom' => $user['prenom'],
        'email'  => $user['email'],
        'role'   => $user['role'],
    ];
}

/** Page d'accueil propre à chaque rôle. */
function home_for_role(string $role): string
{
    switch ($role) {
        case 'admin':     return 'admin/dashboard.php';
        case 'encadrant': return 'encadrant/dashboard.php';
        default:          return 'etudiant/dashboard.php';
    }
}

function require_login(): array
{
    if (!is_logged_in()) {
        flash('warning', 'Veuillez vous connecter pour accéder à cette page.');
        redirect('login.php');
    }
    return current_user();
}

/** Restreint l'accès à un ou plusieurs rôles. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        flash('danger', "Accès refusé : cette page n'est pas disponible pour votre rôle.");
        redirect(home_for_role($user['role']));
    }
    return $user;
}

function role_label(string $role): string
{
    return ['etudiant' => 'Étudiant', 'encadrant' => 'Encadrant', 'admin' => 'Administrateur'][$role] ?? $role;
}

/* ---------------------------------------------------------------------
 *  Statuts des stages et workflow de validation
 * ------------------------------------------------------------------- */

function statuts(): array
{
    return [
        'brouillon'        => ['label' => 'Brouillon',           'color' => 'secondary', 'icon' => 'pencil'],
        'soumis'           => ['label' => 'Soumis',              'color' => 'info',      'icon' => 'send'],
        'a_corriger'       => ['label' => 'À corriger',          'color' => 'warning',   'icon' => 'exclamation-triangle'],
        'valide_encadrant' => ['label' => 'Validé par encadrant','color' => 'primary',   'icon' => 'person-check'],
        'valide'           => ['label' => 'Validé (en cours)',   'color' => 'success',   'icon' => 'check-circle'],
        'refuse'           => ['label' => 'Refusé',              'color' => 'danger',    'icon' => 'x-circle'],
        'termine'          => ['label' => 'Terminé',             'color' => 'dark',      'icon' => 'flag'],
    ];
}

function statut_badge(string $statut): string
{
    $s = statuts()[$statut] ?? ['label' => $statut, 'color' => 'secondary', 'icon' => 'question'];
    return '<span class="badge text-bg-' . $s['color'] . '"><i class="bi bi-' . $s['icon'] . ' me-1"></i>'
        . e($s['label']) . '</span>';
}

/**
 * Change le statut d'un stage, l'enregistre dans l'historique
 * et notifie l'étudiant concerné.
 */
function changer_statut(int $stageId, string $nouveau, int $userId, ?string $commentaire = null): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT statut, etudiant_id, sujet FROM stages WHERE id = ?');
    $stmt->execute([$stageId]);
    $stage = $stmt->fetch();
    if (!$stage) {
        return;
    }

    $pdo->prepare('UPDATE stages SET statut = ? WHERE id = ?')->execute([$nouveau, $stageId]);
    $pdo->prepare('INSERT INTO historique_statuts (stage_id, utilisateur_id, ancien_statut, nouveau_statut, commentaire)
                   VALUES (?, ?, ?, ?, ?)')
        ->execute([$stageId, $userId, $stage['statut'], $nouveau, $commentaire ?: null]);

    if ((int) $stage['etudiant_id'] !== $userId) {
        $label = statuts()[$nouveau]['label'] ?? $nouveau;
        notifier((int) $stage['etudiant_id'],
            'Votre stage « ' . mb_strimwidth($stage['sujet'], 0, 60, '…') . ' » est maintenant : ' . $label,
            'etudiant/stage.php?id=' . $stageId);
    }
}

/* ---------------------------------------------------------------------
 *  Notifications
 * ------------------------------------------------------------------- */

function notifier(int $userId, string $message, ?string $lien = null): void
{
    db()->prepare('INSERT INTO notifications (utilisateur_id, message, lien) VALUES (?, ?, ?)')
        ->execute([$userId, $message, $lien]);
}

/** Notifie tous les administrateurs actifs. */
function notifier_admins(string $message, ?string $lien = null): void
{
    $ids = db()->query("SELECT id FROM utilisateurs WHERE role = 'admin' AND actif = 1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        notifier((int) $id, $message, $lien);
    }
}

function notifications_non_lues(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM notifications WHERE utilisateur_id = ? AND lu = 0 ORDER BY created_at DESC LIMIT 10');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/* ---------------------------------------------------------------------
 *  Téléversement de fichiers (PDF uniquement)
 * ------------------------------------------------------------------- */

/**
 * Enregistre un PDF envoyé via le champ $field.
 * Retourne le nom du fichier stocké, null si aucun fichier, ou lève une exception.
 */
function upload_pdf(string $field, string $prefix): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erreur lors du téléversement du fichier.');
    }
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Le fichier dépasse la taille maximale de 10 Mo.');
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($ext !== 'pdf' || $finfo->file($file['tmp_name']) !== 'application/pdf') {
        throw new RuntimeException('Seuls les fichiers PDF sont acceptés.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }
    $name = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.pdf';
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . DIRECTORY_SEPARATOR . $name)) {
        throw new RuntimeException("Impossible d'enregistrer le fichier.");
    }
    return $name;
}

/* ---------------------------------------------------------------------
 *  Accès aux stages
 * ------------------------------------------------------------------- */

/** Récupère un stage avec les informations liées (étudiant, encadrant, entreprise). */
function get_stage(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT s.*,
                et.nom AS etudiant_nom, et.prenom AS etudiant_prenom, et.email AS etudiant_email, et.filiere,
                en.nom AS encadrant_nom, en.prenom AS encadrant_prenom, en.email AS encadrant_email,
                ent.nom AS entreprise_nom, ent.ville AS entreprise_ville, ent.adresse AS entreprise_adresse
         FROM stages s
         JOIN utilisateurs et ON et.id = s.etudiant_id
         LEFT JOIN utilisateurs en ON en.id = s.encadrant_id
         LEFT JOIN entreprises ent ON ent.id = s.entreprise_id
         WHERE s.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** Vérifie qu'un utilisateur a le droit de consulter un stage. */
function can_view_stage(array $stage, array $user): bool
{
    switch ($user['role']) {
        case 'admin':     return true;
        case 'encadrant': return (int) $stage['encadrant_id'] === $user['id'];
        default:          return (int) $stage['etudiant_id'] === $user['id'];
    }
}

function get_historique(int $stageId): array
{
    $stmt = db()->prepare(
        'SELECT h.*, u.nom, u.prenom, u.role FROM historique_statuts h
         JOIN utilisateurs u ON u.id = h.utilisateur_id
         WHERE h.stage_id = ? ORDER BY h.created_at DESC, h.id DESC'
    );
    $stmt->execute([$stageId]);
    return $stmt->fetchAll();
}

function get_suivis(int $stageId): array
{
    $stmt = db()->prepare(
        'SELECT s.*, u.nom, u.prenom, u.role FROM suivis s
         JOIN utilisateurs u ON u.id = s.auteur_id
         WHERE s.stage_id = ? ORDER BY s.created_at DESC, s.id DESC'
    );
    $stmt->execute([$stageId]);
    return $stmt->fetchAll();
}

function get_encadrants(): array
{
    return db()->query("SELECT id, nom, prenom, departement FROM utilisateurs
                        WHERE role = 'encadrant' AND actif = 1 ORDER BY nom, prenom")->fetchAll();
}

/**
 * Soumission d'un stage par l'étudiant : passage au statut « soumis »
 * et notification de l'encadrant affecté (ou des administrateurs à défaut).
 */
function soumettre_stage(int $stageId, array $user): void
{
    changer_statut($stageId, 'soumis', $user['id'], 'Soumission pour validation');

    $stage = get_stage($stageId);
    $message = $user['prenom'] . ' ' . $user['nom'] . ' a soumis son stage « '
        . mb_strimwidth($stage['sujet'], 0, 60, '…') . ' »';
    if ($stage['encadrant_id']) {
        notifier((int) $stage['encadrant_id'], $message, 'encadrant/stage.php?id=' . $stageId);
    } else {
        notifier_admins($message . ' (encadrant à affecter)', 'admin/stage.php?id=' . $stageId);
    }
}
