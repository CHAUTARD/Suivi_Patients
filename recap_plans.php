<?php
/*
 * Récap mensuel des plans de traitement
 * Accessible à tous les utilisateurs, avec possibilité de filtrer par dentiste pour les admins
 * Données chargées via AJAX depuis api/get_recap_plans.php
 * 
 * Modifié le : 14/05/2026
 */

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();

$dentistes = [];
if (isAdmin()) {
    $stmt = $db->query(
        "SELECT id_utilisateur, login FROM utilisateur WHERE id_role = 1 ORDER BY login"
    );
    $dentistes = $stmt->fetchAll();
}

$pageTitle    = 'Récap mensuel des plans';
$extraScripts = ['recap_plans.js'];
$currentMois  = date('Y-m');

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-auto">
        <h2 class="h4 mb-0 fw-bold text-primary">
            <i class="bi bi-clipboard2-data me-2"></i>Récap mensuel des plans de traitement
        </h2>
    </div>

    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <div class="input-group" style="max-width:180px;">
            <span class="input-group-text bg-white"><i class="bi bi-calendar-month"></i></span>
            <input type="month" id="moisRecapPlans" class="form-control fw-semibold"
                   value="<?= htmlspecialchars($currentMois) ?>">
        </div>
    </div>

    <?php if (isAdmin()): ?>
    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <select id="filtreDentistePlans" class="form-select">
            <option value="0">— Tous les dentistes —</option>
            <?php foreach ($dentistes as $d): ?>
                <option value="<?= (int)$d['id_utilisateur'] ?>">
                    <?= htmlspecialchars($d['login']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <button id="btnExportPlansCsv" class="btn btn-outline-success btn-sm">
            <i class="bi bi-filetype-csv me-1"></i> Exporter CSV
        </button>
    </div>
</div>

<div id="alertZone" class="mb-3" style="display:none;"></div>

<div class="row g-3 mb-3" id="statsPlansZone">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Plans</div>
                <div class="h4 mb-0" id="statTotalPlans">0</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Acceptés</div>
                <div class="h4 mb-0" id="statAcceptes">0</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Montant devis</div>
                <div class="h4 mb-0" id="statDevis">0</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="text-muted small">Taux acceptation</div>
                <div class="h4 mb-0" id="statTaux">0%</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div id="recapPlansTableZone" class="table-responsive">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Chargement…
            </div>
        </div>
    </div>
</div>

<?php if (isAdmin()): ?>
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-light">
        <h3 class="h6 mb-0 fw-bold text-primary">
            <i class="bi bi-bar-chart-line me-2"></i>Comparatif mensuel — ensemble des dentistes
        </h3>
    </div>
    <div class="card-body p-0">
        <div id="comparePlansTableZone" class="table-responsive">
            <div class="text-center py-4 text-muted">Chargement…</div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modale modification plan de traitement -->
<div class="modal fade" id="modalEditPlan" tabindex="-1" aria-labelledby="modalEditPlanLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title h6 fw-bold" id="modalEditPlanLabel">
                    <i class="bi bi-pencil-square me-2 text-primary"></i>Modifier le plan
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-3" id="editPlanInfo"></p>
                <input type="hidden" id="editPlanId">
                <div class="mb-3">
                    <label for="editAccepter" class="form-label fw-semibold small">Accepté</label>
                    <select id="editAccepter" class="form-select form-select-sm">
                        <option value="Non">Non</option>
                        <option value="Oui">Oui</option>
                        <option value="en Partie">En Partie</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="editDateAcceptation" class="form-label fw-semibold small">Date acceptation</label>
                    <input type="text" id="editDateAcceptation" class="form-control form-control-sm"
                           placeholder="jj/mm/aaaa" autocomplete="off">
                </div>
                <div class="mb-2">
                    <label for="editMontant" class="form-label fw-semibold small">Montant (€)</label>
                    <input type="number" id="editMontant" class="form-control form-control-sm" min="0" step="1">
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Annuler</button>
                <button type="button" id="btnSaveEditPlan" class="btn btn-primary btn-sm">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.SITE_ROOT  = '<?= SITE_ROOT ?>';
    window.IS_ADMIN   = <?= isAdmin() ? 'true' : 'false' ?>;
    window.INIT_MOIS  = '<?= $currentMois ?>';
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>