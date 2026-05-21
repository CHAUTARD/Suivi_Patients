<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user  = getCurrentUser();
$error = '';
$ok    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk      = verifyCsrfToken($_POST['csrf_token'] ?? '');
    $ancienPwd    = $_POST['ancien_pwd'] ?? '';
    $nouveauPwd   = $_POST['nouveau_pwd'] ?? '';
    $confirmPwd   = $_POST['confirm_pwd'] ?? '';

    if (!$tokenOk) {
        $error = 'Token de sécurité invalide. Actualisez la page.';
    } elseif ($ancienPwd === '' || $nouveauPwd === '' || $confirmPwd === '') {
        $error = 'Tous les champs sont obligatoires.';
    } elseif ($nouveauPwd !== $confirmPwd) {
        $error = 'Le nouveau mot de passe et sa confirmation ne correspondent pas.';
    } else {
        // SÉCURITÉ : Valider les formats
        $validAncien = validatePassword($ancienPwd);
        $validNouveau = validatePassword($nouveauPwd);
        
        if ($validAncien === null || $validNouveau === null) {
            $error = 'Le mot de passe doit contenir 6-128 caractères.';
        } else {
            $db   = getDB();
            // SÉCURITÉ : Requête préparée
            $stmt = $db->prepare('SELECT pwd FROM utilisateur WHERE id_utilisateur = ?');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();

            // SÉCURITÉ : Vérifier avec BCRYPT + utiliser password_verify
            if (!$row || !verifyPassword($validAncien, $row['pwd'])) {
                $error = "L'ancien mot de passe est incorrect.";
            } else {
                // SÉCURITÉ : Hacher le nouveau mot de passe avec BCRYPT
                $hash = hashPassword($validNouveau);
                // SÉCURITÉ : Requête préparée
                $upd  = $db->prepare(
                    'UPDATE utilisateur SET pwd = ? WHERE id_utilisateur = ?'
                );
                $upd->execute([$hash, $user['id']]);
                $ok = true;
            }
        }
    }
}

$pageTitle = 'Changer mon mot de passe';
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white">
                <h2 class="h5 mb-0"><i class="bi bi-key me-2"></i>Changer mon mot de passe</h2>
            </div>
            <div class="card-body p-4">

                <?php if ($ok): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        Mot de passe modifié avec succès.
                    </div>
                    <a href="<?= SITE_ROOT ?>/saisie.php" class="btn btn-outline-primary w-100">
                        Retour à la saisie
                    </a>
                <?php else: ?>
                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger py-2">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars(generateCsrfToken()) ?>">

                        <div class="mb-3">
                            <label for="ancien_pwd" class="form-label fw-semibold">
                                Ancien mot de passe
                            </label>
                            <input type="password" class="form-control" id="ancien_pwd"
                                   name="ancien_pwd" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label for="nouveau_pwd" class="form-label fw-semibold">
                                Nouveau mot de passe
                                <span class="text-muted fw-normal small">(min. 6 caractères)</span>
                            </label>
                            <input type="password" class="form-control" id="nouveau_pwd"
                                   name="nouveau_pwd" required autocomplete="new-password" minlength="6">
                        </div>
                        <div class="mb-4">
                            <label for="confirm_pwd" class="form-label fw-semibold">
                                Confirmer le nouveau mot de passe
                            </label>
                            <input type="password" class="form-control" id="confirm_pwd"
                                   name="confirm_pwd" required autocomplete="new-password" minlength="6">
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-floppy2-fill me-1"></i> Enregistrer
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
