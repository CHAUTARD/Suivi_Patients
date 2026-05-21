<?php
/*
 * admin/fermetures.php
 * Gestion des périodes de fermeture du cabinet (CRUD).
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle    = 'Périodes de fermeture';
$extraScripts = ['fermetures.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="h4 mb-0 fw-bold text-primary">
            <i class="bi bi-calendar-x me-2"></i>Périodes de fermeture du cabinet
        </h2>
        <p class="text-muted small mb-0 mt-1">
            Les jours inclus dans ces périodes apparaissent en hachuré dans le récapitulatif mensuel.
        </p>
    </div>
    <div class="col-auto d-flex gap-2">
        <a href="<?= SITE_ROOT ?>/admin/index.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
        <button class="btn btn-primary btn-sm" id="btnAddFermeture" type="button">
            <i class="bi bi-plus-lg me-1"></i>Ajouter une période
        </button>
    </div>
</div>

<div id="alertZone" class="mb-3" style="display:none;"></div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="tableFermetures">
                <thead class="table-dark">
                    <tr>
                        <th style="width:14%">Du</th>
                        <th style="width:14%">Au</th>
                        <th style="width:12%">Durée</th>
                        <th>Motif</th>
                        <th class="text-center" style="width:110px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="fermeturesBody">
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Chargement…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============================================================
     Modale — Ajout / Modification
     ============================================================ -->
<div class="modal fade" id="modalFermeture" tabindex="-1"
     aria-labelledby="modalFermetureLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFermetureLabel">Ajouter une période de fermeture</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlertZone" class="mb-3" style="display:none;"></div>
                <input type="hidden" id="fermetureId" value="0">

                <div class="mb-3">
                    <label for="fermetureDebut" class="form-label fw-semibold">
                        Date de début <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="fermetureDebut"
                           placeholder="JJ/MM/AAAA" autocomplete="off">
                </div>
                <div class="mb-3">
                    <label for="fermetureFin" class="form-label fw-semibold">
                        Date de fin <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="fermetureFin"
                           placeholder="JJ/MM/AAAA" autocomplete="off">
                    <div class="form-text">
                        Incluse. Pour une seule journée, saisir la même date qu'au début.
                    </div>
                </div>
                <div class="mb-1">
                    <label for="fermetureMotif" class="form-label fw-semibold">Motif</label>
                    <input type="text" class="form-control" id="fermetureMotif"
                           maxlength="255"
                           placeholder="Ex : Congés estivaux, Formation, Fermeture annuelle…">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="btnSaveFermeture">
                    <i class="bi bi-floppy2-fill me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     Modale — Confirmation de suppression
     ============================================================ -->
<div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmation
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteMessage">
                Voulez-vous vraiment supprimer cette période ?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
                    <i class="bi bi-trash3 me-1"></i>Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.SITE_ROOT = '<?= SITE_ROOT ?>';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
