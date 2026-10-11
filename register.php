<?php
/**
 * Inscription publique réservée aux étudiants.
 * Les comptes encadrant et administrateur sont créés par l'administrateur.
 */
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(home_for_role(current_user()['role']));
}

$data = ['nom' => '', 'prenom' => '', 'email' => '', 'telephone' => '', 'filiere' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($data as $k => $_) {
        $data[$k] = trim($_POST[$k] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($data['nom'] === '' || $data['prenom'] === '') {
        $errors[] = 'Le nom et le prénom sont obligatoires.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if ($data['filiere'] === '') {
        $errors[] = 'La filière est obligatoire.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }
    if (!$errors) {
        $stmt = db()->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = ?');
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Un compte existe déjà avec cette adresse email.';
        }
    }

    if (!$errors) {
        db()->prepare("INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role, telephone, filiere)
                       VALUES (?, ?, ?, ?, 'etudiant', ?, ?)")
            ->execute([$data['nom'], $data['prenom'], $data['email'],
                       password_hash($password, PASSWORD_DEFAULT), $data['telephone'] ?: null, $data['filiere']]);
        flash('success', 'Compte créé avec succès. Vous pouvez vous connecter.');
        redirect('login.php');
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscription · <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper d-flex align-items-center justify-content-center p-3">
    <div class="card auth-card shadow-lg" style="max-width:560px">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-person-plus-fill text-primary" style="font-size:2.5rem"></i>
                <h1 class="h4 mt-2 mb-1">Inscription étudiant</h1>
                <p class="text-muted small mb-0">Créez votre compte pour déposer votre stage PFE</p>
            </div>

            <?php if ($errors): ?>
                <div class="alert alert-danger py-2 small">
                    <ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

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
                    <div class="col-12">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="telephone" class="form-control" value="<?= e($data['telephone']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Filière / Classe *</label>
                        <input type="text" name="filiere" class="form-control" value="<?= e($data['filiere']) ?>" placeholder="ex : Licence GLSI 3" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mot de passe *</label>
                        <input type="password" name="password" class="form-control" minlength="8" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmation *</label>
                        <input type="password" name="password_confirm" class="form-control" minlength="8" required>
                    </div>
                </div>
                <button class="btn btn-primary w-100 mt-4" type="submit">
                    <i class="bi bi-check2-circle me-1"></i>Créer mon compte
                </button>
            </form>
            <p class="text-center small mt-4 mb-0">
                Déjà inscrit ? <a href="<?= url('login.php') ?>">Se connecter</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
