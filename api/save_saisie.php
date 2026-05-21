<?php
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

$data = $input['data'] ?? [];
if (!is_array($data) || count($data) === 0) {
    jsonError(400, 'Aucune donnée à enregistrer.');
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

// Récupérer les ids d'action valides
$validActions = array_flip(
    array_map(
        'intval',
        $db->query('SELECT id_action FROM action')->fetchAll(PDO::FETCH_COLUMN)
    )
);

$stmt = $db->prepare(
    'INSERT INTO nombre (id_action, id_utilisateur, date, nombre)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE nombre = VALUES(nombre)'
);

$db->beginTransaction();
try {
    foreach ($data as $item) {
        $idAction = (int)($item['id_action'] ?? 0);
        $nombre   = $item['nombre'] ?? 0;

        // Validation : action doit exister
        if (!isset($validActions[$idAction])) {
            continue;
        }

        // Validation valeur : entier >= 0 uniquement
        if (!is_numeric($nombre) || (int)$nombre < 0 || (string)(int)$nombre !== (string)$nombre) {
            $db->rollBack();
            jsonError(400, 'Valeur invalide pour l\'action ' . $idAction);
        }

        $valeur = (int)$nombre;
        $stmt->execute([$idAction, $userId, $date, $valeur]);
    }
    $db->commit();
} catch (\PDOException $e) {
    $db->rollBack();
    jsonError(500, 'Erreur lors de l\'enregistrement.');
}

jsonResponse(['success' => true, 'message' => 'Données enregistrées avec succès.']);
