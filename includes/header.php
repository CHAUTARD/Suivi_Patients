<?php
// header.php - Inclus dans toutes les pages authentifiées
// Prérequis : auth.php inclus, startSession() appelé, requireLogin() vérifié
// Variables attendues : $pageTitle (string), $extraScripts (array, optionnel)
$user = getCurrentUser();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME) ?> — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= SITE_ROOT ?>/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?= SITE_ROOT ?>/assets/css/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= SITE_ROOT ?>/assets/css/flatpickr.min.css">
    <link rel="stylesheet" href="<?= SITE_ROOT ?>/assets/css/style.css">
</head>
<body>

<!-- Barre de navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= SITE_ROOT ?>/home.php">
            <span class="me-2">🦷</span><?= htmlspecialchars(APP_NAME) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarMain" aria-controls="navbarMain"
                aria-expanded="false" aria-label="Basculer la navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'home.php') ? 'active' : '' ?>"
                       href="<?= SITE_ROOT ?>/home.php">
                        <i class="bi bi-house-door"></i> Accueil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'saisie.php') ? 'active' : '' ?>"
                       href="<?= SITE_ROOT ?>/saisie.php">
                        <i class="bi bi-pencil-square"></i> Saisie
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'recap.php') ? 'active' : '' ?>"
                       href="<?= SITE_ROOT ?>/recap.php">
                        <i class="bi bi-table"></i> Récapitulatif
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) === 'recap_plans.php') ? 'active' : '' ?>"
                       href="<?= SITE_ROOT ?>/recap_plans.php">
                        <i class="bi bi-clipboard2-data"></i> Récap Plans
                    </a>
                </li>
                <?php if ($user && isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : '' ?>"
                       href="<?= SITE_ROOT ?>/admin/index.php">
                        <i class="bi bi-gear-fill"></i> Administration
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown"
                       role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <?= htmlspecialchars($user['login'] ?? '') ?>
                        <span class="badge bg-light text-primary ms-1 small">
                            <?= htmlspecialchars($user['role_name'] ?? '') ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="<?= SITE_ROOT ?>/changer_pwd.php">
                                <i class="bi bi-key"></i> Changer mon mot de passe
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="<?= SITE_ROOT ?>/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Déconnexion
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Contenu principal -->
<main class="container-fluid py-4">
