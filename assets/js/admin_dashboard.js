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
});
