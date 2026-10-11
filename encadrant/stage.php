<?php
/**
 * Détail d'un stage côté encadrant :
 *   - validation pédagogique du sujet (valider / demander des corrections / refuser)
 *   - remarques dans le journal de suivi
 *   - évaluation finale (note) et clôture du stage
 */
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('encadrant');

$id = (int) ($_GET['id'] ?? 0);
$stage = get_stage($id);
if (!$stage || !can_view_stage($stage, $user)) {
    flash('danger', 'Stage introuvable ou non affecté à vous.');
    redirect('encadrant/stages.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $commentaire = trim($_POST['commentaire'] ?? '');
    $pdo = db();

    switch ($action) {
        case 'valider':
        case 'corriger':
        case 'refuser':
            if ($stage['statut'] !== 'soumis') {
                flash('warning', "Ce stage n'est pas en attente de votre validation.");
                break;
            }
            if ($action !== 'valider' && $commentaire === '') {
                flash('danger', 'Un commentaire est obligatoire pour demander des corrections ou refuser.');
                break;
            }
            $nouveau = ['valider' => 'valide_encadrant', 'corriger' => 'a_corriger', 'refuser' => 'refuse'][$action];
            changer_statut($id, $nouveau, $user['id'], $commentaire);
            if ($nouveau === 'valide_encadrant') {
                notifier_admins('Le stage de ' . $stage['etudiant_prenom'] . ' ' . $stage['etudiant_nom']
                    . ' attend la validation finale', 'admin/stage.php?id=' . $id);
            }
            flash('success', 'Décision enregistrée : ' . statuts()[$nouveau]['label'] . '.');
            break;

        case 'remarque':
            if ($commentaire === '') {
                flash('danger', 'La remarque est vide.');
                break;
            }
            $pdo->prepare('INSERT INTO suivis (stage_id, auteur_id, contenu) VALUES (?, ?, ?)')
                ->execute([$id, $user['id'], $commentaire]);
            notifier((int) $stage['etudiant_id'], 'Votre encadrant a ajouté une remarque sur votre suivi', 'etudiant/stage.php?id=' . $id);
            flash('success', 'Remarque ajoutée au journal de suivi.');
            break;

        case 'cloturer':
            $note = str_replace(',', '.', trim($_POST['note'] ?? ''));
            if ($stage['statut'] !== 'valide') {
                flash('warning', 'Seul un stage validé en cours peut être clôturé.');
            } elseif (!is_numeric($note) || $note < 0 || $note > 20) {
                flash('danger', 'La note doit être comprise entre 0 et 20.');
            } else {
                $pdo->prepare('UPDATE stages SET note_finale = ?, avancement = 100 WHERE id = ?')->execute([$note, $id]);
                changer_statut($id, 'termine', $user['id'], $commentaire ?: 'Stage clôturé avec la note ' . $note . '/20');
                flash('success', 'Stage clôturé et évalué.');
            }
            break;
    }
    redirect('encadrant/stage.php?id=' . $id);
}

$historique = get_historique($id);
$suivis = get_suivis($id);

ob_start(); ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white fw-semibold"><i class="bi bi-lightning me-1"></i>Actions encadrant</div>
    <div class="card-body">
        <?php if ($stage['statut'] === 'soumis'): ?>
            <form method="post">
                <?= csrf_field() ?>
                <label class="form-label small fw-semibold">Commentaire (obligatoire si correction ou refus)</label>
                <textarea name="commentaire" class="form-control mb-3" rows="3"></textarea>
                <div class="d-grid gap-2">
                    <button name="action" value="valider" class="btn btn-success" data-confirm="Valider ce sujet ?"><i class="bi bi-check-lg me-1"></i>Valider le sujet</button>
                    <button name="action" value="corriger" class="btn btn-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Demander des corrections</button>
                    <button name="action" value="refuser" class="btn btn-outline-danger" data-confirm="Refuser définitivement ce stage ?"><i class="bi bi-x-lg me-1"></i>Refuser</button>
                </div>
            </form>
        <?php elseif ($stage['statut'] === 'valide'): ?>
            <form method="post">
                <?= csrf_field() ?>
                <label class="form-label small fw-semibold">Évaluation finale</label>
                <div class="input-group mb-2">
                    <input type="number" name="note" class="form-control" min="0" max="20" step="0.25" placeholder="Note" required>
                    <span class="input-group-text">/ 20</span>
                </div>
                <textarea name="commentaire" class="form-control mb-2" rows="2" placeholder="Appréciation (facultatif)"></textarea>
                <button name="action" value="cloturer" class="btn btn-dark w-100" data-confirm="Clôturer ce stage avec cette note ?"><i class="bi bi-flag me-1"></i>Clôturer le stage</button>
                <?php if (!$stage['fichier_rapport']): ?>
                    <div class="form-text">Le rapport n'a pas encore été déposé par l'étudiant.</div>
                <?php endif; ?>
            </form>
        <?php elseif ($stage['statut'] === 'valide_encadrant'): ?>
            <p class="small text-muted mb-0"><i class="bi bi-hourglass-split me-1"></i>Vous avez validé ce sujet ; il attend la validation finale de l'administration.</p>
        <?php else: ?>
            <p class="small text-muted mb-0">Aucune action de validation disponible à ce stade.</p>
        <?php endif; ?>
    </div>
</div>
<?php
$actionsPanel = ob_get_clean();

if (in_array($stage['statut'], ['valide', 'valide_encadrant', 'termine'], true)) {
    ob_start(); ?>
    <form method="post" class="mb-4 border-bottom pb-3">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remarque">
        <label class="form-label fw-semibold small">Ajouter une remarque pour l'étudiant</label>
        <textarea name="commentaire" class="form-control mb-2" rows="2" required></textarea>
        <button class="btn btn-primary btn-sm"><i class="bi bi-chat-left-text me-1"></i>Publier</button>
    </form>
    <?php
    $suiviForm = ob_get_clean();
}

$pageTitle = 'Stage de ' . $stage['etudiant_prenom'] . ' ' . $stage['etudiant_nom'];
$activeMenu = 'stages';
require __DIR__ . '/../includes/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('encadrant/stages.php') ?>">Mes étudiants</a></li>
        <li class="breadcrumb-item active"><?= e($stage['etudiant_prenom'] . ' ' . $stage['etudiant_nom']) ?></li>
    </ol>
</nav>
<?php require __DIR__ . '/../includes/stage_details.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
