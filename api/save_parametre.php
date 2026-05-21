<?php
/*
 * save_parametre.php
 * Enregistre un paramètre applicatif.
 * POST — corps JSON : { "cle": "delai_relance", "valeur": 30 }
 *
 * Crée automatiquement la table `parametres` si elle n'existe pas encore.
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireAdmin();
checkCsrf();

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$cle    = trim((string)($body['cle']    ?? ''));
$valeur = trim((string)($body['valeur'] ?? ''));

/* ---- Liste blanche des clés autorisées ---- */
$allowed = ['delai_relance'];
if (!in_array($cle, $allowed, true)) {
    jsonError(400, 'Paramètre non autorisé.');
}

/* ---- Validation spécifique par clé ---- */
if ($cle === 'delai_relance') {
    $v = (int)$valeur;
    if ($v < 1 || $v > 365) {
        jsonError(400, 'Le délai de relance doit être compris entre 1 et 365 jours.');
    }
    $valeur = (string)$v;
}

$db = getDB();

try {
    /* Crée la table si elle n'existe pas encore (auto-migration) */
    $db->exec(
        "CREATE TABLE IF NOT EXISTS parametres (
            cle     VARCHAR(100) NOT NULL,
            valeur  TEXT         DEFAULT NULL,
            PRIMARY KEY (cle)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $db->prepare(
        "INSERT INTO parametres (cle, valeur) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
    );
    $stmt->execute([$cle, $valeur]);

    jsonResponse(['success' => true, 'cle' => $cle, 'valeur' => $valeur]);

} catch (\Exception $e) {
    jsonError(500, 'Erreur lors de la sauvegarde : ' . $e->getMessage());
}
