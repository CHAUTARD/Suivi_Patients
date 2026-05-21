<?php
/*
 * get_recap_plans.php
 *
 * API pour récupérer les plans de traitement d'un mois donné, avec les statistiques et le comparatif admin.
 * URL : /api/get_recap_plans.php?mois=YYYY-MM&id_utilisateur=ID (id_utilisateur optionnel pour admin)
 *
 * Modif le : 14/05/2026
 * Adapté pour varchar accepter (Oui, Non, en Partie) - 14/05/2026
 */

require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireLogin();

$mois = validateMois($_GET['mois'] ?? date('Y-m'));
if ($mois === null) {
    jsonError(400, 'Mois invalide. Format attendu : YYYY-MM.');
}

[$year, $month] = array_map('intval', explode('-', $mois));

$user        = getCurrentUser();
$isAdminUser = isAdmin();

if ($isAdminUser) {
    $filtreId = isset($_GET['id_utilisateur']) ? (int)$_GET['id_utilisateur'] : 0;
} else {
    $filtreId = $user['id'];
}

$db = getDB();

// Liste des dentistes pour le filtre admin
$dentistes = [];
if ($isAdminUser) {
    $dentistes = $db->query(
        'SELECT id_utilisateur, login FROM utilisateur WHERE id_role = 1 ORDER BY login'
    )->fetchAll();
}

$params = [$year, $month];

if ($isAdminUser && $filtreId === 0) {
    $sql = 'SELECT p.id_plan, p.date, p.patient, p.montant_devis, p.accepter,  p.date_acceptation, p.montant, 
                   u.id_utilisateur, u.login
            FROM plan_traitement p
            JOIN utilisateur u ON u.id_utilisateur = p.id_utilisateur
            WHERE YEAR(p.date) = ? AND MONTH(p.date) = ?
            ORDER BY p.date ASC, u.login ASC, p.patient ASC';
} else {
    $sql = 'SELECT p.id_plan, p.date, p.patient, p.montant_devis, p.accepter,  p.date_acceptation, p.montant, 
                   u.id_utilisateur, u.login
            FROM plan_traitement p
            JOIN utilisateur u ON u.id_utilisateur = p.id_utilisateur
            WHERE YEAR(p.date) = ? AND MONTH(p.date) = ? AND p.id_utilisateur = ?
            ORDER BY p.date ASC, p.patient ASC';
    $params[] = $filtreId;
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Comparatif admin : ensemble des dentistes du mois
$compare = [];
if ($isAdminUser) {
    $stmtCompare = $db->prepare(
        'SELECT u.id_utilisateur,
                u.login,
                COUNT(p.id_plan) AS total_plans,
                COALESCE(SUM(CASE WHEN p.accepter = \'Oui\' THEN 1 ELSE 0 END), 0) AS total_acceptes,
                COALESCE(SUM(p.montant_devis), 0) AS total_devis,
                COALESCE(SUM(p.montant), 0) AS total_montants
         FROM utilisateur u
         LEFT JOIN plan_traitement p
                ON p.id_utilisateur = u.id_utilisateur
               AND YEAR(p.date) = ?
               AND MONTH(p.date) = ?
         WHERE u.id_role = 1
         GROUP BY u.id_utilisateur, u.login
         ORDER BY u.login ASC'
    );
    $stmtCompare->execute([$year, $month]);

    $compareRows = $stmtCompare->fetchAll();
    foreach ($compareRows as $c) {
        $totalPlansDentiste = (int)$c['total_plans'];
        $totalAcceptesDentiste = (int)$c['total_acceptes'];
        $tauxDentiste = ($totalPlansDentiste > 0)
            ? round(($totalAcceptesDentiste / $totalPlansDentiste) * 100, 1)
            : 0;

        $compare[] = [
            'id_utilisateur'   => (int)$c['id_utilisateur'],
            'login'            => $c['login'],
            'total_plans'      => $totalPlansDentiste,
            'total_acceptes'   => $totalAcceptesDentiste,
            'total_devis'      => (int)$c['total_devis'],
            'total_montants'   => (int)$c['total_montants'],
            'taux_acceptation' => $tauxDentiste,
        ];
    }
}

$plans = [];
$totalPlans = 0;
$totalAcceptes = 0;
$totalDevis = 0;
$totalMontants = 0;

foreach ($rows as $row) {
    $accepter = trim($row['accepter'] ?? 'Non');
    $montantDevis = (int)$row['montant_devis'];
    $montant = (int)$row['montant'];

    $plans[] = [
        'id_plan'       => (int)$row['id_plan'],
        'date'          => $row['date'],
        'patient'       => $row['patient'],
        'montant_devis' => $montantDevis,
        'accepter'      => $accepter,
        'date_acceptation' => $row['date_acceptation'],
        'montant'       => $montant,
        'id_utilisateur'=> (int)$row['id_utilisateur'],
        'login'         => $row['login'],
    ];

    $totalPlans++;
    if ($accepter === 'Oui') {
        $totalAcceptes++;
    }
    $totalDevis += $montantDevis;
    $totalMontants += $montant;
}

$tauxAcceptation = ($totalPlans > 0)
    ? round(($totalAcceptes / $totalPlans) * 100, 1)
    : 0;

jsonResponse([
    'success'         => true,
    'mois'            => $mois,
    'year'            => $year,
    'month'           => $month,
    'selectedUser'    => $filtreId,
    'isAdmin'         => $isAdminUser,
    'dentistes'       => $dentistes,
    'plans'           => $plans,
    'stats'           => [
        'total_plans'       => $totalPlans,
        'total_acceptes'    => $totalAcceptes,
        'total_devis'       => $totalDevis,
        'total_montants'    => $totalMontants,
        'taux_acceptation'  => $tauxAcceptation,
    ],
    'compare'         => $compare,
]);