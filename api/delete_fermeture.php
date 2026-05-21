<?php
/* delete_fermeture.php
 *
 * Supprime une période de fermeture.
 * URL : POST /api/delete_fermeture.php  — corps JSON
 *
 * Corps : { id }
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireAdmin();
checkCsrf();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = (int)($body['id'] ?? 0);

if ($id <= 0) {
    jsonError(400, 'Identifiant invalide.');
}

$db   = getDB();
$stmt = $db->prepare('DELETE FROM fermeture WHERE id_fermeture = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    jsonError(404, 'Période introuvable.');
}

jsonResponse(['success' => true, 'message' => 'Période supprimée.']);
