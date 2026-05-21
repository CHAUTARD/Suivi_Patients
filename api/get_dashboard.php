<?php
/*
 * get_dashboard.php — Données du tableau de bord (mois en cours)
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireLogin();

$mois = date('Y-m');
[$year, $month] = array_map('intval', explode('-', $mois));

$user    = getCurrentUser();
$isAdmin = isAdmin();
$db      = getDB();

$moisLabel = (new DateTime("$year-$month-01"))->formatLocale !== false
    ? ucfirst((new DateTime("$year-$month-01"))->format('F Y'))
    : $mois;

setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'French');
$moisLabel = ucfirst(strftime('%B %Y', mktime(0, 0, 0, $month, 1, $year)));

if ($isAdmin) {
    // Saisies globales
    $stmt = $db->prepare(
        'SELECT MAX(date) as derniere_saisie
         FROM nombre WHERE YEAR(date) = ? AND MONTH(date) = ?'
    );
    $stmt->execute([$year, $month]);
    $saisieRow = $stmt->fetch();

    // Plans globaux
    $stmt = $db->prepare(
        'SELECT COUNT(*) as total,
                COALESCE(SUM(CASE WHEN accepter = \'Oui\' THEN 1 ELSE 0 END), 0) as acceptes,
                COALESCE(SUM(montant_devis), 0) as total_devis,
                COALESCE(SUM(montant), 0) as total_montant
         FROM plan_traitement WHERE YEAR(date) = ? AND MONTH(date) = ?'
    );
    $stmt->execute([$year, $month]);
    $planRow = $stmt->fetch();

    // Comparatif par dentiste
    $stmt = $db->prepare(
        'SELECT u.login,
                COUNT(DISTINCT n.date)  AS jours_saisis,
                MAX(n.date)             AS derniere_saisie,
                COALESCE(pt.total_plans,    0) AS total_plans,
                COALESCE(pt.total_acceptes, 0) AS total_acceptes,
                COALESCE(pt.total_devis,    0) AS total_devis
         FROM utilisateur u
         LEFT JOIN nombre n
                ON n.id_utilisateur = u.id_utilisateur
               AND YEAR(n.date) = ? AND MONTH(n.date) = ?
         LEFT JOIN (
             SELECT id_utilisateur,
                    COUNT(*) AS total_plans,
                    SUM(CASE WHEN accepter = \'Oui\' THEN 1 ELSE 0 END) AS total_acceptes,
                    COALESCE(SUM(montant_devis), 0) AS total_devis
             FROM plan_traitement
             WHERE YEAR(date) = ? AND MONTH(date) = ?
             GROUP BY id_utilisateur
         ) pt ON pt.id_utilisateur = u.id_utilisateur
         WHERE u.id_role = 1
         GROUP BY u.id_utilisateur, u.login
         ORDER BY u.login ASC'
    );
    $stmt->execute([$year, $month, $year, $month]);
    $dentistesRows = $stmt->fetchAll();

    $dentistes = [];
    foreach ($dentistesRows as $d) {
        $total = (int)$d['total_plans'];
        $acc   = (int)$d['total_acceptes'];
        $dentistes[] = [
            'login'           => $d['login'],
            'jours_saisis'    => (int)$d['jours_saisis'],
            'derniere_saisie' => $d['derniere_saisie'],
            'total_plans'     => $total,
            'total_acceptes'  => $acc,
            'taux'            => $total > 0 ? round($acc / $total * 100, 1) : 0,
            'total_devis'     => (int)$d['total_devis'],
        ];
    }

    $totalPlans = (int)$planRow['total'];
    $acceptes   = (int)$planRow['acceptes'];

    jsonResponse([
        'success'        => true,
        'mois'           => $mois,
        'mois_label'     => $moisLabel,
        'is_admin'       => true,
        'derniere_saisie'=> $saisieRow['derniere_saisie'],
        'plans' => [
            'total'        => $totalPlans,
            'acceptes'     => $acceptes,
            'taux'         => $totalPlans > 0 ? round($acceptes / $totalPlans * 100, 1) : 0,
            'total_devis'  => (int)$planRow['total_devis'],
            'total_montant'=> (int)$planRow['total_montant'],
        ],
        'dentistes' => $dentistes,
    ]);

} else {
    $uid = $user['id'];

    $stmt = $db->prepare(
        'SELECT COUNT(DISTINCT date) AS jours_saisis, MAX(date) AS derniere_saisie
         FROM nombre WHERE id_utilisateur = ? AND YEAR(date) = ? AND MONTH(date) = ?'
    );
    $stmt->execute([$uid, $year, $month]);
    $saisieRow = $stmt->fetch();

    $stmt = $db->prepare(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN accepter = \'Oui\' THEN 1 ELSE 0 END), 0) AS acceptes,
                COALESCE(SUM(montant_devis), 0) AS total_devis,
                COALESCE(SUM(montant), 0) AS total_montant
         FROM plan_traitement WHERE id_utilisateur = ? AND YEAR(date) = ? AND MONTH(date) = ?'
    );
    $stmt->execute([$uid, $year, $month]);
    $planRow = $stmt->fetch();

    $totalPlans = (int)$planRow['total'];
    $acceptes   = (int)$planRow['acceptes'];

    jsonResponse([
        'success'         => true,
        'mois'            => $mois,
        'mois_label'      => $moisLabel,
        'is_admin'        => false,
        'jours_saisis'    => (int)$saisieRow['jours_saisis'],
        'derniere_saisie' => $saisieRow['derniere_saisie'],
        'plans' => [
            'total'        => $totalPlans,
            'acceptes'     => $acceptes,
            'taux'         => $totalPlans > 0 ? round($acceptes / $totalPlans * 100, 1) : 0,
            'total_devis'  => (int)$planRow['total_devis'],
            'total_montant'=> (int)$planRow['total_montant'],
        ],
    ]);
}
