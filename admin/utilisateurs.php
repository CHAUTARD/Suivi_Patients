<?php
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db           = getDB();
$currentUser  = getCurrentUser();
$message      = '';
$messageType  = 'success';

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
        $loginInput = trim($_POST['login'] ?? '');
        $idRole     = (int)($_POST['id_role'] ?? 1);
        $pwdInput   = $_POST['pwd'] ?? '';
        $id         = (int)($_POST['id'] ?? 0);

        // SÉCURITÉ : Valider le login
        $login = validateLogin($loginInput);
        if ($login === null) {
            echo json_encode(['success' => false, 
                'error' => 'Le login doit contenir 3-30 caractères (alphanumériques, tirets, underscores)']);
            exit;
        }
        if (!in_array($idRole, [1, 2], true)) {
            echo json_encode(['success' => false, 'error' => 'Rôle invalide.']);
            exit;
        }
        
        // SÉCURITÉ : Valider le mot de passe si fourni
        $validatedPwd = null;
        if ($action === 'create') {
            if ($pwdInput === '') {
                echo json_encode(['success' => false, 'error' => 'Le mot de passe est obligatoire.']);
                exit;
            }
            $validatedPwd = validatePassword($pwdInput);
            if ($validatedPwd === null) {
                echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir 6-128 caractères.']);
                exit;
            }
        } elseif ($pwdInput !== '') {
            $validatedPwd = validatePassword($pwdInput);
            if ($validatedPwd === null) {
                echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir 6-128 caractères.']);
                exit;
            }
        }

        try {
            if ($action === 'create') {
                $stmt = $db->prepare(
                    'INSERT INTO utilisateur (id_role, login, pwd) VALUES (?, ?, ?)'
                );
                // SÉCURITÉ : Requête préparée + hachage BCRYPT
                $stmt->execute([$idRole, $login, hashPassword($validatedPwd)]);
                echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
            } else {
                // SÉCURITÉ : Requête préparée pour vérifier l'unicité
                $chk = $db->prepare(
                    'SELECT id_utilisateur FROM utilisateur WHERE login = ? AND id_utilisateur != ?'
                );
                $chk->execute([$login, $id]);
                if ($chk->fetch()) {
                    echo json_encode(['success' => false, 'error' => 'Ce login est déjà utilisé.']);
                    exit;
                }
                if ($validatedPwd !== null) {
                    $stmt = $db->prepare(
                        'UPDATE utilisateur SET login = ?, id_role = ?, pwd = ? WHERE id_utilisateur = ?'
                    );
                    // SÉCURITÉ : Requête préparée + hachage BCRYPT
                    $stmt->execute([$login, $idRole, hashPassword($validatedPwd), $id]);
                } else {
                    $stmt = $db->prepare(
                        'UPDATE utilisateur SET login = ?, id_role = ? WHERE id_utilisateur = ?'
                    );
                    $stmt->execute([$login, $idRole, $id]);
                }
                echo json_encode(['success' => true]);
            }
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000') {
                echo json_encode(['success' => false, 'error' => 'Ce login est déjà utilisé.']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur base de données.']);
            }
        }
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $currentUser['id']) {
            echo json_encode(['success' => false, 'error' => 'Vous ne pouvez pas supprimer votre propre compte.']);
            exit;
        }
        // Vérifier si l'utilisateur a des saisies
        $chk = $db->prepare('SELECT COUNT(*) FROM nombre WHERE id_utilisateur = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'Impossible : cet utilisateur possède des saisies enregistrées.']);
            exit;
        }
        $stmt = $db->prepare('DELETE FROM utilisateur WHERE id_utilisateur = ?');
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
$sortBy = $_GET['sort'] ?? 'role';
$sortBy = in_array($sortBy, ['login', 'role'], true) ? $sortBy : 'role';

$sortDir = strtolower($_GET['dir'] ?? 'asc');
$sortDir = ($sortDir === 'desc') ? 'DESC' : 'ASC';

if ($sortBy === 'login') {
    $orderBy = 'u.login ' . $sortDir . ', r.id_role ASC, u.id_utilisateur ASC';
} else {
    $orderBy = 'r.id_role ' . $sortDir . ', u.login ASC, u.id_utilisateur ASC';
}

$utilisateurs = $db->query(
    'SELECT u.id_utilisateur, u.login, u.id_role, r.role
     FROM utilisateur u
     JOIN role r ON u.id_role = r.id_role
     ORDER BY ' . $orderBy
)->fetchAll();

$loginNextDir = ($sortBy === 'login' && $sortDir === 'ASC') ? 'desc' : 'asc';
$roleNextDir  = ($sortBy === 'role'  && $sortDir === 'ASC') ? 'desc' : 'asc';

$roles = $db->query('SELECT id_role, role FROM role ORDER BY id_role')->fetchAll();

$pageTitle    = 'Gestion des utilisateurs';
$extraScripts = ['admin.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Fil de navigation -->
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item">
            <a href="<?= SITE_ROOT ?>/admin/index.php">Administration</a>
        </li>
        <li class="breadcrumb-item active">Utilisateurs</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h4 mb-0 fw-bold text-primary">
        <i class="bi bi-people-fill me-2"></i>Gestion des utilisateurs
    </h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUtilisateur"
            id="btnAjouter" onclick="openCreateModal()">
        <i class="bi bi-plus-lg me-1"></i> Ajouter un utilisateur
    </button>
</div>

<div id="alertZone" class="mb-3" style="display:none;"></div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <table class="table table-hover table-bordered mb-0" id="tableUtilisateurs">
            <thead class="table-dark">
                <tr>
                    <th style="width:50px;">#</th>
                    <th>
                        <a class="text-white text-decoration-none"
                           href="?sort=login&dir=<?= $loginNextDir ?>"
                           title="Trier par login">
                            Login
                            <?php if ($sortBy === 'login'): ?>
                                <?= $sortDir === 'ASC' ? ' ▲' : ' ▼' ?>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th>
                        <a class="text-white text-decoration-none"
                           href="?sort=role&dir=<?= $roleNextDir ?>"
                           title="Trier par rôle">
                            Rôle
                            <?php if ($sortBy === 'role'): ?>
                                <?= $sortDir === 'ASC' ? ' ▲' : ' ▼' ?>
                            <?php endif; ?>
                        </a>
                    </th>
                    <th style="width:130px;" class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($utilisateurs as $u): ?>
                <tr id="row-<?= (int)$u['id_utilisateur'] ?>">
                    <td class="text-muted small"><?= (int)$u['id_utilisateur'] ?></td>
                    <td>
                        <?= htmlspecialchars($u['login']) ?>
                        <?php if ((int)$u['id_utilisateur'] === $currentUser['id']): ?>
                            <span class="badge bg-secondary ms-1">Moi</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= (int)$u['id_role'] === 2 ? 'bg-warning text-dark' : 'bg-primary' ?>">
                            <?= htmlspecialchars($u['role']) ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-primary me-1"
                                onclick="openEditModal(<?= (int)$u['id_utilisateur'] ?>,
                                         '<?= addslashes(htmlspecialchars($u['login'])) ?>',
                                         <?= (int)$u['id_role'] ?>)"
                                title="Modifier">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <?php if ((int)$u['id_utilisateur'] !== $currentUser['id']): ?>
                        <button class="btn btn-sm btn-outline-danger"
                                onclick="confirmDelete(<?= (int)$u['id_utilisateur'] ?>,
                                         '<?= addslashes(htmlspecialchars($u['login'])) ?>')"
                                title="Supprimer">
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

<!-- Modal Ajouter / Modifier -->
<div class="modal fade" id="modalUtilisateur" tabindex="-1"
     aria-labelledby="modalUtilisateurLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalUtilisateurLabel">Utilisateur</h5>
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalAlertZone" class="mb-3" style="display:none;"></div>
                <input type="hidden" id="modalAction" value="create">
                <input type="hidden" id="modalId" value="0">
                <div class="mb-3">
                    <label for="modalLogin" class="form-label fw-semibold">Login <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="modalLogin" maxlength="30" required>
                </div>
                <div class="mb-3">
                    <label for="modalRole" class="form-label fw-semibold">Rôle <span class="text-danger">*</span></label>
                    <select class="form-select" id="modalRole">
                        <?php foreach ($roles as $r): ?>
                        <option value="<?= (int)$r['id_role'] ?>">
                            <?= htmlspecialchars($r['role']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="modalPwd" class="form-label fw-semibold">
                        Mot de passe
                        <span class="text-muted fw-normal small" id="pwdHint">(min. 6 caractères)</span>
                    </label>
                    <input type="password" class="form-control" id="modalPwd"
                           autocomplete="new-password" minlength="6">
                    <div class="form-text" id="pwdEditHint" style="display:none;">
                        Laissez vide pour conserver le mot de passe actuel.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="btnSaveModal"
                        onclick="saveUtilisateur()">
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
                <button type="button" class="btn-close btn-close-white"
                        data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="deleteMessage"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Annuler
                </button>
                <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                    <i class="bi bi-trash3 me-1"></i> Supprimer
                </button>
            </div>
        </div>
    </div>
</div>

<script>
window.SITE_ROOT  = '<?= SITE_ROOT ?>';
window.ADMIN_PAGE = 'utilisateurs';
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
