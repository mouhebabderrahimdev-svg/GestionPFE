<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('encadrant');
$pdo = db();

$stmt = $pdo->prepare('SELECT statut, COUNT(*) AS total FROM stages WHERE encadrant_id = ? GROUP BY statut');
$stmt->execute([$user['id']]);
$parStatut = array_column($stmt->fetchAll(), 'total', 'statut');

$stats = [
    ['À valider',  $parStatut['soumis'] ?? 0,     'hourglass-split', 'warning'],
    ['En cours',   $parStatut['valide'] ?? 0,     'play-circle',     'success'],
    ['Terminés',   $parStatut['termine'] ?? 0,    'flag',            'dark'],
    ['Total encadrés', array_sum($parStatut),     'people',          'primary'],
];

$stmt = $pdo->prepare(
    "SELECT s.id, s.sujet, s.updated_at, u.nom, u.prenom, u.filiere
     FROM stages s JOIN utilisateurs u ON u.id = s.etudiant_id
     WHERE s.encadrant_id = ? AND s.statut = 'soumis' ORDER BY s.updated_at"
);
$stmt->execute([$user['id']]);
$aValider = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT su.contenu, su.created_at, su.stage_id, u.nom, u.prenom
     FROM suivis su JOIN stages s ON s.id = su.stage_id JOIN utilisateurs u ON u.id = su.auteur_id
     WHERE s.encadrant_id = ? AND su.auteur_id <> ? ORDER BY su.created_at DESC LIMIT 5"
);
$stmt->execute([$user['id'], $user['id']]);
$derniersSuivis = $stmt->fetchAll();

$pageTitle = 'Tableau de bord';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-1">Bonjour <?= e($user['prenom']) ?></h1>
<p class="text-muted mb-4">Validez les sujets de vos étudiants et suivez leur avancement.</p>

<div class="row g-3 mb-4">
    <?php foreach ($stats as [$label, $value, $icon, $color]): ?>
        <div class="col-6 col-lg-3">
            <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--bs-<?= $color ?>)">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small"><?= e($label) ?></div>
                        <div class="fs-3 fw-bold"><?= (int) $value ?></div>
                    </div>
                    <i class="bi bi-<?= $icon ?> stat-icon text-<?= $color ?>"></i>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-inbox me-1"></i>Sujets en attente de validation</div>
            <ul class="list-group list-group-flush">
                <?php if (!$aValider): ?>
                    <li class="list-group-item text-muted">Aucun sujet en attente. 🎉</li>
                <?php endif; ?>
                <?php foreach ($aValider as $s): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?= e($s['sujet']) ?></div>
                            <div class="small text-muted"><?= e($s['prenom'] . ' ' . $s['nom']) ?> · <?= e($s['filiere']) ?> · soumis le <?= format_date($s['updated_at']) ?></div>
                        </div>
                        <a class="btn btn-sm btn-primary" href="<?= url('encadrant/stage.php?id=' . $s['id']) ?>">Examiner</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-text me-1"></i>Derniers suivis des étudiants</div>
            <ul class="list-group list-group-flush">
                <?php if (!$derniersSuivis): ?>
                    <li class="list-group-item text-muted">Aucun suivi pour le moment.</li>
                <?php endif; ?>
                <?php foreach ($derniersSuivis as $s): ?>
                    <a class="list-group-item list-group-item-action" href="<?= url('encadrant/stage.php?id=' . $s['stage_id']) ?>">
                        <div class="d-flex justify-content-between small">
                            <strong><?= e($s['prenom'] . ' ' . $s['nom']) ?></strong>
                            <span class="text-muted"><?= format_date($s['created_at']) ?></span>
                        </div>
                        <div class="small text-truncate"><?= e($s['contenu']) ?></div>
                    </a>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
