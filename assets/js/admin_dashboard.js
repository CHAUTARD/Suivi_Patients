/**
 * admin_dashboard.js — Camembert répartition par pilier (admin/index.php)
 * SELARL La Vespalienne — Suivi des Actes
 */
'use strict';

var PALETTE = [
    '#1a6fc4', // bleu primaire
    '#198754', // vert
    '#ffc107', // jaune
    '#dc3545', // rouge
    '#0dcaf0', // cyan
    '#6f42c1', // violet
    '#fd7e14', // orange
    '#20c997', // teal
    '#6c757d', // gris
];

$(function () {

    $.ajax({
        url:      window.SITE_ROOT + '/api/get_dashboard.php',
        method:   'GET',
        dataType: 'json'
    }).done(function (d) {
        if (d.success) {
            $('#chartMoisLabel').text(d.mois_label || d.mois);
            renderChart(d.piliers_actes || []);
            renderRetardAlert(d.dentistes || [], d.seuil_retard || '');
        } else {
            $('#chartBody').html(
                '<p class="text-danger small mb-0">' + (d.error || 'Erreur de chargement.') + '</p>'
            );
        }
    }).fail(function () {
        $('#chartBody').html('<p class="text-danger small mb-0">Erreur réseau.</p>');
    });

    /* ================================================================
       Rendu du camembert
       ================================================================ */
    function renderChart(piliersActes) {
        var data = piliersActes.filter(function (p) { return p.total > 0; });

        if (data.length === 0) {
            $('#chartBody').html(
                '<div class="text-center text-muted py-3">'
                + '<i class="bi bi-bar-chart-line display-6 d-block mb-2 opacity-50"></i>'
                + 'Aucun acte saisi ce mois.'
                + '</div>'
            );
            return;
        }

        var labels = data.map(function (p) { return p.Pilier; });
        var values = data.map(function (p) { return p.total; });
        var total  = values.reduce(function (a, b) { return a + b; }, 0);
        var colors = data.map(function (_, i) { return PALETTE[i % PALETTE.length]; });

        /* Texte central : total + "actes" */
        var centerTextPlugin = {
            id: 'centerText',
            afterDraw: function (chart) {
                var ctx  = chart.ctx;
                var area = chart.chartArea;
                var cx   = area.left + (area.right  - area.left) / 2;
                var cy   = area.top  + (area.bottom - area.top)  / 2;
                ctx.save();
                ctx.textAlign    = 'center';
                ctx.textBaseline = 'middle';
                ctx.font         = 'bold 20px system-ui, sans-serif';
                ctx.fillStyle    = '#212529';
                ctx.fillText(total.toLocaleString('fr-FR'), cx, cy - 10);
                ctx.font      = '12px system-ui, sans-serif';
                ctx.fillStyle = '#6c757d';
                ctx.fillText('actes', cx, cy + 12);
                ctx.restore();
            }
        };

        /* Remplace le spinner par le canvas */
        $('#chartBody').html('<div style="width:100%; max-width:260px;"><canvas id="chartPiliers"></canvas></div>');

        new Chart(document.getElementById('chartPiliers'), {
            type: 'doughnut',
            data: {
                labels:   labels,
                datasets: [{
                    data:            values,
                    backgroundColor: colors,
                    borderColor:     '#ffffff',
                    borderWidth:     3,
                    hoverOffset:     6
                }]
            },
            options: {
                responsive:          true,
                maintainAspectRatio: true,
                aspectRatio:         1.4,
                cutout:              '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 13, padding: 14, font: { size: 12 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var pct = total > 0
                                    ? Math.round(ctx.parsed / total * 100) : 0;
                                return ' ' + ctx.label
                                    + ' : ' + ctx.parsed.toLocaleString('fr-FR')
                                    + ' (' + pct + ' %)';
                            }
                        }
                    }
                }
            },
            plugins: [centerTextPlugin]
        });
    }

    /* ================================================================
       Alerte retard de saisie
       ================================================================ */
    function renderRetardAlert(dentistes, seuilStr) {
        var enRetard = dentistes.filter(function (r) { return r.en_retard; });
        if (enRetard.length === 0) { return; }

        var noms = enRetard.map(function (r) {
            return '<strong>' + escH(r.login) + '</strong>';
        }).join(', ');

        var seuilFr = '';
        if (seuilStr && seuilStr.length >= 10) {
            seuilFr = ' (depuis le '
                + seuilStr.substring(8, 10) + '/'
                + seuilStr.substring(5, 7) + '/'
                + seuilStr.substring(0, 4) + ')';
        }

        var plural = enRetard.length > 1 ? 's' : '';
        $('#alertRetard')
            .html(
                '<div class="alert alert-warning alert-dismissible d-flex align-items-start gap-2 mb-0 shadow-sm">'
                + '<i class="bi bi-exclamation-triangle-fill flex-shrink-0 fs-5 mt-1"></i>'
                + '<div>'
                + '<span class="fw-semibold">'
                + enRetard.length + ' dentiste' + plural
                + ' sans saisie depuis plus de 5&nbsp;jours ouvrables' + seuilFr + '&nbsp;:</span> '
                + noms
                + '</div>'
                + '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>'
                + '</div>'
            )
            .show();
    }

    /* ================================================================
       Export & Documents — mise à jour des URLs des boutons
       ================================================================ */
    function updateExportUrls() {
        var recapMois     = $('#pdfRecapMois').val()     || '';
        var recapDent     = $('#pdfRecapDentiste').val() || '0';
        var rapportMois   = $('#pdfRapportMois').val()   || '';
        var csvMois       = $('#csvMois').val()          || '';
        var csvDent       = $('#csvDentiste').val()      || '0';

        var base = window.SITE_ROOT + '/admin/';

        $('#btnPdfRecap').attr('href',
            base + 'export_recap_pdf.php?mois=' + recapMois
            + (recapDent !== '0' ? '&dentiste=' + recapDent : '')
        );
        $('#btnPdfRapport').attr('href',
            base + 'export_rapport_pdf.php?mois=' + rapportMois
        );
        $('#btnCsv').attr('href',
            base + 'export_recap_csv.php?mois=' + csvMois
            + (csvDent !== '0' ? '&dentiste=' + csvDent : '')
        );
    }

    // Initialisation + écoute des changements
    updateExportUrls();
    $('#pdfRecapMois, #pdfRecapDentiste, #pdfRapportMois, #csvMois, #csvDentiste')
        .on('change', updateExportUrls);

    /* ================================================================
       Relance automatique des plans non acceptés
       ================================================================ */
    loadRelance();

    $('#btnSaveDelai').on('click', function () {
        var delai = parseInt($('#delaiRelance').val(), 10);
        if (!delai || delai < 1 || delai > 365) {
            $('#relanceBody').html(
                '<p class="text-danger small p-3 mb-0">'
                + '<i class="bi bi-exclamation-circle me-1"></i>'
                + 'Le délai doit être compris entre 1 et 365 jours.</p>'
            );
            return;
        }

        var $btn = $(this).prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement…');

        $.ajax({
            url:         window.SITE_ROOT + '/api/save_parametre.php',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify({ cle: 'delai_relance', valeur: delai }),
            dataType:    'json'
        }).done(function (d) {
            if (d.success) {
                loadRelance();
            } else {
                $('#relanceBody').html(
                    '<p class="text-danger small p-3 mb-0">'
                    + '<i class="bi bi-exclamation-circle me-1"></i>'
                    + escH(d.error || 'Erreur de sauvegarde.') + '</p>'
                );
            }
        }).fail(function () {
            $('#relanceBody').html(
                '<p class="text-danger small p-3 mb-0">Erreur réseau.</p>'
            );
        }).always(function () {
            $btn.prop('disabled', false)
                .html('<i class="bi bi-check-lg me-1"></i>Appliquer');
        });
    });

    function loadRelance() {
        $('#relanceBody').html(
            '<div class="text-center py-3 text-muted">'
            + '<div class="spinner-border spinner-border-sm" role="status"></div>'
            + '</div>'
        );

        $.ajax({
            url:      window.SITE_ROOT + '/api/get_plans_relance.php',
            method:   'GET',
            dataType: 'json'
        }).done(function (d) {
            if (d.success) {
                $('#delaiRelance').val(d.delai);
                renderRelance(d.plans || [], d.delai);
            } else {
                $('#relanceBadge').hide();
                $('#relanceBody').html(
                    '<p class="text-danger small p-3 mb-0">'
                    + escH(d.error || 'Erreur de chargement.') + '</p>'
                );
            }
        }).fail(function () {
            $('#relanceBadge').hide();
            $('#relanceBody').html(
                '<p class="text-danger small p-3 mb-0">Erreur réseau.</p>'
            );
        });
    }

    function renderRelance(plans, delai) {
        /* ---- Aucun plan en retard ---- */
        if (plans.length === 0) {
            $('#relanceBadge').hide();
            $('#relanceBody').html(
                '<div class="d-flex align-items-center gap-2 px-3 py-3 text-success">'
                + '<i class="bi bi-check-circle-fill fs-5 flex-shrink-0"></i>'
                + '<span class="small">Aucun plan en attente de relance depuis plus de '
                + delai + '&nbsp;jour(s).</span>'
                + '</div>'
            );
            return;
        }

        /* ---- Badge count ---- */
        $('#relanceBadge').text(plans.length).show();

        /* ---- Séparation Non / En partie ---- */
        var nbNon    = plans.filter(function (p) { return p.accepter === 'Non'; }).length;
        var nbPartie = plans.length - nbNon;

        var resume = '<div class="px-3 pt-2 pb-1 small text-muted border-bottom">'
            + '<i class="bi bi-info-circle me-1"></i>'
            + plans.length + ' plan(s) sans acceptation depuis plus de '
            + delai + '&nbsp;jour(s)';
        if (nbNon > 0 && nbPartie > 0) {
            resume += ' &nbsp;—&nbsp; '
                + '<span class="badge bg-danger me-1">' + nbNon + ' Non</span>'
                + '<span class="badge bg-warning text-dark">' + nbPartie + ' En partie</span>';
        }
        resume += '</div>';

        /* ---- Tableau ---- */
        var html = resume
            + '<div class="table-responsive" style="max-height:360px;">'
            + '<table class="table table-sm table-bordered table-hover mb-0">'
            + '<thead class="table-warning" style="position:sticky;top:0;z-index:1;">'
            + '<tr>'
            + '<th>Date plan</th>'
            + '<th>Patient</th>'
            + '<th>Dentiste</th>'
            + '<th class="text-end">Devis</th>'
            + '<th class="text-center">Statut</th>'
            + '<th class="text-center">Jours écoulés</th>'
            + '<th class="text-center" style="width:38px;"></th>'
            + '</tr>'
            + '</thead><tbody>';

        plans.forEach(function (p) {
            var j       = p.jours_ecoules;
            /* Rouge si on dépasse le double du délai, orange sinon */
            var jBadge  = j >= delai * 2
                ? '<span class="badge bg-danger">' + j + '</span>'
                : '<span class="badge bg-warning text-dark">' + j + '</span>';
            var statut  = p.accepter === 'en Partie'
                ? '<span class="badge bg-warning text-dark">En partie</span>'
                : '<span class="badge bg-danger">Non</span>';
            var mois    = (p.date || '').substring(0, 7);
            var lnk     = window.SITE_ROOT + '/recap_plans.php?mois=' + mois;

            html += '<tr>'
                  + '<td class="text-nowrap">' + frDate(p.date) + '</td>'
                  + '<td class="fw-semibold">'  + escH(p.patient || '') + '</td>'
                  + '<td class="text-muted small">' + escH(p.login || '') + '</td>'
                  + '<td class="text-end text-nowrap">' + fmt(p.montant_devis) + '&nbsp;€</td>'
                  + '<td class="text-center">'  + statut   + '</td>'
                  + '<td class="text-center">'  + jBadge   + '</td>'
                  + '<td class="text-center">'
                  +   '<a href="' + lnk + '" class="btn btn-outline-secondary btn-sm py-0 px-1"'
                  +      ' title="Voir les plans du mois">'
                  +     '<i class="bi bi-box-arrow-up-right" style="font-size:.75rem;"></i>'
                  +   '</a>'
                  + '</td>'
                  + '</tr>';
        });

        html += '</tbody></table></div>';
        $('#relanceBody').html(html);
    }

    /* ---- Helpers ---- */
    function frDate(iso) {
        if (!iso || iso.length < 10) { return ''; }
        return iso.substring(8, 10) + '/' + iso.substring(5, 7) + '/' + iso.substring(0, 4);
    }
    function fmt(v) { return (parseInt(v, 10) || 0).toLocaleString('fr-FR'); }
    function escH(s) { return $('<div>').text(s || '').html(); }
});
