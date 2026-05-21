/**
 * evolution.js — Courbes d'évolution mensuelle
 * SELARL La Vespalienne — Suivi des Actes
 * Trois charts Chart.js : actes (barres), devis (lignes), taux d'acceptation (lignes)
 */
'use strict';

/* ── Palette de couleurs ── */
var PALETTE_EVO = [
    '#1a6fc4', // bleu
    '#198754', // vert
    '#ffc107', // jaune
    '#dc3545', // rouge
    '#0dcaf0', // cyan
    '#6f42c1', // violet
    '#fd7e14', // orange
    '#20c997', // teal
    '#6c757d', // gris
];

/* ── Instances Chart.js actives ── */
var _charts = {};

/* ── Utilitaires ── */
function hex2rgba(hex, alpha) {
    var r = parseInt(hex.slice(1, 3), 16);
    var g = parseInt(hex.slice(3, 5), 16);
    var b = parseInt(hex.slice(5, 7), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
}

function destroyChart(id) {
    if (_charts[id]) {
        _charts[id].destroy();
        delete _charts[id];
    }
}

function showChartError(id, msg) {
    var wrap = document.getElementById(id + 'Wrap');
    if (wrap) {
        wrap.innerHTML =
            '<div class="text-center text-danger py-4 small">'
            + '<i class="bi bi-exclamation-circle me-1"></i>' + msg
            + '</div>';
    }
}

function showChartSpinner(id) {
    var wrap = document.getElementById(id + 'Wrap');
    if (wrap) {
        wrap.innerHTML =
            '<div class="text-center py-4 text-muted">'
            + '<div class="spinner-border spinner-border-sm me-2" role="status"></div>'
            + 'Chargement…'
            + '</div>';
    }
}

/* Restaure le canvas dans son wrapper avant de tracer le chart */
function restoreCanvas(id) {
    var wrap = document.getElementById(id + 'Wrap');
    if (wrap) {
        wrap.innerHTML = '<canvas id="' + id + '"></canvas>';
    }
    return document.getElementById(id);
}

/* ── Chargement principal ── */
function loadEvolution(nbMois) {
    /* Affiche les spinners (détruit et remplace le contenu du wrap) */
    ['chartActes', 'chartDevis', 'chartTaux'].forEach(function (id) {
        destroyChart(id);
        showChartSpinner(id);
    });

    $.ajax({
        url:      window.SITE_ROOT + '/api/get_evolution.php',
        method:   'GET',
        data:     { nb_mois: nbMois },
        dataType: 'json'
    }).done(function (d) {
        if (!d.success) {
            ['chartActes', 'chartDevis', 'chartTaux'].forEach(function (id) {
                showChartError(id, d.error || 'Erreur de chargement.');
            });
            return;
        }
        buildChartActes(d);
        buildChartDevis(d);
        buildChartTaux(d);
    }).fail(function () {
        ['chartActes', 'chartDevis', 'chartTaux'].forEach(function (id) {
            showChartError(id, 'Erreur réseau.');
        });
    });
}

/* ================================================================
   Chart 1 — Actes (barres empilées par dentiste)
   ================================================================ */
function buildChartActes(d) {
    destroyChart('chartActes');

    var datasets = d.dentistes.map(function (dent, i) {
        var color = PALETTE_EVO[i % PALETTE_EVO.length];
        return {
            label:           dent.login,
            data:            dent.actes,
            backgroundColor: hex2rgba(color, 0.80),
            borderColor:     color,
            borderWidth:     1,
            stack:           'total'
        };
    });

    /* Ligne total (invisible dans la pile, non empilée) */
    datasets.push({
        label:           'Total',
        data:            d.totaux.actes,
        type:            'line',
        borderColor:     '#212529',
        backgroundColor: 'transparent',
        borderWidth:     2,
        borderDash:      [5, 3],
        pointRadius:     3,
        pointHoverRadius: 5,
        tension:         0.3,
        stack:           undefined,
        order:           -1
    });

    var ctx = restoreCanvas('chartActes');
    if (!ctx) { return; }

    _charts['chartActes'] = new Chart(ctx, {
        type: 'bar',
        data: { labels: d.labels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { stacked: true, grid: { display: false }, ticks: { font: { size: 11 } } },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: {
                        font: { size: 11 },
                        callback: function (v) { return v.toLocaleString('fr-FR'); }
                    },
                    grid: { color: 'rgba(0,0,0,.06)' }
                }
            },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        footer: function (items) {
                            var total = items
                                .filter(function (it) { return it.dataset.stack === 'total'; })
                                .reduce(function (s, it) { return s + (it.raw || 0); }, 0);
                            return total > 0 ? 'Total : ' + total.toLocaleString('fr-FR') : '';
                        }
                    }
                }
            }
        }
    });
}

/* ================================================================
   Chart 2 — Montants devis (lignes par dentiste + total)
   ================================================================ */
function buildChartDevis(d) {
    destroyChart('chartDevis');

    var datasets = d.dentistes.map(function (dent, i) {
        var color = PALETTE_EVO[i % PALETTE_EVO.length];
        return {
            label:            dent.login,
            data:             dent.devis,
            borderColor:      color,
            backgroundColor:  hex2rgba(color, 0.10),
            borderWidth:      2,
            pointRadius:      3,
            pointHoverRadius: 5,
            tension:          0.35,
            fill:             false
        };
    });

    /* Ligne total en trait épais pointillé */
    datasets.push({
        label:            'Total',
        data:             d.totaux.devis,
        borderColor:      '#212529',
        backgroundColor:  'transparent',
        borderWidth:      2.5,
        borderDash:       [6, 3],
        pointRadius:      4,
        pointHoverRadius: 6,
        tension:          0.35,
        fill:             false
    });

    var ctx = restoreCanvas('chartDevis');
    if (!ctx) { return; }

    _charts['chartDevis'] = new Chart(ctx, {
        type: 'line',
        data: { labels: d.labels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: {
                    beginAtZero: true,
                    ticks: {
                        font: { size: 11 },
                        callback: function (v) {
                            return v >= 1000
                                ? (v / 1000).toLocaleString('fr-FR') + ' k€'
                                : v.toLocaleString('fr-FR') + ' €';
                        }
                    },
                    grid: { color: 'rgba(0,0,0,.06)' }
                }
            },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            var v = ctx.raw || 0;
                            return ' ' + ctx.dataset.label + ' : ' + v.toLocaleString('fr-FR') + ' €';
                        }
                    }
                }
            }
        }
    });
}

/* ================================================================
   Chart 3 — Taux d'acceptation (lignes + seuils 80 % / 50 %)
   ================================================================ */
function buildChartTaux(d) {
    destroyChart('chartTaux');

    var datasets = d.dentistes.map(function (dent, i) {
        var color = PALETTE_EVO[i % PALETTE_EVO.length];
        return {
            label:            dent.login,
            data:             dent.taux,
            borderColor:      color,
            backgroundColor:  hex2rgba(color, 0.08),
            borderWidth:      2,
            pointRadius:      4,
            pointHoverRadius: 6,
            tension:          0.35,
            fill:             false,
            spanGaps:         false   // ne relie pas les mois sans données
        };
    });

    /* Ligne moyenne globale */
    datasets.push({
        label:            'Moyenne globale',
        data:             d.totaux.taux,
        borderColor:      '#212529',
        backgroundColor:  'transparent',
        borderWidth:      2.5,
        borderDash:       [6, 3],
        pointRadius:      4,
        pointHoverRadius: 6,
        tension:          0.35,
        fill:             false,
        spanGaps:         false
    });

    /* Ligne seuil 80 % */
    var seuilHaut = d.labels.map(function () { return 80; });
    datasets.push({
        label:       'Seuil 80 %',
        data:        seuilHaut,
        borderColor: '#198754',
        borderWidth: 1,
        borderDash:  [4, 4],
        pointRadius: 0,
        fill:        false,
        spanGaps:    true
    });

    /* Ligne seuil 50 % */
    var seuilBas = d.labels.map(function () { return 50; });
    datasets.push({
        label:       'Seuil 50 %',
        data:        seuilBas,
        borderColor: '#dc3545',
        borderWidth: 1,
        borderDash:  [4, 4],
        pointRadius: 0,
        fill:        false,
        spanGaps:    true
    });

    var ctx = restoreCanvas('chartTaux');
    if (!ctx) { return; }

    _charts['chartTaux'] = new Chart(ctx, {
        type: 'line',
        data: { labels: d.labels, datasets: datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                y: {
                    beginAtZero: true,
                    min: 0,
                    max: 100,
                    ticks: {
                        font: { size: 11 },
                        callback: function (v) { return v + ' %'; },
                        stepSize: 20
                    },
                    grid: { color: 'rgba(0,0,0,.06)' }
                }
            },
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, padding: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function (ctx) {
                            if (ctx.raw === null || ctx.raw === undefined) {
                                return ' ' + ctx.dataset.label + ' : —';
                            }
                            return ' ' + ctx.dataset.label + ' : ' + ctx.raw + ' %';
                        }
                    }
                }
            }
        }
    });
}

/* ── Initialisation ── */
$(function () {
    /* Sélecteur de période */
    $('[name="nbMois"]').on('change', function () {
        loadEvolution(parseInt($(this).val(), 10));
    });

    /* Chargement initial avec la valeur cochée */
    var initNb = parseInt($('[name="nbMois"]:checked').val() || '12', 10);
    loadEvolution(initNb);
});
