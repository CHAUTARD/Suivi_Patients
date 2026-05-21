<?php
/* get_fermetures.php
 *
 * Retourne toutes les périodes de fermeture (page d'administration).
 * URL : GET /api/get_fermetures.php
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireAdmin();

$db   = getDB();
$stmt = $db->query(
    'SELECT id_fermeture, date_debut, date_fin, motif
     FROM fermeture
     ORDER BY date_debut DESC'
);

jsonResponse(['success' => true, 'fermetures' => $stmt->fetchAll()]);
