<?php
/* * Fichier : saisie.php
 * Description : Page de saisie des actes et plans de traitement journaliers
 * 
 * Modif :
 * - 2026-05-16 : Ajout des champs calculés et des pourcentages
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

$pageTitle    = 'Saisie des actes';
$extraScripts = ['saisie.js'];
$today        = date('Y-m-d');

require_once __DIR__ . '/includes/header.php';
?>

<div class="row mb-3 align-items-center">
    <div class="col-12 col-md-auto">
        <h2 class="h4 mb-0 fw-bold text-primary">
            <i class="bi bi-pencil-square me-2"></i>Saisie des actes
        </h2>
    </div>
    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <div class="input-group" style="max-width:200px;">
            <span class="input-group-text bg-white"><i class="bi bi-calendar3"></i></span>
            <input type="text" id="dateSaisie" class="form-control fw-semibold"
                   value="<?= htmlspecialchars($today) ?>" placeholder="JJ/MM/AAAA" readonly>
        </div>
    </div>
    <?php if (isAdmin()): ?>
    <div class="col-12 col-md-auto mt-2 mt-md-0">
        <select id="filtreDentiste" class="form-select" style="min-width:200px;" required>
            <option value="0">— Sélectionner un dentiste —</option>
            <?php foreach ($dentistes as $d): ?>
                <option value="<?= (int)$d['id_utilisateur'] ?>">
                    <?= htmlspecialchars($d['login']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <div class="col-12 col-md-auto mt-2 mt-md-0 ms-md-auto d-flex gap-2">
        <button id="btnEnregistrer" class="btn btn-success">
            <i class="bi bi-floppy2-fill me-1"></i> Enregistrer
        </button>
        <button id="btnToday" class="btn btn-outline-secondary btn-sm" title="Revenir à aujourd'hui">
            Aujourd'hui
        </button>
    </div>
</div>

<!-- Message de retour -->
<div id="alertZone" class="mb-3" style="display:none;"></div>

<!-- Tableau de saisie -->
<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="tableSaisie">
                <thead class="table-dark">
                    <tr>
                        <th style="width:50%">Action</th>
                        <th style="width:35%">Valeur</th>
                        <th style="width:15%" class="text-end">Pourcentage</th>
                    </tr>
                </thead>
                <tbody id="saisieBody">
                    <tr>
                        <td colspan="2" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Chargement…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Plans de traitement journaliers -->
<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-bold text-primary">
            <i class="bi bi-clipboard2-pulse me-2"></i>Plans de traitement du jour
        </h3>
        <div class="d-flex gap-2">
            <button id="btnAddPlan" class="btn btn-outline-primary btn-sm" type="button">
                <i class="bi bi-plus-lg me-1"></i>Ajouter une ligne
            </button>
            <button id="btnSavePlans" class="btn btn-primary btn-sm" type="button">
                <i class="bi bi-floppy2-fill me-1"></i>Enregistrer plans
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0" id="tablePlans">
                <thead class="table-dark">
                    <tr>
                        <th style="width:26%">Patient</th>
                        <th style="width:16%">Montant devis</th>
                        <th style="width:12%" class="text-center">Accepté</th>
                        <th style="width:16%">Date acceptation</th>
                        <th style="width:10%">Montant</th>
                        <th style="width:10%" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="plansBody">
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            Chargement…
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Données passées au JS -->
<script>
    window.SITE_ROOT  = '<?= SITE_ROOT ?>';
    window.INIT_DATE  = '<?= $today ?>';
    window.IS_ADMIN   = <?= isAdmin() ? 'true' : 'false' ?>;
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>