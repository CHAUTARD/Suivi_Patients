/**
 * home.js — Tableau de bord principal
 * SELARL La Vespalienne — Suivi des Actes
 */
'use strict';

$(function () {

    /* ---- Chargement du tableau de bord ---- */
    $.ajax({
        url:      window.SITE_ROOT + '/api/get_dashboard.php',
        method:   'GET',
        dataType: 'json'
    }).done(function (d) {
        if (!d.success) {
            showAlert(d.error || 'Erreur de chargement.', 'danger');
            return;
        }

        var moisLabel = d.mois_label || d.mois;
        $('#dashSubtitle').text('Statistiques de ' + moisLabel);

        /* ---- KPIs ---- */
        if (d.is_admin) {
            $('#kpiJours').html('<span class="text-muted fs-5">—</span>');
            $('#kpiDerniere').text(
                d.derniere_saisie ? 'Dernière date : ' + frDate(d.derniere_saisie) : 'Aucune saisie'
            );
        } else {
            $('#kpiJours').text(d.jours_saisis || 0);
            $('#kpiDerniere').text(
                d.derniere_saisie
                    ? 'Dernière date : ' + frDate(d.derniere_saisie)
                    : 'Aucune saisie ce mois'
            );
        }

        var p = d.plans || {};
        $('#kpiPlans').text(fmt(p.total || 0));
        $('#kpiTaux').text((p.taux || 0) + ' %');
        $('#kpiAcceptes').text((p.acceptes || 0) + ' / ' + (p.total || 0) + ' plans acceptés');
        $('#kpiDevis').text(fmt(p.total_devis || 0) + ' €');
        $('#kpiMontant').text('Accepté : ' + fmt(p.total_montant || 0) + ' €');

        /* Couleur du taux d'acceptation */
        var taux   = parseFloat(p.taux) || 0;
        var tauxEl = document.getElementById('kpiTaux');
        tauxEl.className = 'h3 mb-0 fw-bold '
            + (taux >= 80 ? 'text-success' : taux >= 50 ? 'text-warning' : 'text-danger');

        /* ---- Tableau comparatif (admin) ---- */
        if (window.IS_ADMIN && d.dentistes && d.dentistes.length > 0) {
            $('#adminMoisLabel').text(moisLabel);
            var html = '';
            d.dentistes.forEach(function (r) {
                var tc = r.taux >= 80 ? 'text-success' : r.taux >= 50 ? 'text-warning' : 'text-danger';
                html += '<tr>'
                    + '<td class="fw-semibold">'  + escH(r.login) + '</td>'
                    + '<td class="text-center">'  + (r.jours_saisis || 0) + '</td>'
                    + '<td class="text-center text-muted small">'
                        + (r.derniere_saisie ? frDate(r.derniere_saisie) : '—') + '</td>'
                    + '<td class="text-end">'     + fmt(r.total_plans)    + '</td>'
                    + '<td class="text-end">'     + fmt(r.total_acceptes) + '</td>'
                    + '<td class="text-end fw-semibold ' + tc + '">' + r.taux + ' %</td>'
                    + '<td class="text-end">'     + fmt(r.total_devis)    + ' €</td>'
                    + '</tr>';
            });
            $('#adminTableBody').html(html);
            $('#adminZone').show();
        }

    }).fail(function () {
        showAlert('Erreur réseau lors du chargement du tableau de bord.', 'danger');
    });

    /* ---- Helpers ---- */
    function frDate(iso) {
        if (!iso || iso.length < 10) { return ''; }
        return iso.substring(8, 10) + '/' + iso.substring(5, 7) + '/' + iso.substring(0, 4);
    }
    function fmt(v) { return (parseInt(v, 10) || 0).toLocaleString('fr-FR'); }
    function escH(s) { return $('<div>').text(s || '').html(); }
});
