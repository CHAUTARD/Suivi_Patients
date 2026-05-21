<?php
/**
 * export_rapport_pdf.php — Rapport mensuel synthétique (actes + plans)
 * GET  mois=YYYY-MM   (défaut : mois courant)
 */
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

/* ---- Paramètres ---- */
$mois = isset($_GET['mois']) && preg_match('/^\d{4}-\d{2}$/', $_GET['mois'])
    ? $_GET['mois'] : date('Y-m');
[$year, $month] = array_map('intval', explode('-', $mois));

/* ---- Libellé du mois ---- */
$moisNoms  = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',
              5=>'Mai',6=>'Juin',7=>'Juillet',8=>'Août',
              9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];
$moisLabel = ($moisNoms[$month] ?? '') . ' ' . $year;

/* ================================================================
   SECTION 1 — Actes par pilier
   ================================================================ */
$piliersActes = [];
try {
    $st = $db->prepare(
        'SELECT p.id_pilier, p.Pilier,
                COALESCE(SUM(n.nombre), 0) AS total
         FROM pilier p
         LEFT JOIN action a ON a.id_pilier = p.id_pilier
                AND (a.formule IS NULL OR a.formule NOT LIKE \'=%\')
         LEFT JOIN nombre n ON n.id_action = a.id_action
                AND YEAR(n.date) = ? AND MONTH(n.date) = ?
         GROUP BY p.id_pilier, p.Pilier
         ORDER BY p.id_pilier ASC'
    );
    $st->execute([$year, $month]);
    foreach ($st->fetchAll() as $r) {
        $piliersActes[] = ['Pilier' => $r['Pilier'], 'total' => (int)$r['total']];
    }
} catch (\Exception $e) { }

$grandTotalActes = array_sum(array_column($piliersActes, 'total'));

/* ================================================================
   SECTION 2 — Plans par dentiste
   ================================================================ */
$dentistesPlans = [];
try {
    $st = $db->prepare(
        'SELECT u.login,
                COUNT(DISTINCT n.date)           AS jours_saisis,
                COALESCE(pt.total_plans,    0)   AS total_plans,
                COALESCE(pt.total_acceptes, 0)   AS total_acceptes,
                COALESCE(pt.total_devis,    0)   AS total_devis,
                COALESCE(pt.total_montant,  0)   AS total_montant
         FROM utilisateur u
         LEFT JOIN nombre n
                ON n.id_utilisateur = u.id_utilisateur
               AND YEAR(n.date) = ? AND MONTH(n.date) = ?
         LEFT JOIN (
             SELECT id_utilisateur,
                    COUNT(*) AS total_plans,
                    SUM(CASE WHEN accepter = \'Oui\' THEN 1 ELSE 0 END) AS total_acceptes,
                    COALESCE(SUM(montant_devis), 0) AS total_devis,
                    COALESCE(SUM(montant),       0) AS total_montant
             FROM plan_traitement
             WHERE YEAR(date) = ? AND MONTH(date) = ?
             GROUP BY id_utilisateur
         ) pt ON pt.id_utilisateur = u.id_utilisateur
         WHERE u.id_role = 1
         GROUP BY u.id_utilisateur, u.login
         ORDER BY u.login ASC'
    );
    $st->execute([$year, $month, $year, $month]);
    foreach ($st->fetchAll() as $r) {
        $total = (int)$r['total_plans'];
        $acc   = (int)$r['total_acceptes'];
        $dentistesPlans[] = [
            'login'          => $r['login'],
            'jours_saisis'   => (int)$r['jours_saisis'],
            'total_plans'    => $total,
            'total_acceptes' => $acc,
            'taux'           => $total > 0 ? round($acc / $total * 100, 1) : 0,
            'total_devis'    => (int)$r['total_devis'],
            'total_montant'  => (int)$r['total_montant'],
        ];
    }
} catch (\Exception $e) { }

/* Totaux globaux plans */
$gtPlans    = array_sum(array_column($dentistesPlans, 'total_plans'));
$gtAcceptes = array_sum(array_column($dentistesPlans, 'total_acceptes'));
$gtTaux     = $gtPlans > 0 ? round($gtAcceptes / $gtPlans * 100, 1) : 0;
$gtDevis    = array_sum(array_column($dentistesPlans, 'total_devis'));
$gtMontant  = array_sum(array_column($dentistesPlans, 'total_montant'));
$gtJours    = array_sum(array_column($dentistesPlans, 'jours_saisis'));

/* ---- Helpers ---- */
function fmt_nb(int $v): string { return number_format($v, 0, ',', "\xc2\xa0"); }
function taux_class(float $t): string {
    return $t >= 80 ? '#198754' : ($t >= 50 ? '#fd7e14' : '#dc3545');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Rapport mensuel <?= htmlspecialchars($moisLabel) ?></title>
<style>
@page { size: A4 portrait; margin: 15mm 12mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #212529; }

/* ---- Écran ---- */
.screen-only { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #dee2e6; }
.screen-only button {
    padding: 5px 14px; font-size: 12px; cursor: pointer;
    border: 1px solid #ccc; border-radius: 4px; background: #f8f9fa;
}
.screen-only button.btn-primary { background:#0d6efd; color:#fff; border-color:#0d6efd; }
@media print { .screen-only { display:none; } }
@media screen { body { padding: 10px; } }

/* ---- En-tête ---- */
.doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2pt solid #1a6fc4;
    padding-bottom: 4mm;
    margin-bottom: 6mm;
}
.doc-header h1 { font-size: 13pt; font-weight: bold; color: #1a6fc4; }
.doc-header .meta { font-size: 8pt; color: #555; text-align: right; }
.doc-header .meta strong { display: block; font-size: 10pt; color: #212529; }

/* ---- Sections ---- */
.section { margin-bottom: 8mm; }
.section-title {
    font-size: 10pt;
    font-weight: bold;
    color: #fff;
    background: #1a6fc4;
    padding: 2mm 3mm;
    margin-bottom: 3mm;
    border-radius: 2px;
}

/* ---- KPIs ---- */
.kpi-row { display: flex; gap: 4mm; margin-bottom: 4mm; }
.kpi {
    flex: 1;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 3mm 3mm;
    text-align: center;
}
.kpi .val { font-size: 14pt; font-weight: bold; }
.kpi .lbl { font-size: 7pt; color: #6c757d; margin-top: 1mm; }

/* ---- Tables ---- */
table { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
th, td { border: 1px solid #c0c0c0; padding: 1.5mm 2mm; font-size: 8.5pt; }
thead th { background: #343a40; color: #fff; text-align: center; }
thead th.left { text-align: left; }
tr:nth-child(even) td { background: #f8f9fa; }
td.num { text-align: right; }
td.ctr { text-align: center; }
tr.tr-total td { background: #e8f4e8 !important; font-weight: bold; }

/* ---- Barre pilier ---- */
.bar-outer { background: #e9ecef; border-radius: 3px; height: 8pt; width: 100%; }
.bar-inner { background: #1a6fc4; border-radius: 3px; height: 8pt; }

/* ---- Pied de page ---- */
.doc-footer {
    position: fixed;
    bottom: 8mm;
    left: 12mm;
    right: 12mm;
    border-top: 1px solid #dee2e6;
    padding-top: 2mm;
    font-size: 6.5pt;
    color: #888;
    display: flex;
    justify-content: space-between;
}
</style>
</head>
<body>

<div class="screen-only">
    <button class="btn-primary" onclick="window.print()">🖨️ Imprimer / Enregistrer en PDF</button>
    <button onclick="window.close()" style="margin-left:8px;">✕ Fermer</button>
</div>

<!-- En-tête -->
<div class="doc-header">
    <div>
        <h1>📊 Rapport mensuel — <?= htmlspecialchars($moisLabel) ?></h1>
        <div style="font-size:8pt;color:#555;margin-top:1mm;">SELARL La Vespalienne — Suivi des Actes</div>
    </div>
    <div class="meta">
        Généré le <strong><?= date('d/m/Y à H\hi') ?></strong>
    </div>
</div>

<!-- KPIs globaux -->
<div class="kpi-row">
    <div class="kpi">
        <div class="val" style="color:#1a6fc4;"><?= fmt_nb($grandTotalActes) ?></div>
        <div class="lbl">Actes saisis</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:#0d6efd;"><?= fmt_nb($gtJours) ?></div>
        <div class="lbl">Jours de saisie</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:#198754;"><?= fmt_nb($gtPlans) ?></div>
        <div class="lbl">Plans de traitement</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:<?= taux_class($gtTaux) ?>;"><?= $gtTaux ?>&nbsp;%</div>
        <div class="lbl">Taux d'acceptation global</div>
    </div>
    <div class="kpi">
        <div class="val" style="color:#fd7e14;font-size:11pt;"><?= fmt_nb($gtDevis) ?>&nbsp;€</div>
        <div class="lbl">Montant devis</div>
    </div>
</div>

<!-- Section 1 : Actes par pilier -->
<div class="section">
    <div class="section-title"><i>▪</i> Répartition des actes par pilier</div>
    <?php if (empty($piliersActes) || $grandTotalActes === 0): ?>
    <p style="color:#888;font-size:8pt;">Aucun acte saisi ce mois.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th class="left" style="width:40%;">Pilier</th>
                <th style="width:20%;">Total actes</th>
                <th style="width:12%;">%</th>
                <th style="width:28%;">Répartition</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($piliersActes as $p):
            $pct = $grandTotalActes > 0 ? round($p['total'] / $grandTotalActes * 100, 1) : 0;
        ?>
            <tr>
                <td><?= htmlspecialchars($p['Pilier']) ?></td>
                <td class="num"><?= fmt_nb($p['total']) ?></td>
                <td class="ctr"><?= $pct ?>&nbsp;%</td>
                <td style="padding:2mm 3mm;">
                    <div class="bar-outer">
                        <div class="bar-inner" style="width:<?= $pct ?>%;"></div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
            <tr class="tr-total">
                <td><strong>TOTAL</strong></td>
                <td class="num"><?= fmt_nb($grandTotalActes) ?></td>
                <td class="ctr">100&nbsp;%</td>
                <td></td>
            </tr>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Section 2 : Plans par dentiste -->
<div class="section">
    <div class="section-title"><i>▪</i> Plans de traitement par dentiste</div>
    <?php if (empty($dentistesPlans)): ?>
    <p style="color:#888;font-size:8pt;">Aucun dentiste trouvé.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th class="left">Dentiste</th>
                <th>Jours saisis</th>
                <th>Plans</th>
                <th>Acceptés</th>
                <th>Taux</th>
                <th>Montant devis</th>
                <th>Montant accepté</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($dentistesPlans as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['login']) ?></td>
                <td class="ctr"><?= $r['jours_saisis'] ?></td>
                <td class="num"><?= fmt_nb($r['total_plans']) ?></td>
                <td class="num"><?= fmt_nb($r['total_acceptes']) ?></td>
                <td class="ctr" style="font-weight:bold;color:<?= taux_class($r['taux']) ?>;">
                    <?= $r['taux'] ?>&nbsp;%
                </td>
                <td class="num"><?= fmt_nb($r['total_devis']) ?>&nbsp;€</td>
                <td class="num"><?= fmt_nb($r['total_montant']) ?>&nbsp;€</td>
            </tr>
        <?php endforeach; ?>
            <tr class="tr-total">
                <td><strong>TOTAL</strong></td>
                <td class="ctr"><?= fmt_nb($gtJours) ?></td>
                <td class="num"><?= fmt_nb($gtPlans) ?></td>
                <td class="num"><?= fmt_nb($gtAcceptes) ?></td>
                <td class="ctr" style="font-weight:bold;color:<?= taux_class($gtTaux) ?>;">
                    <?= $gtTaux ?>&nbsp;%
                </td>
                <td class="num"><?= fmt_nb($gtDevis) ?>&nbsp;€</td>
                <td class="num"><?= fmt_nb($gtMontant) ?>&nbsp;€</td>
            </tr>
        </tbody>
    </table>
    <p style="font-size:7pt;color:#888;margin-top:2mm;">
        Taux d'acceptation&nbsp;:
        <span style="color:#198754;font-weight:bold;">■</span> ≥ 80&nbsp;%
        &nbsp; <span style="color:#fd7e14;font-weight:bold;">■</span> 50–79&nbsp;%
        &nbsp; <span style="color:#dc3545;font-weight:bold;">■</span> &lt; 50&nbsp;%
    </p>
    <?php endif; ?>
</div>

<div class="doc-footer">
    <span>SELARL La Vespalienne — Suivi des Actes<?= defined('APP_VERSION') ? ' v' . APP_VERSION : '' ?></span>
    <span>Rapport confidentiel — usage interne</span>
</div>

<script>
window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
</body>
</html>
