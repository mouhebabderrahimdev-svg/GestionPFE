<?php
/**
 * En-tête commun. Variables attendues (optionnelles) :
 *   $pageTitle  : titre de la page
 *   $activeMenu : clé du menu actif
 */
require_once __DIR__ . '/functions.php';

$user = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$activeMenu = $activeMenu ?? '';
$notifications = $user ? notifications_non_lues($user['id']) : [];

$menus = [
    'etudiant' => [
        'dashboard' => ['etudiant/dashboard.php', 'speedometer2', 'Tableau de bord'],
        'stage'     => ['etudiant/stage_form.php', 'plus-circle', 'Nouveau stage'],
    ],
    'encadrant' => [
        'dashboard' => ['encadrant/dashboard.php', 'speedometer2', 'Tableau de bord'],
        'stages'    => ['encadrant/stages.php', 'list-check', 'Mes étudiants'],
    ],
    'admin' => [
        'dashboard'   => ['admin/dashboard.php', 'speedometer2', 'Tableau de bord'],
        'stages'      => ['admin/stages.php', 'briefcase', 'Stages'],
        'users'       => ['admin/users.php', 'people', 'Utilisateurs'],
        'entreprises' => ['admin/entreprises.php', 'building', 'Entreprises'],
    ],
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="bg-body-tertiary d-flex flex-column min-vh-100">

<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= url(home_for_role($user['role'])) ?>">
            <i class="bi bi-mortarboard-fill me-1"></i><?= e(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menus[$user['role']] as $key => [$href, $icon, $label]): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeMenu === $key ? 'active fw-semibold' : '' ?>" href="<?= url($href) ?>">
                            <i class="bi bi-<?= $icon ?> me-1"></i><?= e($label) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <ul class="navbar-nav align-items-lg-center">
                <li class="nav-item dropdown">
                    <a class="nav-link position-relative" href="#" data-bs-toggle="dropdown" title="Notifications">
                        <i class="bi bi-bell fs-5"></i>
                        <?php if ($notifications): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= count($notifications) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end notif-menu shadow">
                        <li><h6 class="dropdown-header">Notifications</h6></li>
                        <?php if (!$notifications): ?>
                            <li><span class="dropdown-item-text text-muted small">Aucune nouvelle notification</span></li>
                        <?php endif; ?>
                        <?php foreach ($notifications as $n): ?>
                            <li>
                                <a class="dropdown-item small text-wrap" href="<?= url('notification.php?id=' . $n['id']) ?>">
                                    <?= e($n['message']) ?>
                                    <div class="text-muted" style="font-size:.75rem"><?= format_date($n['created_at'], true) ?></div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                        <?php if ($notifications): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item small text-center" href="<?= url('notification.php?all=1') ?>">Tout marquer comme lu</a></li>
                        <?php endif; ?>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= e($user['prenom'] . ' ' . $user['nom']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><span class="dropdown-item-text small text-muted"><?= e(role_label($user['role'])) ?></span></li>
                        <li><a class="dropdown-item" href="<?= url('profil.php') ?>"><i class="bi bi-gear me-2"></i>Mon profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container py-4 flex-grow-1">
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>
