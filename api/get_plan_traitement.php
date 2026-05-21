<?php
/*
 * get_plan_traitement.php
 *
 * API pour récupérer les plans de traitement d'un utilisateur pour une date donnée.
 * URL : /api/get_plan_traitement.php?date=YYYY-MM-DD
 *
 * Modif :
 * Adapté pour varchar accepter (Oui, Non, en Partie) - 14/05/2026
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireLogin();

$date = validateDate($_GET['date'] ?? date('Y-m-d'));
if ($date === null) {
    jsonError(400, 'Date invalide. Format attendu : YYYY-MM-DD.');
}

$user = getCurrentUser();
$db   = getDB();

if (isAdmin() && isset($_GET['id_utilisateur'])) {
    $targetId = (int)$_GET['id_utilisateur'];
    $chk = $db->prepare('SELECT id_utilisateur FROM utilisateur WHERE id_utilisateur = ? AND id_role = 1');
    $chk->execute([$targetId]);
    if (!$chk->fetch()) {
        jsonError(400, 'Dentiste invalide.');
    }
    $uid = $targetId;
} else {
    $uid = $user['id'];
}

$stmt = $db->prepare(
    'SELECT id_plan, patient, montant_devis, accepter, date_acceptation, montant
     FROM plan_traitement
     WHERE id_utilisateur = ? AND date = ?
     ORDER BY id_plan ASC'
);
$stmt->execute([$uid, $date]);
$rows = $stmt->fetchAll();

$plans = array_map(static function (array $row): array {
    return [
        'id_plan'       => (int)$row['id_plan'],
        'patient'       => $row['patient'],
        'montant_devis' => (int)$row['montant_devis'],
        'accepter'      => $row['accepter'],
        'date_acceptation' => $row['date_acceptation'],
        'montant'       => (int)$row['montant'],
    ];
}, $rows);

jsonResponse([
    'success' => true,
    'date'    => $date,
    'plans'   => $plans,
]);
