<?php
/* save_fermeture.php
 *
 * Crée ou met à jour une période de fermeture.
 * URL : POST /api/save_fermeture.php  — corps JSON
 *
 * Corps : { id, date_debut, date_fin, motif }
 * id = 0 → création, id > 0 → mise à jour
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireAdmin();
checkCsrf();

$body  = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($body['id']         ?? 0);
$debut = validateDate((string)($body['date_debut'] ?? ''));
$fin   = validateDate((string)($body['date_fin']   ?? ''));
$motif = mb_substr(trim((string)($body['motif']    ?? '')), 0, 255);

if (!$debut || !$fin) {
    jsonError(400, 'Dates invalides. Format attendu : YYYY-MM-DD.');
}
if ($fin < $debut) {
    jsonError(400, 'La date de fin doit être supérieure ou égale à la date de début.');
}

$db = getDB();

if ($id > 0) {
    $db->prepare(
        'UPDATE fermeture SET date_debut = ?, date_fin = ?, motif = ? WHERE id_fermeture = ?'
    )->execute([$debut, $fin, $motif ?: null, $id]);
    jsonResponse(['success' => true, 'message' => 'Période modifiée.']);
} else {
    $db->prepare(
        'INSERT INTO fermeture (date_debut, date_fin, motif) VALUES (?, ?, ?)'
    )->execute([$debut, $fin, $motif ?: null]);
    jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId(), 'message' => 'Période ajoutée.']);
}
