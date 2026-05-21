/**
 * recap.js — Page récapitulatif mensuel
 * 
 * SELARL La Vespalienne — Suivi des Actes
 * 
 * Modif :
 * - 2026-05-15: Mise en évidance des week-end et des jours fériés
 */
'use strict';

$(function () {
    let currentData = null; // Données chargées

    /* ---- Chargement initial ---- */
    loadRecap();

    /* ---- Événements ---- */
    $('#moisRecap').on('change', function () {
        loadRecap();
    });

    $('#filtreDentiste').on('change', function () {
        loadRecap();
    });

    $('#btnExportCsv').on('click', function () {
        exportCsv();
    });

    /* ================================================================
       Fonctions
       ================================================================ */

    /**
     * Charge les données récapitulatives depuis l'API.
     */
    function loadRecap() {
        const mois        = $('#moisRecap').val() || window.INIT_MOIS;
        const filtreUser  = parseInt($('#filtreDentiste').val() || '0', 10);

        $('#recapTableZone').html(
            '<div class="text-center py-5 text-muted">'
            + '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Chargement…'
            + '</div>'
        );
        $('#alertZone').hide();

        const params = { mois: mois };
        if (window.IS_ADMIN) {
            params.id_utilisateur = filtreUser;
        }

        $.ajax({
            url:      window.SITE_ROOT + '/api/get_recap.php',
            method:   'GET',
            data:     params,
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                currentData = resp;
                renderRecap(resp);
            } else {
                showAlert(resp.error || 'Erreur lors du chargement.', 'danger');
                $('#recapTableZone').html(
                    '<div class="text-center py-4 text-muted">Erreur de chargement.</div>'
                );
            }
        }).fail(function () {
            showAlert('Erreur réseau.', 'danger');
            $('#recapTableZone').html(
                '<div class="text-center py-4 text-muted">Erreur réseau.</div>'
            );
        });
    }

    /**
     * Construit et insère le tableau récapitulatif.
     */
    function renderRecap(data) {
        if (!data.piliers || data.piliers.length === 0) {
            $('#recapTableZone').html(
                '<div class="text-center py-5 text-muted">'
                + '<i class="bi bi-inbox display-6 d-block mb-2"></i>'
                + 'Aucune donnée pour cette période.'
                + '</div>'
            );
            return;
        }

        const jours          = data.jours;
        const nbJours        = data.nbJours;
        const joursInfo      = data.joursInfo     || {}; // Abréviations des jours
        const joursFerier    = data.joursFerier   || {}; // Jours fériés
        const joursFermeture = data.joursFermeture || {}; // Fermetures cabinet

        // Nom du mois en français
        const moisLabel = formatMoisLabel(data.year, data.month);

        let html = '<table class="table table-sm table-bordered mb-0" id="tableRecap">';

        // Entête : libellé mois + jours
        html += '<thead class="table-dark sticky-top"><tr>';
        html += '<th class="text-nowrap" style="min-width:200px;">Action — ' + escHtml(moisLabel) + '</th>';
        for (let j = 1; j <= nbJours; j++) {
            const jourAbbr = joursInfo[j] || ''; // Récupérer l'abrégé (Lu, Ma, etc.)
            const isWeekend = (jourAbbr === 'Sa' || jourAbbr === 'Di'); // Vérifier si samedi ou dimanche
            const isHoliday = joursFerier[j] ? true : false; // Vérifier si jour férié
            const fermetureMotif = joursFermeture[j];
            const bgClass = fermetureMotif
                ? ' recap-fermeture'
                : (isHoliday ? ' recap-ferie' : (isWeekend ? ' bg-secondary' : ''));
            const thTitle = fermetureMotif && fermetureMotif !== true
                ? ' title="Fermé : ' + fermetureMotif + '"'
                : (fermetureMotif ? ' title="Fermeture cabinet"' : '');
            html += '<th class="text-center px-1' + bgClass + '"' + thTitle + ' style="min-width:36px;">'
                + '<div>' + j + '</div>'
                + '<div style="font-size:0.75rem; font-weight:normal;">' + jourAbbr + '</div>'
                + '</th>';
        }
        html += '<th class="text-center px-2 bg-warning text-dark">Total</th>';
        html += '</tr></thead>';

        html += '<tbody>';

        data.piliers.forEach(function (pilier) {
            // Séparateur de pilier
            html += '<tr class="pilier-sep"><td colspan="' + (nbJours + 2) + '">'
                + '<i class="bi bi-layer-forward me-2"></i>' + escHtml(pilier.Pilier)
                + '</td></tr>';

            pilier.actions.forEach(function (action) {
                const isComputed = action.formule && action.formule[0] === '=';
                const rowClass = isComputed ? ' class="table-info fw-semibold"' : '';
                html += '<tr' + rowClass + '>';

                // Libellé + indicateur cumul
                const label = escHtml(action.action)
                    + (isComputed ? ' <small class="text-muted fw-normal ms-1" title="Ligne calculée automatiquement">∑</small>' : '');
                html += '<td class="text-nowrap">' + label + '</td>';

                jours.forEach(function (j) {
                    const v    = action.valeurs[j] || 0;
                    const zero = (v === 0);
                    const jourAbbr = joursInfo[j] || '';
                    const isWeekend      = (jourAbbr === 'Sa' || jourAbbr === 'Di');
                    const isHoliday      = joursFerier[j]    ? true : false;
                    const isFermeture    = joursFermeture[j] ? true : false;
                    let cls = zero ? ' zero-val' : '';
                    cls += isFermeture ? ' recap-fermeture'
                         : (isHoliday  ? ' recap-ferie'
                         : (isWeekend  ? ' bg-secondary bg-opacity-10' : ''));
                    html += '<td class="text-end px-1' + cls + '">'
                        + (zero ? '<span class="text-muted">–</span>' : formatValeur(v))
                        + '</td>';
                });

                // Total
                const tot = action.total || 0;
                html += '<td class="text-end total-col">'
                    + formatValeur(tot)
                    + '</td>';
                html += '</tr>';
            });
        });

        html += '</tbody></table>';
        $('#recapTableZone').html(html);
    }

    /**
     * Exporte le tableau récapitulatif en CSV.
     */
    function exportCsv() {
        if (!currentData || !currentData.piliers) {
            showToast('Aucune donnée à exporter.', 'warning');
            return;
        }

        const d     = currentData;
        const jours = d.jours;
        let   csv   = 'Action';

        jours.forEach(function (j) { csv += ';' + j; });
        csv += ';Total\n';

        d.piliers.forEach(function (pilier) {
            csv += escCsv(pilier.Pilier) + '\n';
            pilier.actions.forEach(function (action) {
                csv += escCsv(action.action);
                jours.forEach(function (j) {
                    csv += ';' + (action.valeurs[j] || 0);
                });
                csv += ';' + (action.total || 0) + '\n';
            });
        });

        const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'recap_' + d.mois + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    /* ---- Helpers ---- */

    function formatMoisLabel(year, month) {
        const mois = [
            'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
            'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'
        ];
        return (mois[month - 1] || '') + ' ' + year;
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function escCsv(str) {
        str = String(str);
        if (/[;"\n]/.test(str)) {
            return '"' + str.replace(/"/g, '""') + '"';
        }
        return str;
    }
});