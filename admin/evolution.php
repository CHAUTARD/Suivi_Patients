<?php
/*
 * admin/evolution.php
 * Courbes d'évolution mensuelle — actes, montants devis, taux d'acceptation
 * Accessible aux administrateurs uniquement.
 */
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$pageTitle    = 'Évolution mensuelle';
$cdnScripts   = [SITE_ROOT . '/assets/js/chart.umd.min.js'];
$extraScripts = ['evolution.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md">
        <h2 class="h4 mb-0 fw-bold text-primary">
            <i class="bi bi-graph-up me-2"></i>Évolution mensuelle
        </h2>
        <div class="text-muted small mt-1">Actes saisis, montants des devis et taux d'acceptation par dentiste</div>
    </div>

    <!-- Sélecteur de période -->
    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small fw-semibold">Période&nbsp;:</span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Période">
                <input type="radio" class="btn-check" name="nbMois" id="nb3"  value="3">
                <label class="btn btn-outline-secondary" for="nb3">3 mois</label>

                <input type="radio" class="btn-check" name="nbMois" id="nb6"  value="6">
                <label class="btn btn-outline-secondary" for="nb6">6 mois</label>

                <input type="radio" class="btn-check" name="nbMois" id="nb12" value="12" checked>
                <label class="btn btn-outline-secondary" for="nb12">12 mois</label>

                <input type="radio" class="btn-check" name="nbMois" id="nb24" value="24">
                <label class="btn btn-outline-secondary" for="nb24">24 mois</label>
            </div>
        </div>
    </div>
</div>

<!-- ── Chart 1 : Actes ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="h6 mb-0 fw-bold text-primary">
            <i class="bi bi-bar-chart-fill me-2"></i>Actes saisis par mois
            <span class="text-muted fw-normal small">(barres empilées par dentiste)</span>
        </h5>
    </div>
    <div class="card-body">
        <div id="chartActesWrap" class="chart-wrap" style="position:relative; height:300px;">
            <canvas id="chartActes"></canvas>
        </div>
    </div>
</div>

<!-- ── Chart 2 : Montants devis ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="h6 mb-0 fw-bold text-primary">
            <i class="bi bi-cash-stack me-2"></i>Montants des devis par mois
            <span class="text-muted fw-normal small">(€, par dentiste + total)</span>
        </h5>
    </div>
    <div class="card-body">
        <div id="chartDevisWrap" class="chart-wrap" style="position:relative; height:300px;">
            <canvas id="chartDevis"></canvas>
        </div>
    </div>
</div>

<!-- ── Chart 3 : Taux d'acceptation ── -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="h6 mb-0 fw-bold text-primary">
            <i class="bi bi-percent me-2"></i>Taux d'acceptation des plans par mois
            <span class="text-muted fw-normal small">(seuils à 80 % et 50 %)</span>
        </h5>
    </div>
    <div class="card-body">
        <div id="chartTauxWrap" class="chart-wrap" style="position:relative; height:300px;">
            <canvas id="chartTaux"></canvas>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-2 small text-muted">
            <span>
                <span class="badge" style="background:#198754;">&nbsp;&nbsp;&nbsp;</span>
                Seuil 80 % — objectif idéal
            </span>
            <span>
                <span class="badge" style="background:#dc3545;">&nbsp;&nbsp;&nbsp;</span>
                Seuil 50 % — niveau d'alerte
            </span>
            <span class="ms-auto fst-italic">
                <i class="bi bi-info-circle me-1"></i>
                Les mois sans aucun plan sont affichés sans point (données absentes).
            </span>
        </div>
    </div>
</div>

<script>
window.SITE_ROOT = '<?= SITE_ROOT ?>';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
