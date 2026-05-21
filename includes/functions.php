<?php
require_once __DIR__ . '/auth.php';

// ============================================================
// Fonctions utilitaires pour les API (JSON)
// ============================================================

/**
 * Envoie une réponse JSON et termine le script.
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Envoie une erreur JSON et termine le script.
 */
function jsonError(int $status, string $message): void
{
    jsonResponse(['success' => false, 'error' => $message], $status);
}

/**
 * Vérifie le token CSRF envoyé dans l'entête HTTP ou le POST.
 * À appeler dans les API.
 */
function checkCsrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $_POST['csrf_token']
          ?? '';
    if (!verifyCsrfToken($token)) {
        jsonError(403, 'Token CSRF invalide.');
    }
}

/**
 * Vérifie que l'utilisateur est connecté (pour les API).
 */
function apiRequireLogin(): void
{
    if (!isLoggedIn()) {
        jsonError(401, 'Non authentifié.');
    }
}

/**
 * Vérifie que l'utilisateur est admin (pour les API).
 */
function apiRequireAdmin(): void
{
    apiRequireLogin();
    if (!isAdmin()) {
        jsonError(403, 'Accès refusé.');
    }
}

/**
 * Formate une valeur entière.
 */
function formatValeur(int $val): string
{
    return (string)(int)$val;
}

/**
 * Nettoie et valide une date au format Y-m-d.
 * Retourne la date si valide, null sinon.
 */
function validateDate(string $date): ?string
{
    $d = \DateTime::createFromFormat('Y-m-d', $date);
    if ($d && $d->format('Y-m-d') === $date) {
        return $date;
    }
    return null;
}

/**
 * Nettoie et valide un mois au format Y-m.
 * Retourne le mois si valide, null sinon.
 */
function validateMois(string $mois): ?string
{
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mois)) {
        return $mois;
    }
    return null;
}
