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
// Pré-sélection du mois via ?mois= (deep-link depuis la relance admin)
$currentMois  = validateMois($_GET['mois'] ?? date('Y-m')) ?? date('Y-m');

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-2 align-items-center">
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
        <button type="button" id="btnToggleFilters"
                class="btn btn-outline-secondary btn-sm"
                data-bs-toggle="collapse"
                data-bs-target="#filtresAvancesZone"
                aria-expanded="false"
                aria-controls="filtresAvancesZone">
            <i class="bi bi-funnel me-1"></i>Filtres avancés
            <span id="filtresActiveBadge" class="badge bg-danger ms-1" style="display:none;">0</span>
        </button>
    </div>

    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <button id="btnExportPlansCsv" class="btn btn-outline-success btn-sm">
            <i class="bi bi-filetype-csv me-1"></i>Exporter CSV
        </button>
    </div>
</div>

<!-- Panneau filtres avancés -->
<div class="collapse mb-3" id="filtresAvancesZone">
    <div class="card border-0 shadow-sm bg-light">
        <div class="card-body py-3 px-3">
            <div class="row g-3 align-items-end">

                <!-- Recherche patient -->
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="filtrePatient" class="form-label small fw-semibold mb-1">
                        <i class="bi bi-search me-1"></i>Recherche patient
                    </label>
                    <input type="text" id="filtrePatient" class="form-control form-control-sm"
                           placeholder="Nom du patient…" autocomplete="off">
                </div>

                <!-- Statut -->
                <div class="col-12 col-md-auto">
                    <div class="form-label small fw-semibold mb-1">
                        <i class="bi bi-check-circle me-1"></i>Statut
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Filtre statut">
                        <input type="radio" class="btn-check" name="filtreStatut" id="fsAll"    value="" checked>
                        <label class="btn btn-outline-secondary" for="fsAll">Tous</label>

                        <input type="radio" class="btn-check" name="filtreStatut" id="fsOui"    value="Oui">
                        <label class="btn btn-outline-success"  for="fsOui">
                            <i class="bi bi-check-lg me-1"></i>Acceptés
                        </label>

                        <input type="radio" class="btn-check" name="filtreStatut" id="fsPartie" value="en Partie">
                        <label class="btn btn-outline-warning"  for="fsPartie">
                            <i class="bi bi-dash-lg me-1"></i>En partie
                        </label>

                        <input type="radio" class="btn-check" name="filtreStatut" id="fsNon"    value="Non">
                        <label class="btn btn-outline-danger"   for="fsNon">
                            <i class="bi bi-x-lg me-1"></i>Non
                        </label>
                    </div>
                </div>

                <!-- Montant devis min -->
                <div class="col-6 col-sm-auto">
                    <label for="filtreDevisMin" class="form-label small fw-semibold mb-1">
                        <i class="bi bi-cash me-1"></i>Devis min&nbsp;(€)
                    </label>
                    <input type="number" id="filtreDevisMin" class="form-control form-control-sm"
                           placeholder="0" min="0" style="width:110px;">
                </div>

                <!-- Montant devis max -->
                <div class="col-6 col-sm-auto">
                    <label for="filtreDevisMax" class="form-label small fw-semibold mb-1">
                        Devis max&nbsp;(€)
                    </label>
                    <input type="number" id="filtreDevisMax" class="form-control form-control-sm"
                           placeholder="∞" min="0" style="width:110px;">
                </div>

                <!-- Réinitialiser -->
                <div class="col-auto ms-auto">
                    <button type="button" id="btnResetFilters" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Réinitialiser
                    </button>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Résultat filtre actif -->
<div id="filtreResultat" class="mb-2 small" style="display:none;"></div>

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