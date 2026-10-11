<?php
require_once __DIR__ . '/../includes/functions.php';
$user = require_role('admin');
$pdo = db();

$filtreStatut = $_GET['statut'] ?? '';
$filtreEncadrant = (int) ($_GET['encadrant'] ?? 0);
$filtreAnnee = $_GET['annee'] ?? '';
$sansEncadrant = !empty($_GET['sans_encadrant']);

$where = [];
$params = [];
if (isset(statuts()[$filtreStatut])) {
    $where[] = 's.statut = ?';
    $params[] = $filtreStatut;
}
if ($filtreEncadrant) {
    $where[] = 's.encadrant_id = ?';
    $params[] = $filtreEncadrant;
}
if ($filtreAnnee !== '') {
    $where[] = 's.annee_universitaire = ?';
    $params[] = $filtreAnnee;
}
if ($sansEncadrant) {
    $where[] = "s.encadrant_id IS NULL AND s.statut NOT IN ('refuse','termine')";
}

$sql = 'SELECT s.id, s.sujet, s.statut, s.avancement, s.annee_universitaire, s.note_finale,
               et.nom, et.prenom, et.filiere, en.nom AS enc_nom, en.prenom AS enc_prenom, ent.nom AS entreprise_nom
        FROM stages s
        JOIN utilisateurs et ON et.id = s.etudiant_id
        LEFT JOIN utilisateurs en ON en.id = s.encadrant_id
        LEFT JOIN entreprises ent ON ent.id = s.entreprise_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
     . ' ORDER BY s.updated_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stages = $stmt->fetchAll();

$annees = $pdo->query('SELECT DISTINCT annee_universitaire FROM stages ORDER BY annee_universitaire DESC')->fetchAll(PDO::FETCH_COLUMN);
$encadrants = get_encadrants();

// Export CSV de la liste filtrée
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="stages_pfe_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM pour Excel
    fputcsv($out, ['Étudiant', 'Filière', 'Sujet', 'Entreprise', 'Encadrant', 'Année', 'Statut', 'Avancement', 'Note'], ';');
    foreach ($stages as $s) {
        fputcsv($out, [
            $s['prenom'] . ' ' . $s['nom'], $s['filiere'], $s['sujet'], $s['entreprise_nom'],
            $s['enc_nom'] ? $s['enc_prenom'] . ' ' . $s['enc_nom'] : '', $s['annee_universitaire'],
            statuts()[$s['statut']]['label'], $s['avancement'] . '%', $s['note_finale'],
        ], ';');
    }
    exit;
}

$pageTitle = 'Stages';
$activeMenu = 'stages';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Tous les stages</h1>
    <a class="btn btn-outline-success" href="<?= url('admin/stages.php?' . http_build_query(array_merge($_GET, ['export' => 1]))) ?>">
        <i class="bi bi-filetype-csv me-1"></i>Exporter CSV
    </a>
</div>

<form class="card card-body shadow-sm border-0 mb-3" method="get">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small">Statut</label>
            <select name="statut" class="form-select">
                <option value="">Tous</option>
                <?php foreach (statuts() as $key => $s): ?>
                    <option value="<?= $key ?>" <?= $filtreStatut === $key ? 'selected' : '' ?>><?= e($s['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Encadrant</label>
            <select name="encadrant" class="form-select">
                <option value="">Tous</option>
                <?php foreach ($encadrants as $enc): ?>
                    <option value="<?= $enc['id'] ?>" <?= $filtreEncadrant === (int) $enc['id'] ? 'selected' : '' ?>><?= e($enc['prenom'] . ' ' . $enc['nom']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label small">Année</label>
            <select name="annee" class="form-select">
                <option value="">Toutes</option>
                <?php foreach ($annees as $a): ?>
                    <option <?= $filtreAnnee === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="sans_encadrant" value="1" id="se" <?= $sansEncadrant ? 'checked' : '' ?>>
                <label class="form-check-label small" for="se">Sans encadrant</label>
            </div>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-primary flex-fill"><i class="bi bi-funnel"></i> Filtrer</button>
            <a href="<?= url('admin/stages.php') ?>" class="btn btn-light" title="Réinitialiser"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><?= count($stages) ?> stage(s)</span>
        <input type="search" class="form-control form-control-sm w-auto" placeholder="Rechercher…" data-table-filter="#tableStages">
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="tableStages">
            <thead class="table-light">
                <tr><th>Étudiant</th><th>Sujet</th><th>Entreprise</th><th>Encadrant</th><th>Année</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$stages): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun stage ne correspond aux filtres.</td></tr>
            <?php endif; ?>
            <?php foreach ($stages as $s): ?>
                <tr>
                    <td><?= e($s['prenom'] . ' ' . $s['nom']) ?><div class="small text-muted"><?= e($s['filiere']) ?></div></td>
                    <td><?= e($s['sujet']) ?></td>
                    <td><?= e($s['entreprise_nom'] ?? '—') ?></td>
                    <td><?= $s['enc_nom'] ? e($s['enc_prenom'] . ' ' . $s['enc_nom']) : '<span class="badge text-bg-danger">À affecter</span>' ?></td>
                    <td><?= e($s['annee_universitaire']) ?></td>
                    <td><?= statut_badge($s['statut']) ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= url('admin/stage.php?id=' . $s['id']) ?>">Gérer</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
