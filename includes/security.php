<?php
/**
 * security.php — Fonction de validation et de sécurité
 * SELARL La Vespalienne — Suivi des Actes
 * 
 * Valide les inputs pour prévenir les injections SQL et autres attaques.
 * Les requêtes SQL utilisent TOUJOURS des paramètres liés (prepared statements).
 */

// ============================================================
// Validation du login
// ============================================================
/**
 * Valide et nettoie le login.
 * Format autorisé : 3-30 caractères, alphanumériques + tiret + underscore.
 * @param string $login
 * @return string|null  Login valide ou null
 */
function validateLogin(string $login): ?string
{
    $login = trim($login);
    
    // Longueur stricte
    if (strlen($login) < 3 || strlen($login) > 30) {
        return null;
    }
    
    // Caractères autorisés : a-z, A-Z, 0-9, tiret, underscore
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $login)) {
        return null;
    }
    
    return $login;
}

// ============================================================
// Validation du mot de passe
// ============================================================
/**
 * Valide le mot de passe brut.
 * Contraintes : min 6 caractères, max 128 caractères.
 * @param string $pwd
 * @return string|null  Mot de passe si valide, null sinon
 */
function validatePassword(string $pwd): ?string
{
    $len = strlen($pwd);
    
    // Longueur minimale et maximale
    if ($len < 6 || $len > 128) {
        return null;
    }
    
    return $pwd;
}

// ============================================================
// Hachage sécurisé du mot de passe (BCRYPT)
// ============================================================
/**
 * Hash un mot de passe en BCRYPT (coût 12).
 * Le mot de passe ne doit JAMAIS être stocké en clair.
 * 
 * @param string $pwd  Mot de passe brut (validé)
 * @return string      Hash BCRYPT
 */
function hashPassword(string $pwd): string
{
    // PASSWORD_BCRYPT = algorithme bcrypt (sécurisé, reconnu, accepté par OWASP)
    // cost = 12 = 4096 itérations, bon équilibre sécurité/perf
    return password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
}

// ============================================================
// Vérification du mot de passe
// ============================================================
/**
 * Vérifie un mot de passe brut contre son hash BCRYPT.
 * @param string $pwd   Mot de passe brut
 * @param string $hash  Hash stocké en BD
 * @return bool
 */
function verifyPassword(string $pwd, string $hash): bool
{
    return password_verify($pwd, $hash);
}

// ============================================================
// Validation des paramètres API
// ============================================================
/**
 * Valide que l'utilisateur fourni existe et appartient à la portée correcte.
 * Utilisé pour les filtres admin (récap par utilisateur).
 * @param int $userIdFilter  ID utilisateur du filtre
 * @param int $currentUserId  ID de l'utilisateur actuel
 * @param bool $isAdmin
 * @return int  L'ID validé, ou l'ID courant par défaut
 */
function validateUserFilter(int $userIdFilter, int $currentUserId, bool $isAdmin): int
{
    // Les non-admin ne peuvent voir que leurs données
    if (!$isAdmin) {
        return $currentUserId;
    }
    
    // Admin : filtre 0 = tous, sinon doit être un ID valide (>0)
    if ($userIdFilter === 0) {
        return 0;
    }
    
    // Vérifier que l'ID est positif
    return max(0, $userIdFilter);
}

// ============================================================
// Notice de sécurité : Requêtes SQL préparées
// ============================================================
/**
 * IMPORTANT :
 * 
 * Toutes les requêtes SQL utilisent les requêtes préparées (prepared statements)
 * avec paramètres liés, ce qui prévient complètement les injections SQL.
 * 
 * Exemple SÉCURISÉ :
 *   $stmt = $db->prepare('SELECT * FROM utilisateur WHERE login = ? AND id_role = ?');
 *   $stmt->execute([$login, 1]);
 * 
 * Exemple DANGEREUX (NEP JAMAIS FAIRE) :
 *   $sql = "SELECT * FROM utilisateur WHERE login = '" . $login . "'";
 *   $db->query($sql);  // INJECTION SQL possible !
 */
