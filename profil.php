<?php
require_once __DIR__ . '/includes/functions.php';
$user = require_login();

$stmt = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
$stmt->execute([$user['id']]);
$account = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'infos') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        if ($nom === '' || $prenom === '') {
            flash('danger', 'Le nom et le prénom sont obligatoires.');
        } else {
            db()->prepare('UPDATE utilisateurs SET nom = ?, prenom = ?, telephone = ? WHERE id = ?')
                ->execute([$nom, $prenom, $telephone ?: null, $user['id']]);
            $_SESSION['user']['nom'] = $nom;
            $_SESSION['user']['prenom'] = $prenom;
            flash('success', 'Profil mis à jour.');
        }
    } elseif ($action === 'password') {
        $actuel = $_POST['actuel'] ?? '';
        $nouveau = $_POST['nouveau'] ?? '';
        if (!password_verify($actuel, $account['mot_de_passe'])) {
            flash('danger', 'Mot de passe actuel incorrect.');
        } elseif (strlen($nouveau) < 8 || $nouveau !== ($_POST['confirmation'] ?? '')) {
            flash('danger', 'Le nouveau mot de passe doit contenir 8 caractères minimum et être confirmé.');
        } else {
            db()->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')
                ->execute([password_hash($nouveau, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Mot de passe modifié.');
        }
    }
    redirect('profil.php');
}

$pageTitle = 'Mon profil';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h3 mb-4"><i class="bi bi-person-gear me-2"></i>Mon profil</h1>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Informations personnelles</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="infos">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nom</label>
                            <input type="text" name="nom" class="form-control" value="<?= e($account['nom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Prénom</label>
                            <input type="text" name="prenom" class="form-control" value="<?= e($account['prenom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?= e($account['email']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="text" name="telephone" class="form-control" value="<?= e($account['telephone']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Rôle</label>
                            <input type="text" class="form-control" value="<?= e(role_label($account['role'])) ?><?= $account['filiere'] ? ' · ' . e($account['filiere']) : '' ?><?= $account['departement'] ? ' · ' . e($account['departement']) : '' ?>" disabled>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3"><i class="bi bi-save me-1"></i>Enregistrer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold">Changer le mot de passe</div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="password">
                    <div class="mb-3">
                        <label class="form-label">Mot de passe actuel</label>
                        <input type="password" name="actuel" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe</label>
                        <input type="password" name="nouveau" class="form-control" minlength="8" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmation</label>
                        <input type="password" name="confirmation" class="form-control" minlength="8" required>
                    </div>
                    <button class="btn btn-outline-primary"><i class="bi bi-key me-1"></i>Modifier</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
