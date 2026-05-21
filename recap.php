<?php
/*
 * recap.php — Page récapitulatif mensuel
 * 
 * SELARL La Vespalienne — Suivi des Actes
 * 
 * Modif :
 * - 2026-05-15: Mise en évidance des week-end et des jours fériés
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$db = getDB();

// Liste des dentistes (pour le filtre admin)
$dentistes = [];
if (isAdmin()) {
    $stmt = $db->query(
        "SELECT id_utilisateur, login FROM utilisateur WHERE id_role = 1 ORDER BY login"
    );
    $dentistes = $stmt->fetchAll();
}

$pageTitle    = 'Récapitulatif mensuel';
$extraScripts = ['recap.js'];
$currentMois  = date('Y-m');

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-auto">
        <h2 class="h4 mb-0 fw-bold text-primary">
            <i class="bi bi-table me-2"></i>Récapitulatif mensuel
        </h2>
    </div>

    <!-- Sélecteur mois -->
    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <div class="input-group" style="max-width:180px;">
            <span class="input-group-text bg-white"><i class="bi bi-calendar-month"></i></span>
            <input type="month" id="moisRecap" class="form-control fw-semibold"
                   value="<?= htmlspecialchars($currentMois) ?>">
        </div>
    </div>

    <?php if (isAdmin()): ?>
    <!-- Filtre dentiste (admin seulement) -->
    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <select id="filtreDentiste" class="form-select">
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
        <button id="btnExportCsv" class="btn btn-outline-success btn-sm">
            <i class="bi bi-filetype-csv me-1"></i> Exporter CSV
        </button>
    </div>
</div>

<!-- Message de retour -->
<div id="alertZone" class="mb-3" style="display:none;"></div>

<!-- Zone tableau récapitulatif -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div id="recapTableZone" class="table-responsive">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                Chargement…
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
