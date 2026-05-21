<?php
/*
 * get_plans_relance.php
 * Retourne les plans non acceptés dont la date dépasse le délai de relance.
 * GET — aucun paramètre requis.
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireAdmin();

$db    = getDB();
$delai = 30; // valeur par défaut si la table n'existe pas encore

/* ---- Lire le délai configuré ---- */
try {
    $st  = $db->query("SELECT valeur FROM parametres WHERE cle = 'delai_relance'");
    $row = $st->fetch();
    if ($row && is_numeric($row['valeur'])) {
        $delai = max(1, (int)$row['valeur']);
    }
} catch (\Exception $e) {
    // Table absente → on conserve la valeur par défaut
}

/* ---- Plans à relancer ---- */
$plans = [];
try {
    $stmt = $db->prepare(
        "SELECT p.id_plan,
                p.date,
                p.patient,
                p.montant_devis,
                p.accepter,
                u.login,
                DATEDIFF(CURDATE(), p.date) AS jours_ecoules
         FROM plan_traitement p
         JOIN utilisateur u ON u.id_utilisateur = p.id_utilisateur
         WHERE p.accepter IN ('Non', 'en Partie')
           AND DATEDIFF(CURDATE(), p.date) >= ?
         ORDER BY p.date ASC, u.login ASC, p.patient ASC"
    );
    $stmt->execute([$delai]);
    foreach ($stmt->fetchAll() as $r) {
        $plans[] = [
            'id_plan'       => (int)$r['id_plan'],
            'date'          => $r['date'],
            'patient'       => $r['patient'],
            'montant_devis' => (int)$r['montant_devis'],
            'accepter'      => $r['accepter'],
            'login'         => $r['login'],
            'jours_ecoules' => (int)$r['jours_ecoules'],
        ];
    }
} catch (\Exception $e) {
    jsonError(500, 'Erreur lors du chargement des plans : ' . $e->getMessage());
}

jsonResponse([
    'success' => true,
    'delai'   => $delai,
    'total'   => count($plans),
    'plans'   => $plans,
]);
