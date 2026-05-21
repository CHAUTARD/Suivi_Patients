<?php
/* actions.php
 *
 * Page d'administration pour gérer les actions (CRUD).
 * Accessible uniquement aux administrateurs.
 *
 * URL : /admin/actions.php
 */
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
        $libelle     = trim($_POST['libelle'] ?? '');
        $idPilier    = (int)($_POST['id_pilier'] ?? 0);
        $ord         = (int)($_POST['ord'] ?? 0);
        $id          = (int)($_POST['id'] ?? 0);
        $formuleRaw  = trim($_POST['formule'] ?? '');

        if ($libelle === '') {
            echo json_encode(['success' => false, 'error' => 'Le libellé est obligatoire.']);
            exit;
        }
        if (strlen($libelle) > 200) {
            echo json_encode(['success' => false, 'error' => 'Le libellé ne peut pas dépasser 200 caractères.']);
            exit;
        }
        if ($ord < 0) {
            echo json_encode(['success' => false, 'error' => 'L\'ordre doit être un entier positif.']);
            exit;
        }

        // Valider et normaliser la formule
        $formule = null;
        if ($formuleRaw !== '') {
            if (!preg_match('/^=[\d+\s]+$/', $formuleRaw)) {
                echo json_encode(['success' => false, 'error' => 'Formule invalide. Format attendu : =1+2+3']);
                exit;
            }
            preg_match_all('/\d+/', $formuleRaw, $m);
            $refs = array_unique(array_map('intval', $m[0]));
            // Exclure la référence circulaire (self)
            if ($id > 0) {
                $refs = array_filter($refs, fn($r) => $r !== $id);
            }
            if (empty($refs)) {
                echo json_encode(['success' => false, 'error' => 'La formule doit référencer au moins une autre action.']);
                exit;
            }
            $formule = '=' . implode('+', $refs);
        }

        // Vérifier que le pilier existe
        $chkP = $db->prepare('SELECT id_pilier FROM pilier WHERE id_pilier = ?');
        $chkP->execute([$idPilier]);
        if (!$chkP->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Pilier inexistant.']);
            exit;
        }

        try {
            if ($action === 'create') {
                $stmt = $db->prepare(
                    'INSERT INTO action (id_pilier, action, ord, formule) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$idPilier, $libelle, $ord, $formule]);
                echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
            } else {
                $stmt = $db->prepare(
                    'UPDATE action SET id_pilier = ?, action = ?, ord = ?, formule = ?
                     WHERE id_action = ?'
                );
                $stmt->execute([$idPilier, $libelle, $ord, $formule, $id]);
                echo json_encode(['success' => true]);
            }
        } catch (\PDOException $e) {
            echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Vérifier si l'action a des saisies
        $chk = $db->prepare('SELECT COUNT(*) FROM nombre WHERE id_action = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            echo json_encode([
                'success' => false,
                'error'   => 'Impossible : cette action possède des données saisies.',
            ]);
            exit;
        }
        $stmt = $db->prepare('DELETE FROM action WHERE id_action = ?');
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
$actions = $db->query(
    'SELECT a.id_action, a.action, a.formule, a.ord, a.id_pilier, p.Pilier
     FROM action a
     JOIN pilier p ON a.id_pilier = p.id_pilier
     ORDER BY p.id_pilier ASC, a.ord ASC'
)->fetchAll();

$piliers = $db->query(
    'SELECT id_pilier, Pilier FROM pilier ORDER BY id_pilier'
)->fetchAll();

$pageTitle    = 'Gestion des actions';
$extraScripts = ['admin.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= SITE_ROOT ?>/admin/index.php">Administration</a>
        </li>
        <li class="breadcrumb-item active">Actions</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0 fw-bold text-primary">
        <i class="bi bi-list-check me-2"></i>Gestion des actions
    </h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAction"
            onclick="openCreateModal()">
        <i class="bi bi-plus-lg me-1"></i> Ajouter une action
    </button>
</div>

<div id="alertZone" class="mb-3" style="display:none;"></div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered mb-0">
                <thead class="table-dark">
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Pilier</th>
                        <th>Libellé de l'action</th>
                        <th style="width:130px;" class="text-center">Formule</th>
                        <th style="width:70px;" class="text-center">Ordre</th>
                        <th style="width:110px;" class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $lastPilier = null;
                    foreach ($actions as $a):
                        if ($a['Pilier'] !== $lastPilier):
                            $lastPilier = $a['Pilier'];
                    ?>
                    <tr class="table-secondary">
                        <td colspan="6" class="fw-semibold small py-1">
                            <i class="bi bi-layer-forward me-1"></i>
                            <?= htmlspecialchars($a['Pilier']) ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr id="row-<?= (int)$a['id_action'] ?>" <?= $a['formule'] ? 'class="table-info"' : '' ?>>
                        <td class="text-muted small"><?= (int)$a['id_action'] ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($a['Pilier']) ?></td>
                        <td><?= htmlspecialchars($a['action']) ?></td>
                        <td class="text-center">
                            <?php if ($a['formule']): ?>
                                <code class="text-info"><?= htmlspecialchars($a['formule']) ?></code>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center text-muted small"><?= (int)$a['ord'] ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary me-1"
                                    onclick="openEditModal(
                                        <?= (int)$a['id_action'] ?>,
                                        '<?= addslashes($a['action']) ?>',
                                        <?= (int)$a['id_pilier'] ?>,
                                        <?= (int)$a['ord'] ?>,
                                        '<?= addslashes($a['formule'] ?? '') ?>')"
                                    title="Modifier">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if (!$a['formule']): ?>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="confirmDelete(<?= (int)$a['id_action'] ?>,
                                             '<?= addslashes(htmlspecialchars($a['action'])) ?>')"
                                    title="Supprimer">
                                <i class="bi bi-trash3"></i>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-outline-danger"
                                    onclick="confirmDelete(<?= (int)$a['id_action'] ?>,
                                             '<?= addslashes(htmlspecialchars($a['action'])) ?>')"
                                    title="Supprimer la ligne calculée">
                                <i class="bi bi-trash3"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Ajouter / Modifier -->
<div class="modal fade" id="modalAction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalActionLabel">Action</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlertZone" class="mb-3" style="display:none;"></div>
                <input type="hidden" id="modalAction2" value="create">
                <input type="hidden" id="modalId" value="0">
                <div class="mb-3">
                    <label for="modalPilier" class="form-label fw-semibold">
                        Pilier <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="modalPilier">
                        <?php foreach ($piliers as $p): ?>
                        <option value="<?= (int)$p['id_pilier'] ?>">
                            <?= htmlspecialchars($p['Pilier']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="modalLibelle" class="form-label fw-semibold">
                        Libellé <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="modalLibelle"
                           maxlength="200" required>
                </div>
                <div class="mb-3">
                    <label for="modalOrd" class="form-label fw-semibold">
                        Ordre d'affichage <span class="text-danger">*</span>
                    </label>
                    <input type="number" class="form-control" id="modalOrd"
                           min="0" step="10" value="0" required>
                    <div class="form-text">Entier utilisé pour trier les actions au sein d'un pilier (ex : 10, 20, 30…).</div>
                </div>
                <div class="mb-3">
                    <label for="modalFormule" class="form-label fw-semibold">Formule de cumul</label>
                    <input type="text" class="form-control font-monospace" id="modalFormule"
                           maxlength="255" placeholder="Laisser vide pour une saisie manuelle — ex : =1+2+3+4">
                    <div class="form-text">
                        Si renseignée, la ligne est <strong>calculée automatiquement</strong> (lecture seule) comme la somme
                        des actions dont vous indiquez les numéros. Format : <code>=id1+id2+id3</code>.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="saveAction()">
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
window.ADMIN_PAGE = 'actions';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>