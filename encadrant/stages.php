<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('encadrant');

$filtre = $_GET['statut'] ?? '';
$sql = 'SELECT s.id, s.sujet, s.statut, s.avancement, s.date_debut, s.date_fin, u.nom, u.prenom, u.filiere, ent.nom AS entreprise_nom
        FROM stages s
        JOIN utilisateurs u ON u.id = s.etudiant_id
        LEFT JOIN entreprises ent ON ent.id = s.entreprise_id
        WHERE s.encadrant_id = ?';
$params = [$user['id']];
if (isset(statuts()[$filtre])) {
    $sql .= ' AND s.statut = ?';
    $params[] = $filtre;
}
$stmt = db()->prepare($sql . ' ORDER BY s.updated_at DESC');
$stmt->execute($params);
$stages = $stmt->fetchAll();

$pageTitle = 'Mes étudiants';
$activeMenu = 'stages';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Mes étudiants encadrés</h1>
    <div class="d-flex gap-2">
        <input type="search" class="form-control" placeholder="Rechercher…" data-table-filter="#tableStages">
        <form method="get">
            <select name="statut" class="form-select" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                <?php foreach (statuts() as $key => $s): ?>
                    <option value="<?= $key ?>" <?= $filtre === $key ? 'selected' : '' ?>><?= e($s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="tableStages">
            <thead class="table-light">
                <tr><th>Étudiant</th><th>Sujet</th><th>Entreprise</th><th>Période</th><th>Avancement</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$stages): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun stage.</td></tr>
            <?php endif; ?>
            <?php foreach ($stages as $s): ?>
                <tr>
                    <td><?= e($s['prenom'] . ' ' . $s['nom']) ?><div class="small text-muted"><?= e($s['filiere']) ?></div></td>
                    <td><?= e($s['sujet']) ?></td>
                    <td><?= e($s['entreprise_nom'] ?? '—') ?></td>
                    <td class="small"><?= format_date($s['date_debut']) ?><br><?= format_date($s['date_fin']) ?></td>
                    <td style="min-width:110px">
                        <div class="progress" style="height:.75rem"><div class="progress-bar" style="width: <?= (int) $s['avancement'] ?>%"></div></div>
                        <small class="text-muted"><?= (int) $s['avancement'] ?>%</small>
                    </td>
                    <td><?= statut_badge($s['statut']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('encadrant/stage.php?id=' . $s['id']) ?>">Ouvrir</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
