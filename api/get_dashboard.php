<?php
/*
 * get_dashboard.php — Données du tableau de bord (mois en cours)
 */
// Les avertissements PHP ne doivent pas corrompre la réponse JSON
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireLogin();

$mois = date('Y-m');
[$year, $month] = array_map('intval', explode('-', $mois));

$user    = getCurrentUser();
$isAdmin = isAdmin();
$db      = getDB();

// Nom du mois en français sans strftime (déprécié PHP 8.1, supprimé PHP 8.2)
$moisNoms  = [
    1 => 'Janvier', 2 => 'Février',  3 => 'Mars',      4 => 'Avril',
    5 => 'Mai',     6 => 'Juin',     7 => 'Juillet',   8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
];
$moisLabel = ($moisNoms[$month] ?? '') . ' ' . $year;

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

    // Seuil d'alerte : aujourd'hui − 5 jours ouvrables (lun–ven)
    $seuil = new DateTime();
    $joursOuvrables = 0;
    while ($joursOuvrables < 5) {
        $seuil->modify('-1 day');
        if ((int)$seuil->format('N') <= 5) { // 1=Lun … 5=Ven
            $joursOuvrables++;
        }
    }
    $seuilStr = $seuil->format('Y-m-d');

    // Comparatif par dentiste (+ derniere_globale toutes périodes confondues)
    $stmt = $db->prepare(
        'SELECT u.login,
                COUNT(DISTINCT n.date)          AS jours_saisis,
                MAX(n.date)                     AS derniere_saisie,
                MAX(ns.derniere_globale)        AS derniere_globale,
                COALESCE(pt.total_plans,    0)  AS total_plans,
                COALESCE(pt.total_acceptes, 0)  AS total_acceptes,
                COALESCE(pt.total_devis,    0)  AS total_devis
         FROM utilisateur u
         LEFT JOIN nombre n
                ON n.id_utilisateur = u.id_utilisateur
               AND YEAR(n.date) = ? AND MONTH(n.date) = ?
         LEFT JOIN (
             SELECT id_utilisateur, MAX(date) AS derniere_globale
             FROM nombre
             GROUP BY id_utilisateur
         ) ns ON ns.id_utilisateur = u.id_utilisateur
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
        $total          = (int)$d['total_plans'];
        $acc            = (int)$d['total_acceptes'];
        $derniereGlob   = $d['derniere_globale'];
        $dentistes[] = [
            'login'            => $d['login'],
            'jours_saisis'     => (int)$d['jours_saisis'],
            'derniere_saisie'  => $d['derniere_saisie'],
            'total_plans'      => $total,
            'total_acceptes'   => $acc,
            'taux'             => $total > 0 ? round($acc / $total * 100, 1) : 0,
            'total_devis'      => (int)$d['total_devis'],
            'en_retard'        => ($derniereGlob === null || $derniereGlob < $seuilStr),
        ];
    }

    // Répartition des actes par pilier (actions non calculées uniquement)
    $piliersActes = [];
    try {
        $stmtPiliers = $db->prepare(
            'SELECT p.id_pilier, p.Pilier,
                    COALESCE(SUM(n.nombre), 0) AS total
             FROM pilier p
             LEFT JOIN action a
                    ON a.id_pilier = p.id_pilier
                   AND (a.formule IS NULL OR a.formule NOT LIKE \'=%\')
             LEFT JOIN nombre n
                    ON n.id_action = a.id_action
                   AND YEAR(n.date) = ? AND MONTH(n.date) = ?
             GROUP BY p.id_pilier, p.Pilier
             ORDER BY p.id_pilier ASC'
        );
        $stmtPiliers->execute([$year, $month]);
        $piliersActes = $stmtPiliers->fetchAll();
        foreach ($piliersActes as &$pr) { $pr['total'] = (int)$pr['total']; }
        unset($pr);
    } catch (\Exception $e) {
        // Silencieux : le camembert sera vide mais le reste du dashboard fonctionnera
        $piliersActes = [];
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
        'dentistes'      => $dentistes,
        'seuil_retard'   => $seuilStr,
        'piliers_actes'  => array_values($piliersActes),
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