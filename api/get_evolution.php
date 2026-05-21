<?php
/*
 * get_evolution.php
 * Retourne les données d'évolution mensuelle sur N mois (actes, devis, taux d'acceptation)
 * par dentiste + totaux globaux.
 * GET — paramètre optionnel : nb_mois (3 | 6 | 12 | 24, défaut : 12)
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireAdmin();

$db = getDB();

/* ── Nombre de mois ── */
$nbMois = (int)($_GET['nb_mois'] ?? 12);
if (!in_array($nbMois, [3, 6, 12, 24], true)) {
    $nbMois = 12;
}

/* ── Générer la liste des mois (du plus ancien au plus récent) ── */
$moisList = [];
for ($i = $nbMois - 1; $i >= 0; $i--) {
    $dt = new DateTime('first day of this month');
    $dt->modify("-{$i} months");
    $moisList[] = $dt->format('Y-m');
}

// Labels lisibles (ex : "Jan 2026")
$labels = array_map(function ($m) {
    $dt = DateTime::createFromFormat('Y-m', $m);
    // Mois en français abrégé
    $moisFr = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Aoû','Sep','Oct','Nov','Déc'];
    return $moisFr[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y');
}, $moisList);

/* ── Liste des dentistes ── */
$dentistesRows = $db->query(
    "SELECT id_utilisateur, login FROM utilisateur WHERE id_role = 1 ORDER BY login"
)->fetchAll();

/* ── Requête actes par mois et par dentiste ──
   On exclut les lignes qui sont des formules (commençant par '=')
*/
$stmtActes = $db->prepare(
    "SELECT
         DATE_FORMAT(n.date, '%Y-%m') AS mois,
         n.id_utilisateur,
         SUM(
             CASE
                 WHEN n.formule IS NULL OR n.formule NOT LIKE '=%'
                 THEN COALESCE(CAST(n.nombre AS UNSIGNED), 0)
                 ELSE 0
             END
         ) AS total_actes
     FROM nombre n
     WHERE DATE_FORMAT(n.date, '%Y-%m') >= ?
       AND DATE_FORMAT(n.date, '%Y-%m') <= ?
     GROUP BY DATE_FORMAT(n.date, '%Y-%m'), n.id_utilisateur"
);
$stmtActes->execute([$moisList[0], $moisList[$nbMois - 1]]);

// Indexer : $actesData[mois][id_utilisateur] = total_actes
$actesData = [];
foreach ($stmtActes->fetchAll() as $r) {
    $actesData[$r['mois']][(int)$r['id_utilisateur']] = (int)$r['total_actes'];
}

/* ── Requête plans par mois et par dentiste ── */
$stmtPlans = $db->prepare(
    "SELECT
         DATE_FORMAT(p.date, '%Y-%m') AS mois,
         p.id_utilisateur,
         COUNT(*)                                                    AS total_plans,
         SUM(p.accepter = 'Oui')                                     AS nb_acceptes,
         SUM(p.montant_devis)                                        AS total_devis
     FROM plan_traitement p
     WHERE DATE_FORMAT(p.date, '%Y-%m') >= ?
       AND DATE_FORMAT(p.date, '%Y-%m') <= ?
     GROUP BY DATE_FORMAT(p.date, '%Y-%m'), p.id_utilisateur"
);
$stmtPlans->execute([$moisList[0], $moisList[$nbMois - 1]]);

// Indexer : $plansData[mois][id_utilisateur] = {plans, acceptes, devis}
$plansData = [];
foreach ($stmtPlans->fetchAll() as $r) {
    $plansData[$r['mois']][(int)$r['id_utilisateur']] = [
        'plans'    => (int)$r['total_plans'],
        'acceptes' => (int)$r['nb_acceptes'],
        'devis'    => (int)$r['total_devis'],
    ];
}

/* ── Construire les séries par dentiste ── */
$dentistes = [];
foreach ($dentistesRows as $d) {
    $id    = (int)$d['id_utilisateur'];
    $login = $d['login'];

    $actes    = [];
    $plans    = [];
    $acceptes = [];
    $devis    = [];
    $taux     = [];

    foreach ($moisList as $m) {
        $actes[]    = $actesData[$m][$id] ?? 0;
        $nbP        = $plansData[$m][$id]['plans']    ?? 0;
        $nbA        = $plansData[$m][$id]['acceptes'] ?? 0;
        $nbD        = $plansData[$m][$id]['devis']    ?? 0;
        $plans[]    = $nbP;
        $acceptes[] = $nbA;
        $devis[]    = $nbD;
        // null si aucun plan ce mois (évite de tracer un 0% trompeur)
        $taux[]     = ($nbP > 0) ? round($nbA / $nbP * 100, 1) : null;
    }

    $dentistes[] = [
        'id_utilisateur' => $id,
        'login'          => $login,
        'actes'          => $actes,
        'plans'          => $plans,
        'acceptes'       => $acceptes,
        'devis'          => $devis,
        'taux'           => $taux,
    ];
}

/* ── Totaux globaux ── */
$totActes    = [];
$totPlans    = [];
$totAcceptes = [];
$totDevis    = [];
$totTaux     = [];

foreach ($moisList as $m) {
    $sumActes    = array_sum(array_column(array_filter($actesData[$m] ?? []), null));
    $sumPlans    = 0;
    $sumAcceptes = 0;
    $sumDevis    = 0;
    foreach ($dentistesRows as $d) {
        $id           = (int)$d['id_utilisateur'];
        $sumPlans    += $plansData[$m][$id]['plans']    ?? 0;
        $sumAcceptes += $plansData[$m][$id]['acceptes'] ?? 0;
        $sumDevis    += $plansData[$m][$id]['devis']    ?? 0;
    }
    $totActes[]    = $sumActes;
    $totPlans[]    = $sumPlans;
    $totAcceptes[] = $sumAcceptes;
    $totDevis[]    = $sumDevis;
    $totTaux[]     = ($sumPlans > 0) ? round($sumAcceptes / $sumPlans * 100, 1) : null;
}

jsonResponse([
    'success'   => true,
    'nb_mois'   => $nbMois,
    'mois'      => $moisList,
    'labels'    => $labels,
    'dentistes' => $dentistes,
    'totaux'    => [
        'actes'    => $totActes,
        'plans'    => $totPlans,
        'acceptes' => $totAcceptes,
        'devis'    => $totDevis,
        'taux'     => $totTaux,
    ],
]);
