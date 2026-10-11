<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('etudiant');

$stmt = db()->prepare(
    'SELECT s.*, ent.nom AS entreprise_nom, en.nom AS encadrant_nom, en.prenom AS encadrant_prenom
     FROM stages s
     LEFT JOIN entreprises ent ON ent.id = s.entreprise_id
     LEFT JOIN utilisateurs en ON en.id = s.encadrant_id
     WHERE s.etudiant_id = ? ORDER BY s.created_at DESC'
);
$stmt->execute([$user['id']]);
$stages = $stmt->fetchAll();

// Un étudiant ne peut avoir qu'un seul stage actif (non refusé) à la fois.
$stageActif = null;
foreach ($stages as $s) {
    if ($s['statut'] !== 'refuse') {
        $stageActif = $s;
        break;
    }
}

$pageTitle = 'Tableau de bord';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Bonjour <?= e($user['prenom']) ?> 👋</h1>
        <p class="text-muted mb-0">Suivez ici l'avancement de votre stage de fin d'études.</p>
    </div>
    <?php if (!$stageActif): ?>
        <a href="<?= url('etudiant/stage_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Déposer mon stage</a>
    <?php endif; ?>
</div>

<?php if ($stageActif): ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <div class="text-muted small text-uppercase">Mon stage PFE</div>
                    <h2 class="h5 mt-1"><?= e($stageActif['sujet']) ?></h2>
                    <div class="small text-muted">
                        <i class="bi bi-building me-1"></i><?= e($stageActif['entreprise_nom'] ?? '—') ?>
                        · <i class="bi bi-calendar-range me-1"></i><?= format_date($stageActif['date_debut']) ?> → <?= format_date($stageActif['date_fin']) ?>
                        · <i class="bi bi-person-badge me-1"></i><?= $stageActif['encadrant_nom'] ? e($stageActif['encadrant_prenom'] . ' ' . $stageActif['encadrant_nom']) : 'Encadrant non affecté' ?>
                    </div>
                </div>
                <div><?= statut_badge($stageActif['statut']) ?></div>
            </div>
            <div class="progress mt-3" style="height:1.25rem">
                <div class="progress-bar" style="width: <?= (int) $stageActif['avancement'] ?>%"><?= (int) $stageActif['avancement'] ?>%</div>
            </div>
            <div class="mt-3">
                <a href="<?= url('etudiant/stage.php?id=' . $stageActif['id']) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye me-1"></i>Voir le détail</a>
                <?php if (in_array($stageActif['statut'], ['brouillon', 'a_corriger'], true)): ?>
                    <a href="<?= url('etudiant/stage_form.php?id=' . $stageActif['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>Modifier</a>
                <?php endif; ?>
            </div>
            <?php if ($stageActif['statut'] === 'brouillon'): ?>
                <div class="alert alert-info small mt-3 mb-0">Votre stage est en brouillon : ouvrez le détail pour le <b>soumettre</b> à validation.</div>
            <?php elseif ($stageActif['statut'] === 'a_corriger'): ?>
                <div class="alert alert-warning small mt-3 mb-0">Votre encadrant a demandé des corrections. Modifiez votre dossier puis soumettez-le à nouveau.</div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body text-center py-5">
            <i class="bi bi-briefcase text-primary" style="font-size:3rem"></i>
            <h2 class="h5 mt-3">Vous n'avez pas encore déposé de stage</h2>
            <p class="text-muted">Renseignez votre sujet, l'entreprise d'accueil et la période pour lancer la validation.</p>
            <a href="<?= url('etudiant/stage_form.php') ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Déposer mon stage</a>
        </div>
    </div>
<?php endif; ?>

<?php if (count($stages) > 1 || ($stages && !$stageActif)): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-semibold">Historique de mes demandes</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light"><tr><th>Sujet</th><th>Entreprise</th><th>Année</th><th>Statut</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($stages as $s): ?>
                    <tr>
                        <td><?= e($s['sujet']) ?></td>
                        <td><?= e($s['entreprise_nom'] ?? '—') ?></td>
                        <td><?= e($s['annee_universitaire']) ?></td>
                        <td><?= statut_badge($s['statut']) ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('etudiant/stage.php?id=' . $s['id']) ?>">Voir</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
