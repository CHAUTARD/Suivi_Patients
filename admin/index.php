<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// Statistiques
$nbDentistes = $db->query(
    "SELECT COUNT(*) FROM utilisateur WHERE id_role = 1"
)->fetchColumn();

$nbAdmins = $db->query(
    "SELECT COUNT(*) FROM utilisateur WHERE id_role = 2"
)->fetchColumn();

$nbSaisiesAujourdHui = $db->query(
    "SELECT COUNT(DISTINCT id_utilisateur) FROM nombre WHERE date = CURDATE()"
)->fetchColumn();

$nbSaisiesTotal = $db->query(
    "SELECT COUNT(*) FROM nombre"
)->fetchColumn();

$pageTitle    = 'Administration';
$cdnScripts   = [SITE_ROOT . '/assets/js/chart.umd.min.js'];
$extraScripts = ['admin_dashboard.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col">
        <h2 class="h4 mb-0 fw-bold text-primary">
            <i class="bi bi-gear-fill me-2"></i>Tableau de bord — Administration
        </h2>
    </div>
</div>

<!-- Cartes statistiques -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body">
                <div class="display-6 text-primary fw-bold"><?= (int)$nbDentistes ?></div>
                <div class="text-muted small mt-1">Dentiste(s)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body">
                <div class="display-6 text-warning fw-bold"><?= (int)$nbAdmins ?></div>
                <div class="text-muted small mt-1">Administrateur(s)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body">
                <div class="display-6 text-success fw-bold"><?= (int)$nbSaisiesAujourdHui ?></div>
                <div class="text-muted small mt-1">Saisies aujourd'hui</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm text-center h-100">
            <div class="card-body">
                <div class="display-6 text-info fw-bold"><?= (int)$nbSaisiesTotal ?></div>
                <div class="text-muted small mt-1">Entrées totales</div>
            </div>
        </div>
    </div>
</div>

<!-- Accès rapide -->
<div class="row g-3">
    <div class="col-12 col-md-4">
        <a href="<?= SITE_ROOT ?>/admin/utilisateurs.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-1 text-primary"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="fw-bold">Utilisateurs</div>
                        <div class="text-muted small">Gérer les comptes dentistes et administrateurs</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= SITE_ROOT ?>/admin/piliers.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-1 text-success"><i class="bi bi-layout-three-columns"></i></div>
                    <div>
                        <div class="fw-bold">Piliers</div>
                        <div class="text-muted small">Gérer les piliers de suivi</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= SITE_ROOT ?>/admin/actions.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-1 text-warning"><i class="bi bi-list-check"></i></div>
                    <div>
                        <div class="fw-bold">Actions</div>
                        <div class="text-muted small">Gérer les actions et indicateurs</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-12 col-md-4">
        <a href="<?= SITE_ROOT ?>/admin/fermetures.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-1 text-danger"><i class="bi bi-calendar-x"></i></div>
                    <div>
                        <div class="fw-bold">Fermetures</div>
                        <div class="text-muted small">Gérer les périodes de fermeture du cabinet</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Camembert répartition par pilier -->
<div class="row g-3 mt-2">
    <div class="col-12 col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="h6 mb-0 fw-bold text-primary">
                    <i class="bi bi-pie-chart-fill me-2"></i>Répartition par pilier — <span id="chartMoisLabel">…</span>
                </h5>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center" id="chartBody">
                <div class="spinner-border spinner-border-sm text-muted" role="status"></div>
            </div>
        </div>
    </div>
</div>

<script>
window.SITE_ROOT = '<?= SITE_ROOT ?>';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
