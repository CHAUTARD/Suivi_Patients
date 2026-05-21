<?php
/**
 * export_recap_pdf.php — Récapitulatif mensuel (page prête à imprimer / enregistrer en PDF)
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
$dentisteName = 'Tous les dentistes (cumulé)';
if ($idDentiste > 0) {
    $stD = $db->prepare('SELECT login FROM utilisateur WHERE id_utilisateur = ? AND id_role = 1');
    $stD->execute([$idDentiste]);
    $row = $stD->fetch();
    if ($row) { $dentisteName = $row['login']; }
    else       { $idDentiste  = 0; }
}

/* ---- Actions et piliers ---- */
$actions = $db->query(
    'SELECT a.id_action, a.action, a.formule, p.id_pilier, p.Pilier
     FROM action a
     JOIN pilier p ON a.id_pilier = p.id_pilier
     ORDER BY p.id_pilier ASC, a.ord ASC'
)->fetchAll();

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

/* ---- Formules ---- */
function _parseF(?string $f): array {
    if (!$f || $f[0] !== '=') { return []; }
    preg_match_all('/\d+/', $f, $m);
    return array_map('intval', $m[0]);
}

/* ---- Structure piliers ---- */
$piliers    = [];
$currentPid = null;
foreach ($actions as $action) {
    $pid = (int)$action['id_pilier'];
    if ($pid !== $currentPid) {
        $currentPid    = $pid;
        $piliers[$pid] = ['Pilier' => $action['Pilier'], 'actions' => []];
    }
    $aid  = (int)$action['id_action'];
    $refs = _parseF($action['formule']);
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
        'isFormule' => !empty($refs),
        'vals'      => $vals,
        'total'     => $tot,
    ];
}

/* ---- Jours fériés ---- */
$feries = ['01-01'=>1,'05-01'=>1,'05-08'=>1,'07-14'=>1,
           '08-15'=>1,'11-01'=>1,'11-11'=>1,'12-25'=>1];
$easter = easter_date($year);
$ed     = new DateTime('@' . $easter);
foreach (['+1 day', '+39 days', '+50 days'] as $off) {
    $feries[(clone $ed)->modify($off)->format('m-d')] = 1;
}

/* ---- Abbréviations jours ---- */
$abbrJour = ['Lu','Ma','Me','Je','Ve','Sa','Di'];

/* ---- Totaux par jour (grand total) ---- */
$dayTotals = [];
foreach ($jours as $j) {
    $s = 0;
    foreach ($piliers as $p) {
        foreach ($p['actions'] as $a) {
            if (!$a['isFormule']) { $s += $a['vals'][$j]; }
        }
    }
    $dayTotals[$j] = $s;
}
$grandTotal = array_sum($dayTotals);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Récap <?= htmlspecialchars($moisLabel) ?> — <?= htmlspecialchars($dentisteName) ?></title>
<style>
@page { size: A4 landscape; margin: 8mm 7mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 7.5pt;
    color: #212529;
}
/* ---- En-tête ---- */
.doc-header { margin-bottom: 4mm; }
.doc-header h1 { font-size: 11pt; font-weight: bold; }
.doc-header .sub { font-size: 8pt; color: #555; margin-top: 1mm; }
/* ---- Boutons screen only ---- */
.screen-only {
    margin-bottom: 8px;
    padding: 6px 0;
    border-bottom: 1px solid #dee2e6;
}
.screen-only button {
    padding: 5px 14px; font-size: 12px; cursor: pointer;
    border: 1px solid #ccc; border-radius: 4px; background: #f8f9fa;
}
.screen-only button.btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
/* ---- Table ---- */
table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}
th, td {
    border: 1px solid #c0c0c0;
    padding: 1mm 1mm;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
}
/* Colonnes fixes */
.c-action { width: 64pt; text-align: left; text-overflow: ellipsis; }
.c-day    { width: 14pt; }
.c-total  { width: 22pt; font-weight: bold; background: #e8f4e8 !important; }
/* Ligne pilier */
tr.tr-pilier th {
    background: #1a6fc4;
    color: #fff;
    font-size: 7.5pt;
    text-align: left;
    padding-left: 2mm;
}
/* Lignes actions */
tr.tr-action:nth-child(odd) td  { background: #f8f9fa; }
tr.tr-action.tr-formula td      { color: #888; font-style: italic; }
/* Colonnes week-end / fériés */
.we     { background: #e9ecef !important; }
.ferie  { background: #cce5ff !important; }
/* Cellule zéro */
.zero   { color: #ccc; }
/* Grand total */
tr.tr-grand td { background: #d1e7dd !important; font-weight: bold; font-size: 8pt; }
/* Pied de page */
.doc-footer {
    margin-top: 3mm;
    font-size: 6pt;
    color: #888;
    display: flex;
    justify-content: space-between;
}
/* Légende */
.legend { margin-top: 2mm; font-size: 6.5pt; color: #555; }
.legend span { display: inline-block; width: 10pt; height: 8pt;
               vertical-align: middle; margin-right: 2pt; border: 1px solid #aaa; }
@media print { .screen-only { display: none; } }
@media screen { body { padding: 10px; } }
</style>
</head>
<body>

<div class="screen-only">
    <button class="btn-primary" onclick="window.print()">🖨️ Imprimer / Enregistrer en PDF</button>
    <button onclick="window.close()" style="margin-left:8px;">✕ Fermer</button>
</div>

<div class="doc-header">
    <h1>📋 Récapitulatif mensuel — <?= htmlspecialchars($moisLabel) ?></h1>
    <div class="sub">
        Dentiste&nbsp;: <strong><?= htmlspecialchars($dentisteName) ?></strong>
        &nbsp;&nbsp;|&nbsp;&nbsp;Généré le <?= date('d/m/Y à H\hi') ?>
        &nbsp;&nbsp;|&nbsp;&nbsp;Total&nbsp;: <strong><?= number_format($grandTotal, 0, ',', '&nbsp;') ?> actes</strong>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th class="c-action">Action</th>
            <?php foreach ($jours as $j):
                $dt      = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $j));
                $dow     = (int)$dt->format('N');          // 1=Lu … 7=Di
                $isFerie = isset($feries[$dt->format('m-d')]);
                $isWe    = ($dow >= 6);
                $cls     = $isFerie ? 'ferie' : ($isWe ? 'we' : '');
                $abbr    = $abbrJour[$dow - 1];
            ?>
            <th class="c-day <?= $cls ?>" title="<?= $dt->format('d/m') ?>">
                <?= $j ?><br><span style="font-size:6pt;font-weight:normal;"><?= $abbr ?></span>
            </th>
            <?php endforeach; ?>
            <th class="c-total">Total</th>
        </tr>
    </thead>
    <tbody>
    <?php
    foreach ($piliers as $pilier):
        /* Calcul total pilier (hors formules) */
        $pilierTotal = 0;
        foreach ($pilier['actions'] as $a) {
            if (!$a['isFormule']) { $pilierTotal += $a['total']; }
        }
        $pct = $grandTotal > 0 ? round($pilierTotal / $grandTotal * 100, 1) : 0;
    ?>
        <tr class="tr-pilier">
            <th class="c-action" colspan="<?= $nbJours + 2 ?>">
                <?= htmlspecialchars($pilier['Pilier']) ?>
                &nbsp;—&nbsp;<?= number_format($pilierTotal, 0, ',', '&nbsp;') ?> actes
                (<?= $pct ?>&nbsp;%)
            </th>
        </tr>
        <?php foreach ($pilier['actions'] as $a):
            $rowCls = 'tr-action' . ($a['isFormule'] ? ' tr-formula' : '');
        ?>
        <tr class="<?= $rowCls ?>">
            <td class="c-action" title="<?= htmlspecialchars($a['action']) ?>">
                <?= htmlspecialchars($a['action']) ?>
            </td>
            <?php foreach ($jours as $j):
                $dt      = new DateTime(sprintf('%04d-%02d-%02d', $year, $month, $j));
                $dow     = (int)$dt->format('N');
                $isFerie = isset($feries[$dt->format('m-d')]);
                $isWe    = ($dow >= 6);
                $v       = $a['vals'][$j];
                $cls     = $isFerie ? 'ferie' : ($isWe ? 'we' : '');
                if ($v == 0) { $cls .= ' zero'; }
            ?>
            <td class="<?= trim($cls) ?>"><?= $v > 0 ? $v : '' ?></td>
            <?php endforeach; ?>
            <td class="c-total"><?= $a['total'] > 0 ? number_format($a['total'], 0, ',', '&nbsp;') : '' ?></td>
        </tr>
        <?php endforeach; ?>
    <?php endforeach; ?>

        <!-- Grand total -->
        <tr class="tr-grand">
            <td class="c-action"><strong>GRAND TOTAL</strong></td>
            <?php foreach ($jours as $j): ?>
            <td><?= $dayTotals[$j] > 0 ? $dayTotals[$j] : '' ?></td>
            <?php endforeach; ?>
            <td class="c-total"><?= number_format($grandTotal, 0, ',', '&nbsp;') ?></td>
        </tr>
    </tbody>
</table>

<div class="legend">
    <span style="background:#cce5ff;"></span> Jour férié &nbsp;
    <span style="background:#e9ecef;"></span> Week-end &nbsp;
    <span style="background:#e8f4e8;"></span> Total &nbsp;
    <em style="color:#888;">Italique = valeur calculée (formule)</em>
</div>

<div class="doc-footer">
    <span>SELARL La Vespalienne — Suivi des Actes<?= defined('APP_VERSION') ? ' v' . APP_VERSION : '' ?></span>
    <span>Page 1</span>
</div>

<script>
// Déclenchement automatique de l'impression à l'ouverture
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
</body>
</html>
