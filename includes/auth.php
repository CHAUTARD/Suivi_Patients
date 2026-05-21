<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// ============================================================
// Gestion de la session
// ============================================================

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool
{
    startSession();
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && (int)($_SESSION['user_role'] ?? 0) === 2;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . SITE_ROOT . '/index.php');
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . SITE_ROOT . '/saisie.php');
        exit;
    }
}

function getCurrentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'        => (int)$_SESSION['user_id'],
        'login'     => $_SESSION['user_login'],
        'role'      => (int)$_SESSION['user_role'],
        'role_name' => $_SESSION['user_role_name'],
    ];
}

// ============================================================
// Authentification
// ============================================================

function login(string $loginInput, string $password): bool
{
    // SÉCURITÉ : Valider les inputs avant la requête BD
    $login = validateLogin($loginInput);
    if ($login === null) {
        return false; // Login format invalide
    }
    $pwd = validatePassword($password);
    if ($pwd === null) {
        return false; // Mot de passe format invalide
    }

    $db   = getDB();
    // Requête préparée : aucun risque d'injection SQL
    $stmt = $db->prepare(
        'SELECT u.*, r.role AS role_name
         FROM utilisateur u
         JOIN role r ON u.id_role = r.id_role
         WHERE u.login = ?'
    );
    $stmt->execute([$login]); // Utilise le login validé
    $user = $stmt->fetch();

    // Vérifier le mot de passe avec BCRYPT
    if ($user && verifyPassword($pwd, $user['pwd'])) {
        startSession();
        session_regenerate_id(true);
        $_SESSION['user_id']        = (int)$user['id_utilisateur'];
        $_SESSION['user_login']     = $user['login'];
        $_SESSION['user_role']      = (int)$user['id_role'];
        $_SESSION['user_role_name'] = $user['role_name'];
        return true;
    }
    return false;
}

function logout(): void
{
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . SITE_ROOT . '/index.php');
    exit;
}

// ============================================================
// Protection CSRF
// ============================================================

function generateCsrfToken(): string
{
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    startSession();
    return !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
