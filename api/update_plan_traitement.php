<?php
/*
 * update_plan_traitement.php
 * Met à jour les champs accepter, date_acceptation et montant d'un plan existant.
 * Seul le propriétaire du plan (ou un admin) peut le modifier.
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

$idPlan = isset($input['id_plan']) ? (int)$input['id_plan'] : 0;
if ($idPlan <= 0) {
    jsonError(400, 'id_plan invalide.');
}

$accepter = trim((string)($input['accepter'] ?? ''));
if (!in_array($accepter, ['Oui', 'Non', 'en Partie'], true)) {
    jsonError(400, 'Valeur "Accepté" invalide.');
}

$montant = (int)($input['montant'] ?? 0);
if ($montant < 0) {
    jsonError(400, 'Le montant doit être positif ou nul.');
}

$dateAcceptation = trim((string)($input['date_acceptation'] ?? ''));
if ($dateAcceptation !== '' && $dateAcceptation !== '0000-00-00') {
    $dateAcceptation = validateDate($dateAcceptation);
    if ($dateAcceptation === null) {
        jsonError(400, "Date d'acceptation invalide. Format attendu : YYYY-MM-DD.");
    }
} else {
    $dateAcceptation = '0000-00-00';
}

$user = getCurrentUser();
$db   = getDB();

$stmt = $db->prepare('SELECT id_utilisateur FROM plan_traitement WHERE id_plan = ?');
$stmt->execute([$idPlan]);
$plan = $stmt->fetch();

if (!$plan) {
    jsonError(404, 'Plan non trouvé.');
}

if (!isAdmin() && (int)$plan['id_utilisateur'] !== $user['id']) {
    jsonError(403, 'Accès refusé.');
}

$db->prepare(
    'UPDATE plan_traitement SET accepter = ?, date_acceptation = ?, montant = ? WHERE id_plan = ?'
)->execute([$accepter, $dateAcceptation, $montant, $idPlan]);

jsonResponse(['success' => true, 'message' => 'Plan mis à jour avec succès.']);
