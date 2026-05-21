<?php
require_once __DIR__ . '/includes/auth.php';

startSession();
if (isLoggedIn()) {
    header('Location: ' . SITE_ROOT . '/home.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginInput = trim($_POST['login'] ?? '');
    $password   = $_POST['password'] ?? '';

    if ($loginInput === '' || $password === '') {
        $error = 'Veuillez saisir votre identifiant et votre mot de passe.';
    } elseif (login($loginInput, $password)) {
        header('Location: ' . SITE_ROOT . '/home.php');
        exit;
    } else {
        $error = 'Identifiant ou mot de passe incorrect.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= SITE_ROOT ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= SITE_ROOT ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= SITE_ROOT ?>/assets/css/style.css">
</head>
<body class="login-page d-flex align-items-center justify-content-center min-vh-100 bg-light">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-sm-8 col-md-5 col-lg-4">

            <!-- Logo / Titre -->
            <div class="text-center mb-4">
                <div class="login-logo mb-3">
                    <span style="font-size:3rem;">🦷</span>
                </div>
                <h1 class="h4 fw-bold text-primary"><?= htmlspecialchars(APP_NAME) ?></h1>
                <p class="text-muted"><?= htmlspecialchars(APP_SUBTITLE) ?></p>
            </div>

            <!-- Carte de connexion -->
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <h2 class="card-title h5 mb-4 text-center">Connexion</h2>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <div class="mb-3">
                            <label for="login" class="form-label fw-semibold">Identifiant</label>
                            <input type="text" class="form-control" id="login" name="login"
                                   autocomplete="username" autofocus required
                                   value="<?= htmlspecialchars($_POST['login'] ?? '') ?>">
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Mot de passe</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password"
                                       autocomplete="current-password" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePwd"
                                        title="Afficher/Masquer">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            Se connecter
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-muted small mt-3">
                &copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?>
            </p>
        </div>
    </div>
</div>

<script src="<?= SITE_ROOT ?>/assets/js/jquery.min.js"></script>
<script src="<?= SITE_ROOT ?>/assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Toggle affichage mot de passe
    $('#togglePwd').on('click', function () {
        var inp = $('#password');
        var icon = $('#toggleIcon');
        if (inp.attr('type') === 'password') {
            inp.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            inp.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });
</script>
</body>
</html>
