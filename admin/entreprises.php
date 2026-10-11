<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $entId = (int) ($_POST['id'] ?? 0);
    $champs = [];
    foreach (['nom', 'adresse', 'ville', 'telephone', 'email'] as $k) {
        $champs[$k] = trim($_POST[$k] ?? '');
    }

    if ($action === 'enregistrer') {
        if ($champs['nom'] === '') {
            flash('danger', "Le nom de l'entreprise est obligatoire.");
        } elseif ($champs['email'] !== '' && !filter_var($champs['email'], FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Email invalide.');
        } else {
            $values = array_map(fn($v) => $v === '' ? null : $v, array_values($champs));
            if ($entId) {
                $values[] = $entId;
                $pdo->prepare('UPDATE entreprises SET nom = ?, adresse = ?, ville = ?, telephone = ?, email = ? WHERE id = ?')->execute($values);
                flash('success', 'Entreprise mise à jour.');
            } else {
                $pdo->prepare('INSERT INTO entreprises (nom, adresse, ville, telephone, email) VALUES (?, ?, ?, ?, ?)')->execute($values);
                flash('success', 'Entreprise ajoutée.');
            }
        }
    } elseif ($action === 'supprimer' && $entId) {
        $pdo->prepare('DELETE FROM entreprises WHERE id = ?')->execute([$entId]);
        flash('success', 'Entreprise supprimée.');
    }
    redirect('admin/entreprises.php');
}

$entreprises = $pdo->query(
    'SELECT e.*, (SELECT COUNT(*) FROM stages s WHERE s.entreprise_id = e.id) AS nb_stages
     FROM entreprises e ORDER BY e.nom'
)->fetchAll();

$edit = null;
if (isset($_GET['edit'])) {
    foreach ($entreprises as $ent) {
        if ((int) $ent['id'] === (int) $_GET['edit']) {
            $edit = $ent;
        }
    }
}

$pageTitle = 'Entreprises';
$activeMenu = 'entreprises';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="h3 mb-3">Entreprises d'accueil</h1>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold"><?= $edit ? "Modifier l'entreprise" : 'Ajouter une entreprise' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
                    <?php foreach (['nom' => 'Nom *', 'ville' => 'Ville', 'adresse' => 'Adresse', 'telephone' => 'Téléphone', 'email' => 'Email'] as $k => $label): ?>
                        <div class="mb-2">
                            <label class="form-label small"><?= $label ?></label>
                            <input type="<?= $k === 'email' ? 'email' : 'text' ?>" name="<?= $k ?>" class="form-control" value="<?= e($edit[$k] ?? '') ?>" <?= $k === 'nom' ? 'required' : '' ?>>
                        </div>
                    <?php endforeach; ?>
                    <div class="d-flex gap-2 mt-3">
                        <button name="action" value="enregistrer" class="btn btn-primary flex-fill"><i class="bi bi-save me-1"></i>Enregistrer</button>
                        <?php if ($edit): ?><a href="<?= url('admin/entreprises.php') ?>" class="btn btn-light">Annuler</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><?= count($entreprises) ?> entreprise(s)</span>
                <input type="search" class="form-control form-control-sm w-auto" placeholder="Rechercher…" data-table-filter="#tableEnt">
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="tableEnt">
                    <thead class="table-light"><tr><th>Nom</th><th>Ville</th><th>Contact</th><th>Stages</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($entreprises as $ent): ?>
                        <tr>
                            <td><?= e($ent['nom']) ?><div class="small text-muted"><?= e($ent['adresse']) ?></div></td>
                            <td><?= e($ent['ville'] ?: '—') ?></td>
                            <td class="small"><?= e($ent['email']) ?><br><?= e($ent['telephone']) ?></td>
                            <td><span class="badge text-bg-light border"><?= (int) $ent['nb_stages'] ?></span></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/entreprises.php?edit=' . $ent['id']) ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $ent['id'] ?>">
                                    <button name="action" value="supprimer" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer cette entreprise ?"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
