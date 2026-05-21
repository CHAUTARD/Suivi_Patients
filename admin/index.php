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

$listeDentistes = $db->query(
    "SELECT id_utilisateur, login FROM utilisateur WHERE id_role = 1 ORDER BY login"
)->fetchAll();
$moisCourant = date('Y-m');

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

<!-- Alerte retard de saisie (injecté par admin_dashboard.js) -->
<div id="alertRetard" class="mb-1" style="display:none;"></div>

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
    <div class="col-12 col-md-4">
        <a href="<?= SITE_ROOT ?>/admin/evolution.php" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 card-hover">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-1 text-info"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <div class="fw-bold">Évolution mensuelle</div>
                        <div class="text-muted small">Courbes actes, devis et taux d'acceptation</div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Camembert + Export & Documents -->
<div class="row g-3 mt-2">

    <!-- Camembert répartition par pilier -->
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

    <!-- Export & Documents -->
    <div class="col-12 col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="h6 mb-0 fw-bold text-primary">
                    <i class="bi bi-box-arrow-up me-2"></i>Export &amp; Documents
                </h5>
            </div>
            <div class="card-body d-flex flex-column justify-content-around gap-2 py-3">

                <!-- 1. PDF Récapitulatif mensuel -->
                <div class="d-flex align-items-start gap-3 p-3 rounded border">
                    <div class="fs-1 text-danger flex-shrink-0">
                        <i class="bi bi-file-earmark-pdf-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-1">Export PDF — Récapitulatif mensuel</div>
                        <div class="text-muted small mb-2">
                            Tableau des actes par jour, par action et par pilier.
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="month" id="pdfRecapMois"
                                class="form-control form-control-sm"
                                style="width:150px;"
                                value="<?= htmlspecialchars($moisCourant) ?>">
                            <select id="pdfRecapDentiste" class="form-select form-select-sm" style="max-width:180px;">
                                <option value="0">Tous les dentistes</option>
                                <?php foreach ($listeDentistes as $d): ?>
                                <option value="<?= (int)$d['id_utilisateur'] ?>">
                                    <?= htmlspecialchars($d['login']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <a id="btnPdfRecap" href="#" target="_blank" class="btn btn-sm btn-danger">
                                <i class="bi bi-file-earmark-pdf me-1"></i>Générer PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 2. Rapport mensuel PDF actes + plans -->
                <div class="d-flex align-items-start gap-3 p-3 rounded border">
                    <div class="fs-1 text-primary flex-shrink-0">
                        <i class="bi bi-file-earmark-richtext-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-1">Rapport mensuel PDF — Actes + Plans</div>
                        <div class="text-muted small mb-2">
                            Synthèse complète : actes par pilier, plans de traitement et taux d'acceptation par dentiste.
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="month" id="pdfRapportMois"
                                class="form-control form-control-sm"
                                style="width:150px;"
                                value="<?= htmlspecialchars($moisCourant) ?>">
                            <a id="btnPdfRapport" href="#" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-file-earmark-richtext me-1"></i>Générer PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 3. Export CSV enrichi -->
                <div class="d-flex align-items-start gap-3 p-3 rounded border">
                    <div class="fs-1 text-success flex-shrink-0">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-1">Export CSV enrichi</div>
                        <div class="text-muted small mb-2">
                            Données détaillées avec totaux et pourcentages, importable dans Excel.
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input type="month" id="csvMois"
                                class="form-control form-control-sm"
                                style="width:150px;"
                                value="<?= htmlspecialchars($moisCourant) ?>">
                            <select id="csvDentiste" class="form-select form-select-sm" style="max-width:180px;">
                                <option value="0">Tous les dentistes</option>
                                <?php foreach ($listeDentistes as $d): ?>
                                <option value="<?= (int)$d['id_utilisateur'] ?>">
                                    <?= htmlspecialchars($d['login']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <a id="btnCsv" href="#" class="btn btn-sm btn-success">
                                <i class="bi bi-download me-1"></i>Télécharger CSV
                            </a>
                        </div>
                    </div>
                </div>

            </div><!-- /.card-body -->
        </div><!-- /.card -->
    </div><!-- /.col export -->

</div><!-- /.row -->

<!-- Relance automatique des plans non acceptés -->
<div class="row g-3 mt-2">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <div class="d-flex align-items-center flex-wrap gap-2 gap-md-3">

                    <!-- Titre + badge -->
                    <div class="d-flex align-items-center gap-2 me-auto">
                        <h5 class="h6 mb-0 fw-bold text-primary">
                            <i class="bi bi-bell-fill me-1"></i>Relance automatique — plans non acceptés
                        </h5>
                        <span id="relanceBadge" class="badge bg-warning text-dark"
                              style="display:none;" title="Plans en attente de relance">0</span>
                    </div>

                    <!-- Configuration du délai -->
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small fw-semibold">Délai&nbsp;:</span>
                        <input type="number" id="delaiRelance"
                               class="form-control form-control-sm text-center fw-semibold"
                               min="1" max="365" style="width:72px;" value="30"
                               title="Nombre de jours après lequel un plan est considéré à relancer">
                        <span class="text-muted small">jours</span>
                        <button type="button" id="btnSaveDelai"
                                class="btn btn-sm btn-outline-primary"
                                title="Enregistrer et rafraîchir">
                            <i class="bi bi-check-lg me-1"></i>Appliquer
                        </button>
                    </div>

                </div>
            </div>
            <div class="card-body p-0" id="relanceBody">
                <div class="text-center py-3 text-muted">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.SITE_ROOT = '<?= SITE_ROOT ?>';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
