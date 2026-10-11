<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $targetId = (int) ($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($targetId === $user['id']) {
        flash('warning', 'Vous ne pouvez pas modifier ou supprimer votre propre compte ici.');
    } elseif ($action === 'toggle') {
        $pdo->prepare('UPDATE utilisateurs SET actif = 1 - actif WHERE id = ?')->execute([$targetId]);
        flash('success', 'Statut du compte mis à jour.');
    } elseif ($action === 'supprimer') {
        $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$targetId]);
        flash('success', 'Utilisateur supprimé.');
    }
    redirect('admin/users.php' . (isset($_GET['role']) ? '?role=' . urlencode($_GET['role']) : ''));
}

$role = $_GET['role'] ?? '';
$sql = 'SELECT u.*,
               (SELECT COUNT(*) FROM stages s WHERE s.encadrant_id = u.id AND s.statut NOT IN (\'refuse\',\'termine\')) AS nb_encadres
        FROM utilisateurs u';
$params = [];
if (in_array($role, ['etudiant', 'encadrant', 'admin'], true)) {
    $sql .= ' WHERE u.role = ?';
    $params[] = $role;
}
$stmt = $pdo->prepare($sql . ' ORDER BY u.role, u.nom, u.prenom');
$stmt->execute($params);
$users = $stmt->fetchAll();

$pageTitle = 'Utilisateurs';
$activeMenu = 'users';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Utilisateurs</h1>
    <a href="<?= url('admin/user_form.php') ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Nouvel utilisateur</a>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach (['' => 'Tous', 'etudiant' => 'Étudiants', 'encadrant' => 'Encadrants', 'admin' => 'Administrateurs'] as $key => $label): ?>
        <li class="nav-item"><a class="nav-link <?= $role === $key ? 'active' : '' ?>" href="<?= url('admin/users.php' . ($key ? '?role=' . $key : '')) ?>"><?= $label ?></a></li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= count($users) ?> utilisateur(s)</span>
        <input type="search" class="form-control form-control-sm w-auto" placeholder="Rechercher…" data-table-filter="#tableUsers">
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="tableUsers">
            <thead class="table-light">
                <tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Filière / Département</th><th>Statut</th><th class="text-end">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= e($u['prenom'] . ' ' . $u['nom']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td>
                        <?= e(role_label($u['role'])) ?>
                        <?php if ($u['role'] === 'encadrant'): ?><span class="badge text-bg-light border"><?= (int) $u['nb_encadres'] ?> encadré(s)</span><?php endif; ?>
                    </td>
                    <td><?= e($u['filiere'] ?: $u['departement'] ?: '—') ?></td>
                    <td><?= $u['actif'] ? '<span class="badge text-bg-success">Actif</span>' : '<span class="badge text-bg-secondary">Désactivé</span>' ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= url('admin/user_form.php?id=' . $u['id']) ?>" title="Modifier"><i class="bi bi-pencil"></i></a>
                        <?php if ((int) $u['id'] !== $user['id']): ?>
                            <form method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <button name="action" value="toggle" class="btn btn-sm btn-outline-secondary" title="<?= $u['actif'] ? 'Désactiver' : 'Activer' ?>">
                                    <i class="bi bi-<?= $u['actif'] ? 'pause' : 'play' ?>"></i>
                                </button>
                                <button name="action" value="supprimer" class="btn btn-sm btn-outline-danger" title="Supprimer"
                                        data-confirm="Supprimer cet utilisateur ? Ses stages et suivis seront aussi supprimés.">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
