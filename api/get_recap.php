<?php
/*
 * get_recap.php
 *
 * API pour récupérer les plans de traitement d'un mois donné, avec les statistiques et le comparatif admin.
 * URL : /api/get_recap_plans.php?mois=YYYY-MM&id_utilisateur=ID (id_utilisateur optionnel pour admin)
 *
 * Modif :
 * - Adapté pour varchar accepter (Oui, Non, en Partie) - 14/05/2026
 * - Mise en évidance des week-end et des jours fériés - 15/05/2026
 */
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
apiRequireLogin();

$mois = validateMois($_GET['mois'] ?? date('Y-m'));
if ($mois === null) {
    jsonError(400, 'Mois invalide. Format attendu : YYYY-MM.');
}

[$year, $month] = array_map('intval', explode('-', $mois));
$nbJours = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$jours   = range(1, $nbJours);

// Jours de la semaine en français (2 caractères)
$joursAbbr = ['Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa', 'Di'];
$joursInfo = [];
$joursFerier = []; // Tableau pour les jours fériés

// Jours fériés fixes
$joursFeriers = [
    '01-01' => 'Jour de l\'an',
    '05-01' => 'Fête du Travail',
    '05-08' => 'Fête de la Victoire 1945',
    '07-14' => 'Fête nationale',
    '08-15' => 'Assomption',
    '11-01' => 'Toussaint',
    '11-11' => 'Armistice 1918',
    '12-25' => 'Noël',
];

// Jours fériés mobiles (calculés pour l'année)
$easter = easter_date($year);
$easterDate = new DateTime('@' . $easter);
$easterMonth = (int)$easterDate->format('m');
$easterDay = (int)$easterDate->format('d');

$joursFeiersMobiles = [
    // Lundi de Pâques (jour après Pâques)
    (new DateTime($easterDate->format('Y-m-d')))->modify('+1 day')->format('m-d') => 'Lundi de Pâques',
    // Ascension (39 jours après Pâques)
    (new DateTime($easterDate->format('Y-m-d')))->modify('+39 days')->format('m-d') => 'Ascension',
    // Lundi de Pentecôte (50 jours après Pâques)
    (new DateTime($easterDate->format('Y-m-d')))->modify('+50 days')->format('m-d') => 'Lundi de Pentecôte',
];

$joursFeriers = array_merge($joursFeriers, $joursFeiersMobiles);

foreach ($jours as $jour) {
    $date = new DateTime("$year-$month-$jour");
    $numJour = (int)$date->format('w');
    $numJour = ($numJour === 0) ? 6 : $numJour - 1; // Ajuster pour commencer par lundi
    $joursInfo[$jour] = $joursAbbr[$numJour];
    
    // Vérifier si c'est un jour férié
    $dateStr = $date->format('m-d');
    if (isset($joursFeriers[$dateStr])) {
        $joursFerier[$jour] = true;
    }
}

$user        = getCurrentUser();
$isAdminUser = isAdmin();

// Déterminer le filtre utilisateur
if ($isAdminUser) {
    $filtreId = isset($_GET['id_utilisateur']) ? (int)$_GET['id_utilisateur'] : 0;
} else {
    $filtreId = $user['id'];
}

$db = getDB();

// Toutes les actions avec leur pilier
$actions = $db->query(
    'SELECT a.id_action, a.action, a.formule, p.id_pilier, p.Pilier
     FROM action a
     JOIN pilier p ON a.id_pilier = p.id_pilier
     ORDER BY p.id_pilier ASC, a.ord ASC'
)->fetchAll();

// Récupérer les données du mois
if ($isAdminUser && $filtreId === 0) {
    // Admin : tous les dentistes cumulés
    $stmt = $db->prepare(
        'SELECT DAY(n.date) AS jour, n.id_action, SUM(n.nombre) AS nombre
         FROM nombre n
         WHERE YEAR(n.date) = ? AND MONTH(n.date) = ?
         GROUP BY DAY(n.date), n.id_action'
    );
    $stmt->execute([$year, $month]);
} else {
    // Dentiste ou admin filtré sur un dentiste
    $stmt = $db->prepare(
        'SELECT DAY(n.date) AS jour, n.id_action, SUM(n.nombre) AS nombre
         FROM nombre n
         WHERE n.id_utilisateur = ? AND YEAR(n.date) = ? AND MONTH(n.date) = ?
         GROUP BY DAY(n.date), n.id_action'
    );
    $stmt->execute([$filtreId, $year, $month]);
}

$rawData = $stmt->fetchAll();

// Indexer par [id_action][jour]
$dataMap = [];
foreach ($rawData as $row) {
    $dataMap[(int)$row['id_action']][(int)$row['jour']] = (int)$row['nombre'];
}

// Parser une formule "=1+2+3" → [1, 2, 3]
function parseFormule(?string $f): array {
    if (!$f || $f[0] !== '=') return [];
    preg_match_all('/\d+/', $f, $m);
    return array_map('intval', $m[0]);
}

// Construire la structure par pilier
$piliers         = [];
$currentPilierId = null;
foreach ($actions as $action) {
    $pid = (int)$action['id_pilier'];
    if ($pid !== $currentPilierId) {
        $currentPilierId = $pid;
        $piliers[$pid]   = [
            'id_pilier' => $pid,
            'Pilier'    => $action['Pilier'],
            'actions'   => [],
        ];
    }
    $aid     = (int)$action['id_action'];
    $refs    = parseFormule($action['formule']);   // [] si ligne normale

    $valeurs = [];
    $total   = 0;
    foreach ($jours as $j) {
        if ($refs) {
            // Ligne calculée : somme des actions référencées
            $v = 0;
            foreach ($refs as $refId) {
                $v += $dataMap[$refId][$j] ?? 0;
            }
        } else {
            $v = $dataMap[$aid][$j] ?? 0;
        }
        $valeurs[$j] = $v;
        $total      += $v;
    }
    $piliers[$pid]['actions'][] = [
        'id_action' => $aid,
        'action'    => $action['action'],
        'formule'   => $action['formule'],
        'valeurs'   => $valeurs,
        'total'     => $total,
    ];
}

// Liste des dentistes pour le filtre admin
$dentistes = [];
if ($isAdminUser) {
    $dentistes = $db->query(
        'SELECT id_utilisateur, login FROM utilisateur WHERE id_role = 1 ORDER BY login'
    )->fetchAll();
}

// Périodes de fermeture du cabinet qui chevauchent ce mois
$joursFermeture = [];
try {
    $firstDay = sprintf('%04d-%02d-01',      $year, $month);
    $lastDay  = sprintf('%04d-%02d-%02d',    $year, $month, $nbJours);
    $stmtFerm = $db->prepare(
        'SELECT date_debut, date_fin, motif
         FROM fermeture
         WHERE date_debut <= :lastDay AND date_fin >= :firstDay'
    );
    $stmtFerm->execute([':firstDay' => $firstDay, ':lastDay' => $lastDay]);
    foreach ($stmtFerm->fetchAll() as $ferm) {
        $dDebut = new DateTime($ferm['date_debut']);
        $dFin   = new DateTime($ferm['date_fin']);
        foreach ($jours as $j) {
            $dJour = new DateTime("$year-$month-$j");
            if ($dJour >= $dDebut && $dJour <= $dFin) {
                // On stocke le motif (ou true si aucun motif) pour le tooltip JS
                $joursFermeture[$j] = $ferm['motif'] ?: true;
            }
        }
    }
} catch (\Exception $e) {
    // Table absente (migration non exécutée) — on continue sans fermetures
}

jsonResponse([
    'success'         => true,
    'mois'            => $mois,
    'year'            => $year,
    'month'           => $month,
    'nbJours'         => $nbJours,
    'jours'           => $jours,
    'joursInfo'       => $joursInfo,
    'joursFerier'     => $joursFerier,
    'joursFermeture'  => $joursFermeture,
    'piliers'         => array_values($piliers),
    'dentistes'       => $dentistes,
    'selectedUser'    => $filtreId,
]);