<?php
/**
 * Création / modification d'un utilisateur par l'administrateur.
 */
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');
$pdo = db();

$id = (int) ($_GET['id'] ?? 0);
$account = null;
if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $stmt->execute([$id]);
    $account = $stmt->fetch();
    if (!$account) {
        flash('danger', 'Utilisateur introuvable.');
        redirect('admin/users.php');
    }
}

$data = [
    'nom'         => $account['nom'] ?? '',
    'prenom'      => $account['prenom'] ?? '',
    'email'       => $account['email'] ?? '',
    'role'        => $account['role'] ?? ($_GET['role'] ?? 'encadrant'),
    'telephone'   => $account['telephone'] ?? '',
    'filiere'     => $account['filiere'] ?? '',
    'departement' => $account['departement'] ?? '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($data as $k => $_) {
        $data[$k] = trim($_POST[$k] ?? '');
    }
    $password = $_POST['password'] ?? '';

    if ($data['nom'] === '' || $data['prenom'] === '') {
        $errors[] = 'Le nom et le prénom sont obligatoires.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if (!in_array($data['role'], ['etudiant', 'encadrant', 'admin'], true)) {
        $errors[] = 'Rôle invalide.';
    }
    if ($account && (int) $account['id'] === $user['id'] && $data['role'] !== 'admin') {
        $errors[] = 'Vous ne pouvez pas retirer votre propre rôle administrateur.';
    }
    if ((!$account || $password !== '') && strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = ? AND id <> ?');
    $stmt->execute([$data['email'], $id]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Cette adresse email est déjà utilisée.';
    }

    if (!$errors) {
        $values = [$data['nom'], $data['prenom'], $data['email'], $data['role'], $data['telephone'] ?: null,
                   $data['role'] === 'etudiant' ? ($data['filiere'] ?: null) : null,
                   $data['role'] !== 'etudiant' ? ($data['departement'] ?: null) : null];
        if ($account) {
            $sql = 'UPDATE utilisateurs SET nom = ?, prenom = ?, email = ?, role = ?, telephone = ?, filiere = ?, departement = ?';
            if ($password !== '') {
                $sql .= ', mot_de_passe = ?';
                $values[] = password_hash($password, PASSWORD_DEFAULT);
            }
            $values[] = $id;
            $pdo->prepare($sql . ' WHERE id = ?')->execute($values);
            flash('success', 'Utilisateur mis à jour.');
        } else {
            $values[] = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO utilisateurs (nom, prenom, email, role, telephone, filiere, departement, mot_de_passe)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute($values);
            flash('success', 'Utilisateur créé.');
        }
        redirect('admin/users.php?role=' . $data['role']);
    }
}

$pageTitle = $account ? 'Modifier un utilisateur' : 'Nouvel utilisateur';
$activeMenu = 'users';
require __DIR__ . '/../includes/header.php';
?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= url('admin/users.php') ?>">Utilisateurs</a></li>
        <li class="breadcrumb-item active"><?= e($pageTitle) ?></li>
    </ol>
</nav>
<h1 class="h3 mb-4"><?= e($pageTitle) ?></h1>

<?php if ($errors): ?>
    <div class="alert alert-danger"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="card shadow-sm border-0" style="max-width:760px">
    <div class="card-body">
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="nom" class="form-control" value="<?= e($data['nom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Prénom *</label>
                    <input type="text" name="prenom" class="form-control" value="<?= e($data['prenom']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="telephone" class="form-control" value="<?= e($data['telephone']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Rôle *</label>
                    <select name="role" class="form-select" id="roleSelect">
                        <?php foreach (['etudiant', 'encadrant', 'admin'] as $r): ?>
                            <option value="<?= $r ?>" <?= $data['role'] === $r ? 'selected' : '' ?>><?= e(role_label($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" data-role-field="etudiant">
                    <label class="form-label">Filière / Classe</label>
                    <input type="text" name="filiere" class="form-control" value="<?= e($data['filiere']) ?>">
                </div>
                <div class="col-md-6" data-role-field="encadrant admin">
                    <label class="form-label">Département</label>
                    <input type="text" name="departement" class="form-control" value="<?= e($data['departement']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mot de passe <?= $account ? '<small class="text-muted">(laisser vide pour conserver)</small>' : '*' ?></label>
                    <input type="password" name="password" class="form-control" minlength="8" <?= $account ? '' : 'required' ?>>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2 justify-content-end">
                <a href="<?= url('admin/users.php') ?>" class="btn btn-light">Annuler</a>
                <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<script>
    (function () {
        const select = document.getElementById('roleSelect');
        const toggle = () => document.querySelectorAll('[data-role-field]').forEach(el => {
            el.style.display = el.dataset.roleField.split(' ').includes(select.value) ? '' : 'none';
        });
        select.addEventListener('change', toggle);
        toggle();
    })();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
