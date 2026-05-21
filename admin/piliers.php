<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// ----------------------------------------------------------------
// Traitement AJAX (POST)
// ----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');

    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verifyCsrfToken($token)) {
        echo json_encode(['success' => false, 'error' => 'Token CSRF invalide.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $libelle = trim($_POST['libelle'] ?? '');
        $id      = (int)($_POST['id'] ?? 0);

        if ($libelle === '') {
            echo json_encode(['success' => false, 'error' => 'Le libellé est obligatoire.']);
            exit;
        }
        if (strlen($libelle) > 150) {
            echo json_encode(['success' => false, 'error' => 'Le libellé ne peut pas dépasser 150 caractères.']);
            exit;
        }

        try {
            if ($action === 'create') {
                $stmt = $db->prepare('INSERT INTO pilier (Pilier) VALUES (?)');
                $stmt->execute([$libelle]);
                echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
            } else {
                $stmt = $db->prepare('UPDATE pilier SET Pilier = ? WHERE id_pilier = ?');
                $stmt->execute([$libelle, $id]);
                echo json_encode(['success' => true]);
            }
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Vérifier si le pilier a des actions liées
        $chk = $db->prepare('SELECT COUNT(*) FROM action WHERE id_pilier = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'error'   => 'Impossible : ce pilier contient des actions. Supprimez d\'abord les actions associées.',
            ]);
            exit;
        }
        $stmt = $db->prepare('DELETE FROM pilier WHERE id_pilier = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Action inconnue.']);
    exit;
}

// ----------------------------------------------------------------
// Affichage
// ----------------------------------------------------------------
$piliers = $db->query(
    'SELECT p.id_pilier, p.Pilier,
            (SELECT COUNT(*) FROM action a WHERE a.id_pilier = p.id_pilier) AS nb_actions
     FROM pilier p
     ORDER BY p.id_pilier'
)->fetchAll();

$pageTitle    = 'Gestion des piliers';
$extraScripts = ['admin.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= SITE_ROOT ?>/admin/index.php">Administration</a>
        </li>
        <li class="breadcrumb-item active">Piliers</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0 fw-bold text-primary">
        <i class="bi bi-layout-three-columns me-2"></i>Gestion des piliers
    </h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalPilier"
            onclick="openCreateModal()">
        <i class="bi bi-plus-lg me-1"></i> Ajouter un pilier
    </button>
</div>

<div id="alertZone" class="mb-3" style="display:none;"></div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover table-bordered mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width:50px;">#</th>
                    <th>Libellé</th>
                    <th style="width:110px;" class="text-center">Actions</th>
                    <th style="width:110px;" class="text-center">Nb actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($piliers as $p): ?>
                <tr id="row-<?= (int)$p['id_pilier'] ?>">
                    <td class="text-muted small"><?= (int)$p['id_pilier'] ?></td>
                    <td><?= htmlspecialchars($p['Pilier']) ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="openEditModal(<?= (int)$p['id_pilier'] ?>,
                                         '<?= addslashes(htmlspecialchars($p['Pilier'])) ?>')"
                                title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="confirmDelete(<?= (int)$p['id_pilier'] ?>,
                                         '<?= addslashes(htmlspecialchars($p['Pilier'])) ?>')"
                                title="Supprimer">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?= (int)$p['nb_actions'] ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Ajouter / Modifier -->
<div class="modal fade" id="modalPilier" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalPilierLabel">Pilier</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlertZone" class="mb-3" style="display:none;"></div>
                <input type="hidden" id="modalAction" value="create">
                <input type="hidden" id="modalId" value="0">
                <div class="mb-3">
                    <label for="modalLibelle" class="form-label fw-semibold">
                        Libellé <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="modalLibelle" maxlength="150" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="savePilier()">
                    <i class="bi bi-floppy2-fill me-1"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Confirmation suppression -->
<div class="modal fade" id="modalDelete" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Confirmation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                    <i class="bi bi-trash3 me-1"></i> Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.SITE_ROOT  = '<?= SITE_ROOT ?>';
window.ADMIN_PAGE = 'piliers';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
