<?php
/**
 * API pour enregistrer les plans de traitement d'une date donnée.
 * Expects JSON body with structure:
 * {
 *   "date": "YYYY-MM-DD",
 *   "plans": [
 *     {
 *       "patient": "Nom du patient",
 *       "montant_devis": 100,
 *       "accepter": "Oui/Non",
 *       "date_acceptation": "YYYY-MM-DD",
 *       "montant": 80
 *     },
 *     ...
 *   ]
 * }
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireLogin();
checkCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError(405, 'Méthode non autorisée.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError(400, 'Corps de requête JSON invalide.');
}

$date = validateDate($input['date'] ?? '');
if ($date === null) {
    jsonError(400, 'Date invalide. Format attendu : YYYY-MM-DD.');
}

$plans = $input['plans'] ?? [];
if (!is_array($plans)) {
    jsonError(400, 'Liste de plans invalide.');
}

$user = getCurrentUser();
$db   = getDB();

if (isAdmin() && isset($input['id_utilisateur'])) {
    $targetId = (int)$input['id_utilisateur'];
    $chk = $db->prepare('SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = ? AND id_role = 1');
    $chk->execute([$targetId]);
    if (!$chk->fetch()) {
        jsonError(400, 'Dentiste invalide.');
    }
    $userId = $targetId;
} else {
    $userId = $user['id'];
}

$deleteStmt = $db->prepare(
    'DELETE FROM plan_traitement WHERE id_utilisateur = ? AND date = ?'
);
$insertStmt = $db->prepare(
    'INSERT INTO plan_traitement (id_utilisateur, date, patient, montant_devis, accepter, date_acceptation, montant)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);

$db->beginTransaction();
try {
    $deleteStmt->execute([$userId, $date]);

    foreach ($plans as $item) {
        $patient = trim((string)($item['patient'] ?? ''));
        if ($patient === '') {
            continue;
        }
        if (mb_strlen($patient) > 150) {
            $db->rollBack();
            jsonError(400, 'Le nom du patient dépasse 150 caractères.');
        }

        $montantDevis = (int)($item['montant_devis'] ?? 0);
        $accepter     = trim((string)$item['accepter'] ?? '');
        $montant      = (int)($item['montant'] ?? 0);
        $dateAcceptation = trim((string)$item['date_acceptation']) ;

        if ($montantDevis < 0 || $montant < 0) {
            $db->rollBack();
            jsonError(400, 'Les montants doivent être positifs.');
        }

        if(!$dateAcceptation) {
            $dateAcceptation = '0000-00-00';
        } else {
            $dateAcceptation = validateDate($dateAcceptation);
            if ($dateAcceptation === null) {
                $db->rollBack();
                jsonError(400, 'Date d\'acceptation invalide. Format attendu : YYYY-MM-DD.');
            }
        }

        $insertStmt->execute([
            $userId,
            $date,
            $patient,
            $montantDevis,
            $accepter,
            $dateAcceptation,
            $montant,
        ]);
    }

    $db->commit();
} catch (\PDOException $e) {
    $db->rollBack();
    jsonError(500, 'Erreur lors de l\'enregistrement des plans.');
}

jsonResponse([
    'success' => true,
    'message' => 'Plans de traitement enregistrés avec succès.',
]);
