<?php
/**
 * Bloc d'affichage commun du détail d'un stage.
 * Variables attendues : $stage (get_stage), $historique, $suivis
 */
$etapes = ['brouillon' => 'Brouillon', 'soumis' => 'Soumis', 'valide_encadrant' => 'Validé encadrant',
           'valide' => 'Validation finale', 'termine' => 'Terminé'];
$ordre = array_keys($etapes);
$position = array_search($stage['statut'] === 'a_corriger' ? 'soumis' : $stage['statut'], $ordre, true);
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?= e($stage['sujet']) ?></h1>
        <div class="text-muted small">
            <?= e($stage['etudiant_prenom'] . ' ' . $stage['etudiant_nom']) ?> · <?= e($stage['filiere']) ?>
            · Année <?= e($stage['annee_universitaire']) ?>
        </div>
    </div>
    <div class="fs-6"><?= statut_badge($stage['statut']) ?></div>
</div>

<?php if ($stage['statut'] === 'refuse'): ?>
    <div class="alert alert-danger"><i class="bi bi-x-octagon me-1"></i>Ce stage a été refusé. Consultez l'historique pour le motif.</div>
<?php else: ?>
    <div class="workflow-steps mb-4">
        <?php foreach ($ordre as $i => $key): ?>
            <div class="step <?= $i < $position || ($key === 'termine' && $stage['statut'] === 'termine') ? 'done' : ($i === $position ? 'current' : '') ?>">
                <?= e($etapes[$key]) ?>
                <?php if ($key === 'soumis' && $stage['statut'] === 'a_corriger'): ?><br><small>(à corriger)</small><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1"></i>Informations du stage</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Entreprise</dt>
                    <dd class="col-sm-8"><?= e($stage['entreprise_nom'] ?? '—') ?><?= $stage['entreprise_ville'] ? ' · ' . e($stage['entreprise_ville']) : '' ?></dd>
                    <dt class="col-sm-4">Encadrant entreprise</dt>
                    <dd class="col-sm-8"><?= e($stage['encadrant_entreprise'] ?: '—') ?>
                        <?php if ($stage['encadrant_entreprise_email']): ?><span class="text-muted">(<?= e($stage['encadrant_entreprise_email']) ?>)</span><?php endif; ?></dd>
                    <dt class="col-sm-4">Encadrant académique</dt>
                    <dd class="col-sm-8"><?= $stage['encadrant_id'] ? e($stage['encadrant_prenom'] . ' ' . $stage['encadrant_nom']) : '<span class="text-warning">Non affecté</span>' ?></dd>
                    <dt class="col-sm-4">Période</dt>
                    <dd class="col-sm-8">du <?= format_date($stage['date_debut']) ?> au <?= format_date($stage['date_fin']) ?></dd>
                    <dt class="col-sm-4">Technologies</dt>
                    <dd class="col-sm-8"><?= e($stage['technologies'] ?: '—') ?></dd>
                    <dt class="col-sm-4">Description</dt>
                    <dd class="col-sm-8"><?= nl2br(e($stage['description'] ?: '—')) ?></dd>
                    <dt class="col-sm-4">Avancement</dt>
                    <dd class="col-sm-8">
                        <div class="progress" role="progressbar" style="height:1.25rem">
                            <div class="progress-bar" style="width: <?= (int) $stage['avancement'] ?>%"><?= (int) $stage['avancement'] ?>%</div>
                        </div>
                    </dd>
                    <?php if ($stage['note_finale'] !== null): ?>
                        <dt class="col-sm-4">Note finale</dt>
                        <dd class="col-sm-8 fw-bold"><?= e($stage['note_finale']) ?> / 20</dd>
                    <?php endif; ?>
                    <dt class="col-sm-4">Documents</dt>
                    <dd class="col-sm-8">
                        <?php if ($stage['fichier_convention']): ?>
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= url('download.php?type=convention&id=' . $stage['id']) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Convention</a>
                        <?php endif; ?>
                        <?php if ($stage['fichier_rapport']): ?>
                            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= url('download.php?type=rapport&id=' . $stage['id']) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Rapport</a>
                        <?php endif; ?>
                        <?php if (!$stage['fichier_convention'] && !$stage['fichier_rapport']): ?><span class="text-muted">Aucun document</span><?php endif; ?>
                    </dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-journal-text me-1"></i>Journal de suivi</div>
            <div class="card-body">
                <?php if (isset($suiviForm)) { echo $suiviForm; } ?>
                <?php if (!$suivis): ?>
                    <p class="text-muted mb-0">Aucune entrée de suivi pour le moment.</p>
                <?php endif; ?>
                <?php foreach ($suivis as $s): ?>
                    <div class="border rounded p-3 mb-2 <?= $s['role'] === 'etudiant' ? 'bg-light' : 'border-primary-subtle' ?>">
                        <div class="d-flex justify-content-between small mb-1">
                            <strong><?= e($s['prenom'] . ' ' . $s['nom']) ?> <span class="text-muted fw-normal">(<?= e(role_label($s['role'])) ?>)</span></strong>
                            <span class="text-muted"><?= format_date($s['created_at'], true) ?></span>
                        </div>
                        <div><?= nl2br(e($s['contenu'])) ?></div>
                        <?php if ($s['avancement'] !== null): ?>
                            <span class="badge text-bg-light border mt-2">Avancement déclaré : <?= (int) $s['avancement'] ?>%</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <?php if (isset($actionsPanel)) { echo $actionsPanel; } ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1"></i>Historique de validation</div>
            <div class="card-body">
                <?php if (!$historique): ?><p class="text-muted mb-0">Aucun historique.</p><?php endif; ?>
                <div class="timeline">
                    <?php foreach ($historique as $h): ?>
                        <div class="timeline-item">
                            <div><?= statut_badge($h['nouveau_statut']) ?></div>
                            <div class="small mt-1"><?= e($h['prenom'] . ' ' . $h['nom']) ?> · <span class="text-muted"><?= format_date($h['created_at'], true) ?></span></div>
                            <?php if ($h['commentaire']): ?><div class="small fst-italic mt-1">« <?= e($h['commentaire']) ?> »</div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
