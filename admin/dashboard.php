<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');
$pdo = db();

$parStatut = array_column(
    $pdo->query('SELECT statut, COUNT(*) AS total FROM stages GROUP BY statut')->fetchAll(), 'total', 'statut'
);
$parRole = array_column(
    $pdo->query('SELECT role, COUNT(*) AS total FROM utilisateurs GROUP BY role')->fetchAll(), 'total', 'role'
);
$sansEncadrant = (int) $pdo->query("SELECT COUNT(*) FROM stages WHERE encadrant_id IS NULL AND statut NOT IN ('refuse','termine')")->fetchColumn();
$nbEntreprises = (int) $pdo->query('SELECT COUNT(*) FROM entreprises')->fetchColumn();

$stats = [
    ['Stages au total',      array_sum($parStatut),               'briefcase',       'primary',   'admin/stages.php'],
    ['Validation finale',    $parStatut['valide_encadrant'] ?? 0, 'patch-check',     'warning',   'admin/stages.php?statut=valide_encadrant'],
    ['Sans encadrant',       $sansEncadrant,                      'person-exclamation', 'danger', 'admin/stages.php?sans_encadrant=1'],
    ['Stages en cours',      $parStatut['valide'] ?? 0,           'play-circle',     'success',   'admin/stages.php?statut=valide'],
    ['Étudiants',            $parRole['etudiant'] ?? 0,           'mortarboard',     'info',      'admin/users.php?role=etudiant'],
    ['Encadrants',           $parRole['encadrant'] ?? 0,          'person-badge',    'secondary', 'admin/users.php?role=encadrant'],
    ['Entreprises',          $nbEntreprises,                      'building',        'dark',      'admin/entreprises.php'],
    ['Stages terminés',      $parStatut['termine'] ?? 0,          'flag',            'dark',      'admin/stages.php?statut=termine'],
];

$aValider = $pdo->query(
    "SELECT s.id, s.sujet, s.updated_at, et.nom, et.prenom, en.nom AS enc_nom, en.prenom AS enc_prenom
     FROM stages s JOIN utilisateurs et ON et.id = s.etudiant_id LEFT JOIN utilisateurs en ON en.id = s.encadrant_id
     WHERE s.statut = 'valide_encadrant' OR (s.encadrant_id IS NULL AND s.statut = 'soumis')
     ORDER BY s.updated_at LIMIT 10"
)->fetchAll();

$pageTitle = 'Administration';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-1">Tableau de bord administrateur</h1>
<p class="text-muted mb-4">Vue d'ensemble des stages PFE · année <?= e(annee_universitaire()) ?></p>

<div class="row g-3 mb-4">
    <?php foreach ($stats as [$label, $value, $icon, $color, $link]): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= url($link) ?>" class="text-decoration-none text-reset">
                <div class="card stat-card shadow-sm h-100" style="border-left-color: var(--bs-<?= $color ?>)">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small"><?= e($label) ?></div>
                            <div class="fs-3 fw-bold"><?= (int) $value ?></div>
                        </div>
                        <i class="bi bi-<?= $icon ?> stat-icon text-<?= $color ?>"></i>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-inbox me-1"></i>Dossiers nécessitant une action</div>
            <ul class="list-group list-group-flush">
                <?php if (!$aValider): ?>
                    <li class="list-group-item text-muted">Aucun dossier en attente.</li>
                <?php endif; ?>
                <?php foreach ($aValider as $s): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?= e($s['sujet']) ?></div>
                            <div class="small text-muted">
                                <?= e($s['prenom'] . ' ' . $s['nom']) ?> ·
                                <?= $s['enc_nom'] ? 'Validé par ' . e($s['enc_prenom'] . ' ' . $s['enc_nom']) : '<span class="text-danger">Encadrant à affecter</span>' ?>
                            </div>
                        </div>
                        <a class="btn btn-sm btn-primary" href="<?= url('admin/stage.php?id=' . $s['id']) ?>">Traiter</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-pie-chart me-1"></i>Répartition par statut</div>
            <div class="card-body">
                <?php $total = max(1, array_sum($parStatut)); ?>
                <?php foreach (statuts() as $key => $s): ?>
                    <?php $n = (int) ($parStatut[$key] ?? 0); ?>
                    <div class="d-flex justify-content-between small mb-1">
                        <span><?= e($s['label']) ?></span><span class="fw-semibold"><?= $n ?></span>
                    </div>
                    <div class="progress mb-3" style="height:.5rem">
                        <div class="progress-bar bg-<?= $s['color'] ?>" style="width: <?= round($n * 100 / $total) ?>%"></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
