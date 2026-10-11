<?php
/**
 * Détail du stage côté étudiant : soumission, journal de suivi, dépôt du rapport.
 */
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('etudiant');

$id = (int) ($_GET['id'] ?? 0);
$stage = get_stage($id);
if (!$stage || !can_view_stage($stage, $user)) {
    flash('danger', 'Stage introuvable.');
    redirect('etudiant/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $pdo = db();

    switch ($action) {
        case 'soumettre':
            if (in_array($stage['statut'], ['brouillon', 'a_corriger'], true)) {
                soumettre_stage($id, $user);
                flash('success', 'Votre stage a été soumis pour validation.');
            }
            break;

        case 'supprimer':
            if ($stage['statut'] === 'brouillon') {
                $pdo->prepare('DELETE FROM stages WHERE id = ?')->execute([$id]);
                flash('success', 'Brouillon supprimé.');
                redirect('etudiant/dashboard.php');
            }
            break;

        case 'suivi':
            $contenu = trim($_POST['contenu'] ?? '');
            $avancement = max(0, min(100, (int) ($_POST['avancement'] ?? 0)));
            if ($stage['statut'] !== 'valide') {
                flash('warning', 'Le journal de suivi est disponible une fois le stage validé.');
            } elseif ($contenu === '') {
                flash('danger', 'Le contenu du suivi est obligatoire.');
            } else {
                $pdo->prepare('INSERT INTO suivis (stage_id, auteur_id, contenu, avancement) VALUES (?, ?, ?, ?)')
                    ->execute([$id, $user['id'], $contenu, $avancement]);
                $pdo->prepare('UPDATE stages SET avancement = ? WHERE id = ?')->execute([$avancement, $id]);
                if ($stage['encadrant_id']) {
                    notifier((int) $stage['encadrant_id'],
                        $user['prenom'] . ' ' . $user['nom'] . ' a ajouté un suivi (' . $avancement . '%)',
                        'encadrant/stage.php?id=' . $id);
                }
                flash('success', 'Suivi enregistré.');
            }
            break;

        case 'rapport':
            if ($stage['statut'] !== 'valide') {
                flash('warning', 'Le rapport peut être déposé uniquement pendant le stage validé.');
                break;
            }
            try {
                $fichier = upload_pdf('rapport', 'rapport');
                if (!$fichier) {
                    flash('danger', 'Veuillez choisir un fichier PDF.');
                } else {
                    $pdo->prepare('UPDATE stages SET fichier_rapport = ? WHERE id = ?')->execute([$fichier, $id]);
                    if ($stage['encadrant_id']) {
                        notifier((int) $stage['encadrant_id'],
                            $user['prenom'] . ' ' . $user['nom'] . ' a déposé son rapport de stage',
                            'encadrant/stage.php?id=' . $id);
                    }
                    flash('success', 'Rapport déposé avec succès.');
                }
            } catch (RuntimeException $ex) {
                flash('danger', $ex->getMessage());
            }
            break;
    }
    redirect('etudiant/stage.php?id=' . $id);
}

$historique = get_historique($id);
$suivis = get_suivis($id);

// Panneau d'actions (colonne de droite)
ob_start(); ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-lightning me-1"></i>Actions</div>
    <div class="card-body d-grid gap-2">
        <?php if (in_array($stage['statut'], ['brouillon', 'a_corriger'], true)): ?>
            <a href="<?= url('etudiant/stage_form.php?id=' . $id) ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Modifier</a>
            <form method="post" class="d-grid">
                <?= csrf_field() ?>
                <button name="action" value="soumettre" class="btn btn-primary" data-confirm="Soumettre ce stage pour validation ?">
                    <i class="bi bi-send me-1"></i><?= $stage['statut'] === 'a_corriger' ? 'Soumettre à nouveau' : 'Soumettre pour validation' ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if ($stage['statut'] === 'brouillon'): ?>
            <form method="post" class="d-grid">
                <?= csrf_field() ?>
                <button name="action" value="supprimer" class="btn btn-outline-danger" data-confirm="Supprimer définitivement ce brouillon ?">
                    <i class="bi bi-trash me-1"></i>Supprimer le brouillon
                </button>
            </form>
        <?php endif; ?>
        <?php if ($stage['statut'] === 'valide'): ?>
            <form method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label class="form-label small fw-semibold">Rapport de stage (PDF)</label>
                <input type="file" name="rapport" class="form-control form-control-sm mb-2" accept="application/pdf" required>
                <button name="action" value="rapport" class="btn btn-success btn-sm w-100"><i class="bi bi-upload me-1"></i>Déposer le rapport</button>
            </form>
        <?php endif; ?>
        <?php if (in_array($stage['statut'], ['soumis', 'valide_encadrant'], true)): ?>
            <p class="small text-muted mb-0"><i class="bi bi-hourglass-split me-1"></i>Votre dossier est en cours de validation. Vous serez notifié à chaque étape.</p>
        <?php elseif (in_array($stage['statut'], ['refuse', 'termine'], true)): ?>
            <p class="small text-muted mb-0">Aucune action disponible.</p>
        <?php endif; ?>
    </div>
</div>
<?php
$actionsPanel = ob_get_clean();

if ($stage['statut'] === 'valide') {
    ob_start(); ?>
    <form method="post" class="mb-4 border-bottom pb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="suivi">
        <label class="form-label fw-semibold small">Nouvelle entrée de suivi</label>
        <textarea name="contenu" class="form-control mb-2" rows="3" placeholder="Travail réalisé, difficultés rencontrées, prochaines étapes…" required></textarea>
        <div class="d-flex align-items-center gap-3">
            <label class="small text-nowrap">Avancement : <b id="avVal"><?= (int) $stage['avancement'] ?></b>%</label>
            <input type="range" class="form-range" name="avancement" min="0" max="100" step="5" value="<?= (int) $stage['avancement'] ?>"
                   oninput="document.getElementById('avVal').textContent = this.value">
            <button class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-plus-lg me-1"></i>Ajouter</button>
        </div>
    </form>
    <?php
    $suiviForm = ob_get_clean();
}

$pageTitle = 'Mon stage';
$activeMenu = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('etudiant/dashboard.php') ?>">Tableau de bord</a></li>
        <li class="breadcrumb-item active">Mon stage</li>
    </ol>
</nav>
<?php require __DIR__ . '/../includes/stage_details.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
