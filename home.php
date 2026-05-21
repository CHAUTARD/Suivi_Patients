<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user      = getCurrentUser();
$pageTitle = 'Tableau de bord';

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="h4 mb-0 fw-bold text-primary">
            <i class="bi bi-house-door me-2"></i>Tableau de bord
        </h2>
        <p class="text-muted small mb-0" id="dashSubtitle">Chargement…</p>
    </div>
</div>

<div id="alertZone" class="mb-3" style="display:none;"></div>

<!-- KPI cards -->
<div class="row g-3 mb-4" id="kpiZone">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-calendar-check text-primary fs-5"></i>
                    <span class="text-muted small">Jours saisis</span>
                </div>
                <div class="h3 mb-0 fw-bold" id="kpiJours">—</div>
                <div class="text-muted small" id="kpiDerniere"></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-clipboard2-data text-info fs-5"></i>
                    <span class="text-muted small">Plans ce mois</span>
                </div>
                <div class="h3 mb-0 fw-bold" id="kpiPlans">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-check-circle text-success fs-5"></i>
                    <span class="text-muted small">Taux acceptation</span>
                </div>
                <div class="h3 mb-0 fw-bold" id="kpiTaux">—</div>
                <div class="text-muted small" id="kpiAcceptes"></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <i class="bi bi-cash-coin text-warning fs-5"></i>
                    <span class="text-muted small">Montant devis</span>
                </div>
                <div class="h3 mb-0 fw-bold" id="kpiDevis">—</div>
                <div class="text-muted small" id="kpiMontant"></div>
            </div>
        </div>
    </div>
</div>

<!-- Accès rapides -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <h5 class="fw-bold text-secondary mb-2">
            <i class="bi bi-lightning-charge me-1"></i>Accès rapide
        </h5>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= SITE_ROOT ?>/saisie.php" class="card border-0 shadow-sm h-100 text-decoration-none card-hover">
            <div class="card-body text-center py-3">
                <i class="bi bi-pencil-square text-primary fs-2 d-block mb-1"></i>
                <div class="fw-semibold">Saisie du jour</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= SITE_ROOT ?>/recap.php" class="card border-0 shadow-sm h-100 text-decoration-none card-hover">
            <div class="card-body text-center py-3">
                <i class="bi bi-table text-success fs-2 d-block mb-1"></i>
                <div class="fw-semibold">Récapitulatif</div>
            </div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= SITE_ROOT ?>/recap_plans.php" class="card border-0 shadow-sm h-100 text-decoration-none card-hover">
            <div class="card-body text-center py-3">
                <i class="bi bi-clipboard2-data text-info fs-2 d-block mb-1"></i>
                <div class="fw-semibold">Récap plans</div>
            </div>
        </a>
    </div>
    <?php if (isAdmin()): ?>
    <div class="col-6 col-md-3">
        <a href="<?= SITE_ROOT ?>/admin/index.php" class="card border-0 shadow-sm h-100 text-decoration-none card-hover">
            <div class="card-body text-center py-3">
                <i class="bi bi-gear-fill text-secondary fs-2 d-block mb-1"></i>
                <div class="fw-semibold">Administration</div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Comparatif dentistes (admin uniquement) -->
<?php if (isAdmin()): ?>
<div class="card shadow-sm border-0 mt-2" id="adminZone" style="display:none;">
    <div class="card-header bg-light">
        <h5 class="h6 mb-0 fw-bold text-primary">
            <i class="bi bi-people me-2"></i>Activité par dentiste — <span id="adminMoisLabel"></span>
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0" id="adminTable">
                <thead class="table-dark">
                    <tr>
                        <th>Dentiste</th>
                        <th class="text-center">Jours saisis</th>
                        <th class="text-center">Dernière saisie</th>
                        <th class="text-end">Plans</th>
                        <th class="text-end">Acceptés</th>
                        <th class="text-end">Taux</th>
                        <th class="text-end">Montant devis</th>
                    </tr>
                </thead>
                <tbody id="adminTableBody"></tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
window.SITE_ROOT = '<?= SITE_ROOT ?>';
window.IS_ADMIN  = <?= isAdmin() ? 'true' : 'false' ?>;

$(function () {
    $.ajax({
        url: window.SITE_ROOT + '/api/get_dashboard.php',
        method: 'GET',
        dataType: 'json'
    }).done(function (d) {
        if (!d.success) { showAlert(d.error || 'Erreur de chargement.', 'danger'); return; }

        $('#dashSubtitle').text('Statistiques de ' + (d.mois_label || d.mois));

        // KPIs
        if (d.is_admin) {
            $('#kpiJours').html('<span class="text-muted fs-5">—</span>');
            $('#kpiDerniere').text(d.derniere_saisie ? 'Dernière : ' + frDate(d.derniere_saisie) : 'Aucune saisie');
        } else {
            $('#kpiJours').text(d.jours_saisis || 0);
            $('#kpiDerniere').text(d.derniere_saisie ? 'Dernière : ' + frDate(d.derniere_saisie) : 'Aucune saisie ce mois');
        }

        var p = d.plans || {};
        $('#kpiPlans').text(fmt(p.total || 0));
        $('#kpiTaux').text((p.taux || 0) + ' %');
        $('#kpiAcceptes').text((p.acceptes || 0) + ' / ' + (p.total || 0) + ' plans acceptés');
        $('#kpiDevis').text(fmt(p.total_devis || 0) + ' €');
        $('#kpiMontant').text('Accepté : ' + fmt(p.total_montant || 0) + ' €');

        // Couleur taux
        var taux = p.taux || 0;
        var tauxEl = document.getElementById('kpiTaux');
        tauxEl.className = 'h3 mb-0 fw-bold ' + (taux >= 80 ? 'text-success' : taux >= 50 ? 'text-warning' : 'text-danger');

        // Tableau admin
        if (window.IS_ADMIN && d.dentistes && d.dentistes.length > 0) {
            $('#adminMoisLabel').text(d.mois_label || d.mois);
            var html = '';
            d.dentistes.forEach(function (r) {
                var tauxColor = r.taux >= 80 ? 'text-success' : r.taux >= 50 ? 'text-warning' : 'text-danger';
                html += '<tr>';
                html += '<td class="fw-semibold">' + escH(r.login) + '</td>';
                html += '<td class="text-center">' + (r.jours_saisis || 0) + '</td>';
                html += '<td class="text-center text-muted small">' + (r.derniere_saisie ? frDate(r.derniere_saisie) : '—') + '</td>';
                html += '<td class="text-end">' + fmt(r.total_plans) + '</td>';
                html += '<td class="text-end">' + fmt(r.total_acceptes) + '</td>';
                html += '<td class="text-end fw-semibold ' + tauxColor + '">' + r.taux + ' %</td>';
                html += '<td class="text-end">' + fmt(r.total_devis) + ' €</td>';
                html += '</tr>';
            });
            $('#adminTableBody').html(html);
            $('#adminZone').show();
        }
    }).fail(function () {
        showAlert('Erreur réseau lors du chargement du tableau de bord.', 'danger');
    });

    function frDate(iso) {
        if (!iso || iso.length < 10) return '';
        return iso.substring(8, 10) + '/' + iso.substring(5, 7) + '/' + iso.substring(0, 4);
    }
    function fmt(v) { return (parseInt(v, 10) || 0).toLocaleString('fr-FR'); }
    function escH(s) { return $('<div>').text(s || '').html(); }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
