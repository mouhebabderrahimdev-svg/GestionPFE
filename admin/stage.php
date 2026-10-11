<?php
/**
 * Gestion d'un stage par l'administrateur :
 *   - affectation / changement de l'encadrant académique
 *   - validation finale (après validation de l'encadrant) ou refus
 *   - suppression du dossier
 */
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');

$id = (int) ($_GET['id'] ?? 0);
$stage = get_stage($id);
if (!$stage) {
    flash('danger', 'Stage introuvable.');
    redirect('admin/stages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $commentaire = trim($_POST['commentaire'] ?? '');
    $pdo = db();

    switch ($action) {
        case 'affecter':
            $encadrantId = (int) ($_POST['encadrant_id'] ?? 0);
            $valides = array_map('intval', array_column(get_encadrants(), 'id'));
            if (!in_array($encadrantId, $valides, true)) {
                flash('danger', 'Encadrant invalide.');
                break;
            }
            $pdo->prepare('UPDATE stages SET encadrant_id = ? WHERE id = ?')->execute([$encadrantId, $id]);
            notifier($encadrantId, 'Vous avez été affecté comme encadrant du stage de '
                . $stage['etudiant_prenom'] . ' ' . $stage['etudiant_nom'], 'encadrant/stage.php?id=' . $id);
            notifier((int) $stage['etudiant_id'], 'Un encadrant académique a été affecté à votre stage', 'etudiant/stage.php?id=' . $id);
            flash('success', 'Encadrant affecté.');
            break;

        case 'valider':
            if ($stage['statut'] !== 'valide_encadrant') {
                flash('warning', "La validation finale n'est possible qu'après la validation de l'encadrant.");
                break;
            }
            changer_statut($id, 'valide', $user['id'], $commentaire ?: 'Validation finale');
            if ($stage['encadrant_id']) {
                notifier((int) $stage['encadrant_id'], 'Le stage de ' . $stage['etudiant_prenom'] . ' ' . $stage['etudiant_nom']
                    . ' est validé définitivement', 'encadrant/stage.php?id=' . $id);
            }
            flash('success', 'Stage validé définitivement.');
            break;

        case 'refuser':
            if (in_array($stage['statut'], ['refuse', 'termine'], true)) {
                break;
            }
            if ($commentaire === '') {
                flash('danger', 'Le motif du refus est obligatoire.');
                break;
            }
            changer_statut($id, 'refuse', $user['id'], $commentaire);
            flash('success', 'Stage refusé.');
            break;

        case 'supprimer':
            $pdo->prepare('DELETE FROM stages WHERE id = ?')->execute([$id]);
            foreach (['fichier_convention', 'fichier_rapport'] as $col) {
                if ($stage[$col] && is_file(UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($stage[$col]))) {
                    unlink(UPLOAD_DIR . DIRECTORY_SEPARATOR . basename($stage[$col]));
                }
            }
            flash('success', 'Stage supprimé.');
            redirect('admin/stages.php');
    }
    redirect('admin/stage.php?id=' . $id);
}

$historique = get_historique($id);
$suivis = get_suivis($id);
$encadrants = get_encadrants();

ob_start(); ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>Encadrant académique</div>
    <div class="card-body">
        <form method="post">
            <?= csrf_field() ?>
            <select name="encadrant_id" class="form-select mb-2" required>
                <option value="">— Choisir —</option>
                <?php foreach ($encadrants as $enc): ?>
                    <option value="<?= $enc['id'] ?>" <?= (int) $stage['encadrant_id'] === (int) $enc['id'] ? 'selected' : '' ?>>
                        <?= e($enc['prenom'] . ' ' . $enc['nom']) ?><?= $enc['departement'] ? ' · ' . e($enc['departement']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button name="action" value="affecter" class="btn btn-outline-primary w-100">
                <i class="bi bi-person-check me-1"></i><?= $stage['encadrant_id'] ? "Changer l'encadrant" : 'Affecter' ?>
            </button>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-patch-check me-1"></i>Validation administrative</div>
    <div class="card-body">
        <?php if (!in_array($stage['statut'], ['refuse', 'termine'], true)): ?>
            <form method="post">
                <?= csrf_field() ?>
                <textarea name="commentaire" class="form-control mb-2" rows="2" placeholder="Commentaire (obligatoire en cas de refus)"></textarea>
                <div class="d-grid gap-2">
                    <?php if ($stage['statut'] === 'valide_encadrant'): ?>
                        <button name="action" value="valider" class="btn btn-success" data-confirm="Valider définitivement ce stage ?"><i class="bi bi-check2-all me-1"></i>Validation finale</button>
                    <?php else: ?>
                        <p class="small text-muted mb-1">La validation finale sera possible après la validation de l'encadrant.</p>
                    <?php endif; ?>
                    <button name="action" value="refuser" class="btn btn-outline-danger" data-confirm="Refuser ce stage ?"><i class="bi bi-x-lg me-1"></i>Refuser</button>
                </div>
            </form>
        <?php else: ?>
            <p class="small text-muted mb-0">Dossier clôturé.</p>
        <?php endif; ?>
        <hr>
        <form method="post">
            <?= csrf_field() ?>
            <button name="action" value="supprimer" class="btn btn-sm btn-link text-danger p-0" data-confirm="Supprimer définitivement ce stage et ses documents ?">
                <i class="bi bi-trash me-1"></i>Supprimer le dossier
            </button>
        </form>
    </div>
</div>
<?php
$actionsPanel = ob_get_clean();

$pageTitle = 'Gestion du stage';
$activeMenu = 'stages';
require __DIR__ . '/../includes/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('admin/stages.php') ?>">Stages</a></li>
        <li class="breadcrumb-item active"><?= e($stage['etudiant_prenom'] . ' ' . $stage['etudiant_nom']) ?></li>
    </ol>
</nav>
<?php require __DIR__ . '/../includes/stage_details.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
