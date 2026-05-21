<?php
/* get_saisie.php
 *
 * Récupère les plans de traitement pour un mois donné, avec possibilité de filtrer par dentiste (admin).
 * 
 * URL : GET /api/get_recap_plans.php?mois=YYYY-MM&id_utilisateur=ID (id_utilisateur optionnel pour admin)
 * 
 * Réponse : JSON avec la liste des plans et des statistiques
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
    'SELECT a.id_action, a.action, a.formule,
            p.id_pilier, p.Pilier,
            COALESCE(n.nombre, 0) AS nombre
     FROM action a
     JOIN pilier p ON a.id_pilier = p.id_pilier
     LEFT JOIN nombre n
            ON n.id_action      = a.id_action
           AND n.id_utilisateur = :uid
           AND n.date           = :date
     ORDER BY p.id_pilier ASC, a.ord ASC'
);
$stmt->execute([':uid' => $uid, ':date' => $date]);
$rows = $stmt->fetchAll();

// Grouper par pilier
$piliers = [];
foreach ($rows as $row) {
    $pid = (int)$row['id_pilier'];
    if (!isset($piliers[$pid])) {
        $piliers[$pid] = [
            'id_pilier' => $pid,
            'Pilier'    => $row['Pilier'],
            'actions'   => [],
        ];
    }
    $piliers[$pid]['actions'][] = [
        'id_action' => (int)$row['id_action'],
        'action'    => $row['action'],
        'formule'   => $row['formule'],          // null ou '=1+2+3'
        'nombre'    => (int)$row['nombre'],
    ];
}

jsonResponse([
    'success' => true,
    'date'    => $date,
    'piliers' => array_values($piliers),
]);