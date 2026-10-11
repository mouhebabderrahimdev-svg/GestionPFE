<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(home_for_role(current_user()['role']));
}

$email = '';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
    $stmt->execute([$email]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($password, $account['mot_de_passe'])) {
        $error = 'Email ou mot de passe incorrect.';
    } elseif (!$account['actif']) {
        $error = "Votre compte est désactivé. Contactez l'administration.";
    } else {
        login_user($account);
        flash('success', 'Bienvenue ' . $account['prenom'] . ' !');
        redirect(home_for_role($account['role']));
    }
}

$pageTitle = 'Connexion';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion · <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-wrapper d-flex align-items-center justify-content-center p-3">
    <div class="card auth-card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <i class="bi bi-mortarboard-fill text-primary" style="font-size:3rem"></i>
                <h1 class="h4 mt-2 mb-1"><?= e(APP_NAME) ?></h1>
                <p class="text-muted small mb-0">Gestion et suivi des stages de fin d'études</p>
            </div>

            <?php foreach (get_flashes() as $f): ?>
                <div class="alert alert-<?= e($f['type']) ?> py-2 small"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Adresse email</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" value="<?= e($email) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Mot de passe</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Se connecter
                </button>
            </form>

            <p class="text-center small mt-4 mb-0">
                Étudiant sans compte ? <a href="<?= url('register.php') ?>">Créer un compte</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
