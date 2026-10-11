<?php
/**
 * Dépôt (création) ou modification d'un stage par l'étudiant.
 * Modification possible uniquement aux statuts « brouillon » et « à corriger ».
 */
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('etudiant');
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$stage = null;

if ($id) {
    $stage = get_stage($id);
    if (!$stage || (int) $stage['etudiant_id'] !== $user['id']) {
        flash('danger', 'Stage introuvable.');
        redirect('etudiant/dashboard.php');
    }
    if (!in_array($stage['statut'], ['brouillon', 'a_corriger'], true)) {
        flash('warning', 'Ce stage ne peut plus être modifié à ce stade de la validation.');
        redirect('etudiant/stage.php?id=' . $id);
    }
} else {
    $stmt = $pdo->prepare("SELECT id FROM stages WHERE etudiant_id = ? AND statut <> 'refuse' LIMIT 1");
    $stmt->execute([$user['id']]);
    if ($existing = $stmt->fetchColumn()) {
        flash('info', 'Vous avez déjà un stage en cours. Vous pouvez le consulter ou le modifier ici.');
        redirect('etudiant/stage.php?id=' . $existing);
    }
}

$entreprises = $pdo->query('SELECT id, nom, ville FROM entreprises ORDER BY nom')->fetchAll();
$encadrants = get_encadrants();

$data = [
    'sujet'                      => $stage['sujet'] ?? '',
    'description'                => $stage['description'] ?? '',
    'technologies'               => $stage['technologies'] ?? '',
    'entreprise_id'              => $stage['entreprise_id'] ?? '',
    'nouvelle_entreprise'        => '',
    'nouvelle_ville'             => '',
    'nouvelle_adresse'           => '',
    'encadrant_entreprise'       => $stage['encadrant_entreprise'] ?? '',
    'encadrant_entreprise_email' => $stage['encadrant_entreprise_email'] ?? '',
    'date_debut'                 => $stage['date_debut'] ?? '',
    'date_fin'                   => $stage['date_fin'] ?? '',
    'encadrant_id'               => $stage['encadrant_id'] ?? '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($data as $k => $_) {
        $data[$k] = trim($_POST[$k] ?? '');
    }
    $soumettre = ($_POST['action'] ?? '') === 'soumettre';

    if ($data['sujet'] === '') {
        $errors[] = 'Le sujet du stage est obligatoire.';
    }
    if ($data['entreprise_id'] === '' && $data['nouvelle_entreprise'] === '') {
        $errors[] = "Choisissez une entreprise d'accueil ou saisissez-en une nouvelle.";
    }
    if (!$data['date_debut'] || !$data['date_fin'] || !strtotime($data['date_debut']) || !strtotime($data['date_fin'])) {
        $errors[] = 'Les dates de début et de fin sont obligatoires.';
    } elseif ($data['date_fin'] <= $data['date_debut']) {
        $errors[] = 'La date de fin doit être postérieure à la date de début.';
    }
    if ($data['encadrant_entreprise_email'] !== '' && !filter_var($data['encadrant_entreprise_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email de l'encadrant entreprise est invalide.";
    }
    $encadrantIds = array_column($encadrants, 'id');
    if ($data['encadrant_id'] !== '' && !in_array((int) $data['encadrant_id'], array_map('intval', $encadrantIds), true)) {
        $errors[] = 'Encadrant sélectionné invalide.';
    }

    $convention = null;
    if (!$errors) {
        try {
            $convention = upload_pdf('convention', 'convention');
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        $pdo->beginTransaction();
        try {
            // Entreprise : existante ou nouvelle
            $entrepriseId = $data['entreprise_id'] !== '' ? (int) $data['entreprise_id'] : null;
            if ($entrepriseId === null) {
                $pdo->prepare('INSERT INTO entreprises (nom, ville, adresse) VALUES (?, ?, ?)')
                    ->execute([$data['nouvelle_entreprise'], $data['nouvelle_ville'] ?: null, $data['nouvelle_adresse'] ?: null]);
                $entrepriseId = (int) $pdo->lastInsertId();
            }

            $values = [
                $data['sujet'], $data['description'] ?: null, $data['technologies'] ?: null, $entrepriseId,
                $data['encadrant_entreprise'] ?: null, $data['encadrant_entreprise_email'] ?: null,
                $data['date_debut'], $data['date_fin'], $data['encadrant_id'] !== '' ? (int) $data['encadrant_id'] : null,
            ];

            if ($stage) {
                $sql = 'UPDATE stages SET sujet = ?, description = ?, technologies = ?, entreprise_id = ?,
                        encadrant_entreprise = ?, encadrant_entreprise_email = ?, date_debut = ?, date_fin = ?, encadrant_id = ?'
                     . ($convention ? ', fichier_convention = ?' : '') . ' WHERE id = ?';
                if ($convention) {
                    $values[] = $convention;
                }
                $values[] = $stage['id'];
                $pdo->prepare($sql)->execute($values);
                $stageId = (int) $stage['id'];
            } else {
                $values[] = $convention;
                $values[] = $user['id'];
                $values[] = annee_universitaire();
                $pdo->prepare('INSERT INTO stages (sujet, description, technologies, entreprise_id, encadrant_entreprise,
                               encadrant_entreprise_email, date_debut, date_fin, encadrant_id, fichier_convention,
                               etudiant_id, annee_universitaire) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute($values);
                $stageId = (int) $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO historique_statuts (stage_id, utilisateur_id, ancien_statut, nouveau_statut, commentaire)
                               VALUES (?, ?, NULL, 'brouillon', 'Création du dossier')")->execute([$stageId, $user['id']]);
            }

            if ($soumettre) {
                soumettre_stage($stageId, $user);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        flash('success', $soumettre ? 'Votre stage a été soumis pour validation.' : 'Stage enregistré en brouillon.');
        redirect('etudiant/stage.php?id=' . $stageId);
    }
}

$pageTitle = $stage ? 'Modifier mon stage' : 'Déposer un stage';
$activeMenu = 'stage';
require __DIR__ . '/../includes/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('etudiant/dashboard.php') ?>">Tableau de bord</a></li>
        <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
    </ol>
</nav>
<h1 class="h3 mb-4"><?= e($pageTitle) ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-lightbulb me-1"></i>Sujet</div>
        <div class="card-body row g-3">
            <div class="col-12">
                <label class="form-label">Intitulé du sujet *</label>
                <input type="text" name="sujet" class="form-control" maxlength="255" value="<?= e($data['sujet']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label">Description / objectifs</label>
                <textarea name="description" class="form-control" rows="4"><?= e($data['description']) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Technologies</label>
                <input type="text" name="technologies" class="form-control" value="<?= e($data['technologies']) ?>" placeholder="ex : PHP, MySQL, Bootstrap">
            </div>
            <div class="col-md-3">
                <label class="form-label">Date de début *</label>
                <input type="date" name="date_debut" class="form-control" value="<?= e($data['date_debut']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Date de fin *</label>
                <input type="date" name="date_fin" class="form-control" value="<?= e($data['date_fin']) ?>" required>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-building me-1"></i>Entreprise d'accueil</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Entreprise existante</label>
                <select name="entreprise_id" class="form-select" id="entrepriseSelect">
                    <option value="">— Nouvelle entreprise —</option>
                    <?php foreach ($entreprises as $ent): ?>
                        <option value="<?= $ent['id'] ?>" <?= (string) $data['entreprise_id'] === (string) $ent['id'] ? 'selected' : '' ?>>
                            <?= e($ent['nom']) ?><?= $ent['ville'] ? ' (' . e($ent['ville']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12" id="nouvelleEntreprise">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Nom de la nouvelle entreprise</label>
                        <input type="text" name="nouvelle_entreprise" class="form-control" value="<?= e($data['nouvelle_entreprise']) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ville</label>
                        <input type="text" name="nouvelle_ville" class="form-control" value="<?= e($data['nouvelle_ville']) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Adresse</label>
                        <input type="text" name="nouvelle_adresse" class="form-control" value="<?= e($data['nouvelle_adresse']) ?>">
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Encadrant en entreprise</label>
                <input type="text" name="encadrant_entreprise" class="form-control" value="<?= e($data['encadrant_entreprise']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email de l'encadrant entreprise</label>
                <input type="email" name="encadrant_entreprise_email" class="form-control" value="<?= e($data['encadrant_entreprise_email']) ?>">
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1"></i>Encadrement académique et documents</div>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label">Encadrant souhaité</label>
                <select name="encadrant_id" class="form-select">
                    <option value="">— Laisser l'administration affecter —</option>
                    <?php foreach ($encadrants as $enc): ?>
                        <option value="<?= $enc['id'] ?>" <?= (string) $data['encadrant_id'] === (string) $enc['id'] ? 'selected' : '' ?>>
                            <?= e($enc['prenom'] . ' ' . $enc['nom']) ?><?= $enc['departement'] ? ' · ' . e($enc['departement']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Convention de stage (PDF, 10 Mo max)</label>
                <input type="file" name="convention" class="form-control" accept="application/pdf">
                <?php if (!empty($stage['fichier_convention'])): ?>
                    <div class="form-text">Une convention est déjà déposée ; un nouveau fichier la remplacera.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 justify-content-end">
        <a href="<?= url('etudiant/dashboard.php') ?>" class="btn btn-light">Annuler</a>
        <button type="submit" name="action" value="brouillon" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Enregistrer en brouillon</button>
        <button type="submit" name="action" value="soumettre" class="btn btn-primary" data-confirm="Soumettre votre stage pour validation ? Vous ne pourrez plus le modifier sauf demande de correction.">
            <i class="bi bi-send me-1"></i>Enregistrer et soumettre
        </button>
    </div>
</form>

<script>
    // Affiche les champs « nouvelle entreprise » uniquement si aucune entreprise existante n'est choisie
    (function () {
        const select = document.getElementById('entrepriseSelect');
        const bloc = document.getElementById('nouvelleEntreprise');
        const toggle = () => bloc.style.display = select.value === '' ? '' : 'none';
        select.addEventListener('change', toggle);
        toggle();
    })();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
