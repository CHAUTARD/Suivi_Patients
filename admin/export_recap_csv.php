<?php
/**
 * export_recap_csv.php — Export CSV enrichi (actes par jour + totaux + pourcentages)
 * GET  mois=YYYY-MM   (défaut : mois courant)
 *      dentiste=ID    (optionnel ; 0 ou absent = tous les dentistes cumulés)
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

/* ---- Paramètres ---- */
$mois = isset($_GET['mois']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mois'])
    ? $_GET['mois'] : date('Y-m');
[$year, $month] = array_map('intval', explode('-', $mois));
$nbJours = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$jours   = range(1, $nbJours);

$idDentiste = isset($_GET['dentiste']) && ctype_digit((string)$_GET['dentiste'])
    ? (int)$_GET['dentiste'] : 0;

/* ---- Libellé du mois ---- */
$moisNoms  = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',
              5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',
              9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$moisLabel = ($moisNoms[$month] ?? '') . ' ' . $year;

/* ---- Nom du dentiste ---- */
$dentisteName = 'Tous les dentistes (cumule)';
if ($idDentiste > 0) {
    $stD = $db->prepare('SELECT login FROM utilisateur WHERE id_utilisateur = ? AND id_role = 1');
    $stD->execute([$idDentiste]);
    $row = $stD->fetch();
    if ($row) { $dentisteName = $row['login']; }
    else       { $idDentiste  = 0; }
}

/* ---- Données nombre ---- */
if ($idDentiste === 0) {
    $stmt = $db->prepare(
        'SELECT DAY(n.date) AS jour, n.id_action, SUM(n.nombre) AS nombre
         FROM nombre n WHERE YEAR(n.date) = ? AND MONTH(n.date) = ?
         GROUP BY DAY(n.date), n.id_action'
    );
    $stmt->execute([$year, $month]);
} else {
    $stmt = $db->prepare(
        'SELECT DAY(n.date) AS jour, n.id_action, SUM(n.nombre) AS nombre
         FROM nombre n WHERE n.id_utilisateur = ? AND YEAR(n.date) = ? AND MONTH(n.date) = ?
         GROUP BY DAY(n.date), n.id_action'
    );
    $stmt->execute([$idDentiste, $year, $month]);
}
$dataMap = [];
foreach ($stmt->fetchAll() as $r) {
    $dataMap[(int)$r['id_action']][(int)$r['jour']] = (int)$r['nombre'];
}

/* ---- Actions ---- */
$actions = $db->query(
    'SELECT a.id_action, a.action, a.formule, p.id_pilier, p.Pilier
     FROM action a
     JOIN pilier p ON a.id_pilier = p.id_pilier
     ORDER BY p.id_pilier ASC, a.ord ASC'
)->fetchAll();

function _parseFormCSV(?string $f): array {
    if (!$f || $f[0] !== '=') { return []; }
    preg_match_all('/\d+/', $f, $m);
    return array_map('intval', $m[0]);
}

/* ---- Construction de la structure piliers ---- */
$piliers    = [];
$currentPid = null;
foreach ($actions as $action) {
    $pid = (int)$action['id_pilier'];
    if ($pid !== $currentPid) {
        $currentPid    = $pid;
        $piliers[$pid] = ['Pilier' => $action['Pilier'], 'actions' => []];
    }
    $aid  = (int)$action['id_action'];
    $refs = _parseFormCSV($action['formule']);
    $vals = [];
    $tot  = 0;
    foreach ($jours as $j) {
        if ($refs) {
            $v = 0;
            foreach ($refs as $rid) { $v += $dataMap[$rid][$j] ?? 0; }
        } else {
            $v = $dataMap[$aid][$j] ?? 0;
        }
        $vals[$j] = $v;
        $tot     += $v;
    }
    $piliers[$pid]['actions'][] = [
        'action'    => $action['action'],
        'formule'   => $action['formule'],
        'isFormule' => !empty($refs),
        'vals'      => $vals,
        'total'     => $tot,
    ];
}

/* ---- Grand total ---- */
$grandTotal = 0;
foreach ($piliers as $pilier) {
    foreach ($pilier['actions'] as $a) {
        if (!$a['isFormule']) { $grandTotal += $a['total']; }
    }
}

/* ---- Nom du fichier ---- */
$filename = 'recap_' . $mois . '_' . preg_replace('/[^a-z0-9]/i', '_', $dentisteName) . '.csv';

/* ---- Headers HTTP ---- */
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store');
header('Pragma: no-cache');

/* ---- Helper CSV ---- */
function csvRow(array $cols, bool $trim = false): string {
    $out = [];
    foreach ($cols as $c) {
        $c    = (string)$c;
        $c    = str_replace('"', '""', $c);
        $out[] = '"' . $c . '"';
    }
    return implode(';', $out) . "\r\n";
}

/* ---- Sortie ---- */
// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

// Métadonnées
echo csvRow(['Recapitulatif des actes', $moisLabel]);
echo csvRow(['Dentiste', $dentisteName]);
echo csvRow(['Genere le', date('d/m/Y H:i')]);
echo csvRow([]);

// En-tête colonnes : Pilier ; Action ; Type ; J01 ; J02 … J31 ; Total ; % Total
$headerDays = [];
foreach ($jours as $j) {
    $headerDays[] = 'J' . str_pad((string)$j, 2, '0', STR_PAD_LEFT);
}
echo csvRow(array_merge(
    ['Pilier', 'Action', 'Type'],
    $headerDays,
    ['Total', '% Total']
));

/* ---- Données ---- */
foreach ($piliers as $pilier) {
    /* Calcul total pilier */
    $pilierTotal = 0;
    foreach ($pilier['actions'] as $a) {
        if (!$a['isFormule']) { $pilierTotal += $a['total']; }
    }
    $pilierPct = $grandTotal > 0 ? round($pilierTotal / $grandTotal * 100, 2) : 0;

    foreach ($pilier['actions'] as $a) {
        $type  = $a['isFormule'] ? 'Calcul' : 'Saisie';
        $pct   = !$a['isFormule'] && $grandTotal > 0
            ? round($a['total'] / $grandTotal * 100, 2) : '';
        $dayVals = [];
        foreach ($jours as $j) {
            $dayVals[] = $a['vals'][$j] > 0 ? $a['vals'][$j] : '';
        }
        echo csvRow(array_merge(
            [$pilier['Pilier'], $a['action'], $type],
            $dayVals,
            [$a['total'] > 0 ? $a['total'] : '', $pct !== '' ? $pct . '%' : '']
        ));
    }

    /* Sous-total pilier */
    $subDays = [];
    foreach ($jours as $j) {
        $s = 0;
        foreach ($pilier['actions'] as $a) {
            if (!$a['isFormule']) { $s += $a['vals'][$j]; }
        }
        $subDays[] = $s > 0 ? $s : '';
    }
    echo csvRow(array_merge(
        ['', 'TOTAL ' . strtoupper($pilier['Pilier']), ''],
        $subDays,
        [$pilierTotal, $pilierPct . '%']
    ));

    // Ligne vide entre piliers
    echo csvRow([]);
}

/* ---- Grand total ---- */
$gtDays = [];
foreach ($jours as $j) {
    $s = 0;
    foreach ($piliers as $pilier) {
        foreach ($pilier['actions'] as $a) {
            if (!$a['isFormule']) { $s += $a['vals'][$j]; }
        }
    }
    $gtDays[] = $s > 0 ? $s : '';
}
echo csvRow(array_merge(
    ['', 'GRAND TOTAL', ''],
    $gtDays,
    [$grandTotal, '100%']
));
