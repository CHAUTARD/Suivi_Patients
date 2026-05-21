/**
 * recap_plans.js — Récap mensuel des plans de traitement
 * SELARL La Vespalienne — Suivi des Actes
 */
'use strict';

$(function () {

    /* ================================================================
       État
       ================================================================ */
    let currentData   = null;
    let fpEditDate    = null;
    let sortConfig    = { column: null, direction: 'asc' };
    let activeFilters = { patient: '', statut: '', devisMin: null, devisMax: null };

    /* ================================================================
       Initialisation
       ================================================================ */
    loadRecapPlans();

    /* ================================================================
       Événements — chargement API
       ================================================================ */
    $('#moisRecapPlans, #filtreDentistePlans').on('change', loadRecapPlans);

    /* ================================================================
       Événements — filtres avancés
       ================================================================ */
    $('#filtrePatient').on('input', debounce(function () {
        activeFilters.patient = this.value.trim().toLowerCase();
        applyFiltersAndRender();
    }, 200));

    $('input[name="filtreStatut"]').on('change', function () {
        activeFilters.statut = this.value;
        applyFiltersAndRender();
    });

    $('#filtreDevisMin').on('input', debounce(function () {
        activeFilters.devisMin = this.value !== '' ? parseInt(this.value, 10) : null;
        applyFiltersAndRender();
    }, 250));

    $('#filtreDevisMax').on('input', debounce(function () {
        activeFilters.devisMax = this.value !== '' ? parseInt(this.value, 10) : null;
        applyFiltersAndRender();
    }, 250));

    $('#btnResetFilters').on('click', resetFilters);

    // Lien "Effacer les filtres" dans la zone résultat
    $(document).on('click', '#lnkResetFilters', function (e) {
        e.preventDefault();
        resetFilters();
    });

    /* ================================================================
       Événements — tri des colonnes
       ================================================================ */
    $(document).on('click', 'th.sortable', function () {
        setSortColumn($(this).data('column'));
        applyFiltersAndRender();
    });

    /* ================================================================
       Événements — export CSV
       ================================================================ */
    $('#btnExportPlansCsv').on('click', exportCsv);

    /* ================================================================
       Événements — modale édition plan
       ================================================================ */
    $(document).on('click', '.btn-edit-plan', function () {
        const idPlan = parseInt($(this).data('id'), 10);
        if (!currentData || !currentData.plans) { return; }

        const plan = currentData.plans.find(function (p) { return p.id_plan === idPlan; });
        if (!plan) { return; }

        $('#editPlanId').val(plan.id_plan);
        $('#editPlanInfo').text(plan.patient + ' — ' + frDate(plan.date));
        $('#editAccepter').val(plan.accepter || 'Non');
        $('#editMontant').val(plan.montant || plan.montant_devis || 0);

        if (!fpEditDate) {
            fpEditDate = flatpickr('#editDateAcceptation', {
                locale: 'fr', dateFormat: 'd/m/Y', allowInput: true
            });
        }
        const dateAcc = plan.date_acceptation;
        if (dateAcc && dateAcc.length === 10 && dateAcc !== '0000-00-00') {
            fpEditDate.setDate(dateAcc, true, 'Y-m-d');
        } else {
            fpEditDate.setDate(new Date(), true);
        }
        new bootstrap.Modal(document.getElementById('modalEditPlan')).show();
    });

    $('#btnSaveEditPlan').on('click', function () {
        const idPlan  = parseInt($('#editPlanId').val(), 10);
        if (!idPlan) { return; }

        const accepter = $('#editAccepter').val();
        const montant  = parseInt($('#editMontant').val(), 10) || 0;

        let dateAcceptation = '';
        const rawDate = fpEditDate ? fpEditDate.input.value.trim() : '';
        if (rawDate) {
            const parts = rawDate.split('/');
            if (parts.length === 3) {
                dateAcceptation = parts[2] + '-' + parts[1] + '-' + parts[0];
            }
        }

        const $btn = $('#btnSaveEditPlan')
            .prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement…');

        $.ajax({
            url:         window.SITE_ROOT + '/api/update_plan_traitement.php',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify({ id_plan: idPlan, accepter, date_acceptation: dateAcceptation, montant }),
            dataType:    'json'
        }).done(function (resp) {
            if (resp.success) {
                bootstrap.Modal.getInstance(document.getElementById('modalEditPlan')).hide();
                showToast('Plan mis à jour avec succès.', 'success');
                loadRecapPlans();
            } else {
                showAlert(resp.error || 'Erreur lors de la mise à jour.', 'danger');
            }
        }).fail(function () {
            showAlert('Erreur réseau.', 'danger');
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Enregistrer');
        });
    });

    /* ================================================================
       Chargement API
       ================================================================ */
    function loadRecapPlans() {
        const mois       = $('#moisRecapPlans').val() || window.INIT_MOIS;
        const filtreUser = parseInt($('#filtreDentistePlans').val() || '0', 10);

        $('#recapPlansTableZone').html(
            '<div class="text-center py-5 text-muted">'
            + '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Chargement…'
            + '</div>'
        );
        $('#alertZone').hide();

        const params = { mois };
        if (window.IS_ADMIN) { params.id_utilisateur = filtreUser; }

        $.ajax({
            url:      window.SITE_ROOT + '/api/get_recap_plans.php',
            method:   'GET',
            data:     params,
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                currentData = resp;
                renderCompare(resp.compare || []);
                applyFiltersAndRender();
            } else {
                showAlert(resp.error || 'Erreur lors du chargement.', 'danger');
            }
        }).fail(function () {
            showAlert('Erreur réseau.', 'danger');
        });
    }

    /* ================================================================
       Filtres
       ================================================================ */
    function applyFiltersAndRender() {
        if (!currentData) { return; }
        const allPlans      = currentData.plans || [];
        const filteredPlans = applyFilters(allPlans);
        updateFilterBadge();
        updateResultCount(filteredPlans.length, allPlans.length);
        renderStats(computeStats(filteredPlans));
        renderTable(filteredPlans);
    }

    function applyFilters(plans) {
        return plans.filter(function (p) {
            // Recherche patient
            if (activeFilters.patient) {
                if (!(p.patient || '').toLowerCase().includes(activeFilters.patient)) {
                    return false;
                }
            }
            // Statut acceptation
            if (activeFilters.statut && p.accepter !== activeFilters.statut) {
                return false;
            }
            // Fourchette montant devis
            const devis = parseInt(p.montant_devis, 10) || 0;
            if (activeFilters.devisMin !== null && devis < activeFilters.devisMin) { return false; }
            if (activeFilters.devisMax !== null && devis > activeFilters.devisMax) { return false; }
            return true;
        });
    }

    function computeStats(plans) {
        let total = 0, acceptes = 0, devis = 0, montants = 0;
        plans.forEach(function (p) {
            total++;
            if (p.accepter === 'Oui') { acceptes++; }
            devis    += parseInt(p.montant_devis, 10) || 0;
            montants += parseInt(p.montant,       10) || 0;
        });
        return {
            total_plans:      total,
            total_acceptes:   acceptes,
            total_devis:      devis,
            total_montants:   montants,
            taux_acceptation: total > 0 ? Math.round(acceptes / total * 1000) / 10 : 0,
        };
    }

    function hasActiveFilters() {
        return activeFilters.patient  !== ''
            || activeFilters.statut   !== ''
            || activeFilters.devisMin !== null
            || activeFilters.devisMax !== null;
    }

    function updateFilterBadge() {
        let n = 0;
        if (activeFilters.patient)          { n++; }
        if (activeFilters.statut)           { n++; }
        if (activeFilters.devisMin !== null){ n++; }
        if (activeFilters.devisMax !== null){ n++; }
        n > 0 ? $('#filtresActiveBadge').text(n).show()
              : $('#filtresActiveBadge').hide();
    }

    function updateResultCount(filtered, total) {
        const $el = $('#filtreResultat');
        if (hasActiveFilters()) {
            $el.html(
                '<i class="bi bi-funnel-fill me-1 text-primary"></i>'
                + '<strong>' + filtered + '</strong>&nbsp;plan(s) affiché(s) sur&nbsp;<strong>' + total + '</strong>'
                + '&ensp;<a href="#" id="lnkResetFilters" class="text-danger">Effacer les filtres</a>'
            ).show();
        } else {
            $el.hide();
        }
    }

    function resetFilters() {
        activeFilters = { patient: '', statut: '', devisMin: null, devisMax: null };
        $('#filtrePatient').val('');
        $('input[name="filtreStatut"][value=""]').prop('checked', true);
        $('#filtreDevisMin, #filtreDevisMax').val('');
        applyFiltersAndRender();
    }

    /* ================================================================
       Rendu — statistiques
       ================================================================ */
    function renderStats(stats) {
        $('#statTotalPlans').text(intFmt(stats.total_plans || 0));
        $('#statAcceptes').text(intFmt(stats.total_acceptes || 0));
        $('#statDevis').text(intFmt(stats.total_devis || 0) + ' €');

        const taux   = parseFloat(stats.taux_acceptation) || 0;
        const tauxEl = document.getElementById('statTaux');
        tauxEl.textContent = taux + ' %';
        tauxEl.className   = 'h4 mb-0 fw-bold '
            + (taux >= 80 ? 'text-success' : taux >= 50 ? 'text-warning' : 'text-danger');
    }

    /* ================================================================
       Rendu — tableau des plans
       ================================================================ */
    function renderTable(plans) {
        if (plans.length === 0) {
            $('#recapPlansTableZone').html(
                '<div class="text-center py-5 text-muted">'
                + '<i class="bi bi-inbox display-6 d-block mb-2"></i>'
                + (hasActiveFilters()
                    ? 'Aucun plan ne correspond aux filtres appliqués.'
                    : 'Aucun plan de traitement pour cette période.')
                + '</div>'
            );
            return;
        }

        let sorted = [...plans];
        if (sortConfig.column) {
            sorted = sortPlans(sorted, sortConfig.column, sortConfig.direction);
        }

        let html = '<table class="table table-sm table-bordered mb-0">';
        html += '<thead class="table-dark"><tr>';
        html += '<th class="sortable" data-column="date" style="cursor:pointer;">'
              + getHeaderWithIcon('Date') + '</th>';
        if (window.IS_ADMIN) {
            html += '<th class="sortable" data-column="login" style="cursor:pointer;">'
                  + getHeaderWithIcon('Dentiste') + '</th>';
        }
        html += '<th class="sortable" data-column="patient" style="cursor:pointer;">'
              + getHeaderWithIcon('Patient') + '</th>';
        html += '<th class="sortable text-end" data-column="montant_devis" style="cursor:pointer;">'
              + getHeaderWithIcon('Montant devis') + '</th>';
        html += '<th class="sortable text-center" data-column="accepter" style="cursor:pointer;">'
              + getHeaderWithIcon('Accepté') + '</th>';
        html += '<th class="sortable" data-column="date_acceptation" style="cursor:pointer;">'
              + getHeaderWithIcon('Date acceptation') + '</th>';
        html += '<th class="sortable text-end" data-column="montant" style="cursor:pointer;">'
              + getHeaderWithIcon('Montant') + '</th>';
        html += '<th class="text-center" style="width:40px;"></th>';
        html += '</tr></thead><tbody>';

        sorted.forEach(function (p) {
            // Mise en évidence du terme recherché dans le nom patient
            const patientHtml = activeFilters.patient
                ? highlightMatch(p.patient || '', activeFilters.patient)
                : escHtml(p.patient || '');

            html += '<tr>';
            html += '<td class="text-nowrap">' + escHtml(frDate(p.date)) + '</td>';
            if (window.IS_ADMIN) {
                html += '<td>' + escHtml(p.login || '') + '</td>';
            }
            html += '<td>' + patientHtml + '</td>';
            html += '<td class="text-end text-nowrap">' + intFmt(p.montant_devis || 0) + ' €</td>';
            html += '<td class="text-center">' + getAccepterBadge(p.accepter) + '</td>';
            html += '<td class="text-nowrap">' + escHtml(frDate(p.date_acceptation)) + '</td>';
            html += '<td class="text-end text-nowrap">' + intFmt(p.montant || 0) + ' €</td>';
            html += '<td class="text-center">'
                  + '<button class="btn btn-outline-primary btn-sm py-0 px-1 btn-edit-plan"'
                  + ' data-id="' + p.id_plan + '" title="Modifier">'
                  + '<i class="bi bi-pencil" style="font-size:.8rem;"></i>'
                  + '</button></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#recapPlansTableZone').html(html);
    }

    /* ================================================================
       Rendu — tableau comparatif (admin)
       ================================================================ */
    function renderCompare(compare) {
        if (!window.IS_ADMIN) { return; }

        if (!compare || compare.length === 0) {
            $('#comparePlansTableZone').html(
                '<div class="text-center py-4 text-muted">Aucune donnée comparative.</div>'
            );
            return;
        }

        let html = '<table class="table table-sm table-bordered mb-0">';
        html += '<thead class="table-dark"><tr>'
              + '<th>Dentiste</th>'
              + '<th class="text-end">Plans</th>'
              + '<th class="text-end">Acceptés</th>'
              + '<th class="text-end">Montant devis</th>'
              + '<th class="text-end">Montant acceptés</th>'
              + '<th class="text-end">Taux acceptation</th>'
              + '</tr></thead><tbody>';

        compare.forEach(function (row) {
            const taux    = parseFloat(row.taux_acceptation) || 0;
            const tauxCls = taux >= 80 ? 'text-success' : taux >= 50 ? 'text-warning' : 'text-danger';
            html += '<tr>'
                  + '<td class="fw-semibold">' + escHtml(row.login || '')              + '</td>'
                  + '<td class="text-end">'    + intFmt(row.total_plans    || 0)       + '</td>'
                  + '<td class="text-end">'    + intFmt(row.total_acceptes || 0)       + '</td>'
                  + '<td class="text-end">'    + intFmt(row.total_devis    || 0) + ' €</td>'
                  + '<td class="text-end">'    + intFmt(row.total_montants || 0) + ' €</td>'
                  + '<td class="text-end fw-semibold ' + tauxCls + '">' + taux + ' %</td>'
                  + '</tr>';
        });

        html += '</tbody></table>';
        $('#comparePlansTableZone').html(html);
    }

    /* ================================================================
       Export CSV (données filtrées)
       ================================================================ */
    function exportCsv() {
        if (!currentData || !(currentData.plans || []).length) {
            showToast('Aucune donnée à exporter.', 'warning');
            return;
        }
        const plans = applyFilters(currentData.plans);
        if (plans.length === 0) {
            showToast('Aucune ligne ne correspond aux filtres appliqués.', 'warning');
            return;
        }

        let csv = 'Date;';
        if (window.IS_ADMIN) { csv += 'Dentiste;'; }
        csv += 'Patient;Montant devis (€);Accepté;Date acceptation;Montant (€)\n';

        plans.forEach(function (p) {
            csv += escCsv(frDate(p.date))            + ';';
            if (window.IS_ADMIN) { csv += escCsv(p.login || '') + ';'; }
            csv += escCsv(p.patient || '')            + ';';
            csv += (p.montant_devis || 0)             + ';';
            csv += getAccepterLabel(p.accepter)       + ';';
            csv += escCsv(frDate(p.date_acceptation)) + ';';
            csv += (p.montant || 0)                   + '\n';
        });

        const moisLabel = currentData.mois || 'mois';
        const suffix    = hasActiveFilters() ? '_filtre' : '';
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'recap_plans_' + moisLabel + suffix + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    /* ================================================================
       Tri
       ================================================================ */
    function setSortColumn(col) {
        sortConfig.direction = (sortConfig.column === col && sortConfig.direction === 'asc')
            ? 'desc' : 'asc';
        sortConfig.column = col;
    }

    function sortPlans(plans, col, dir) {
        return [...plans].sort(function (a, b) {
            let va = a[col], vb = b[col];
            if (col === 'date' || col === 'date_acceptation') {
                return dir === 'asc'
                    ? new Date(va || 0) - new Date(vb || 0)
                    : new Date(vb || 0) - new Date(va || 0);
            }
            if (col === 'montant_devis' || col === 'montant') {
                va = parseInt(va, 10) || 0;
                vb = parseInt(vb, 10) || 0;
                return dir === 'asc' ? va - vb : vb - va;
            }
            if (col === 'accepter') {
                const order = { 'Oui': 0, 'en Partie': 1, 'Non': 2 };
                const oa = order[va] !== undefined ? order[va] : 3;
                const ob = order[vb] !== undefined ? order[vb] : 3;
                return dir === 'asc' ? oa - ob : ob - oa;
            }
            va = String(va || '').toLowerCase();
            vb = String(vb || '').toLowerCase();
            return dir === 'asc'
                ? va.localeCompare(vb, 'fr-FR')
                : vb.localeCompare(va, 'fr-FR');
        });
    }

    /* ================================================================
       Helpers
       ================================================================ */
    function getAccepterBadge(v) {
        const val = String(v || '').trim();
        if (val === 'Oui')       { return '<span class="badge bg-success">Oui</span>'; }
        if (val === 'en Partie') { return '<span class="badge bg-warning text-dark">En partie</span>'; }
        return '<span class="badge bg-danger">Non</span>';
    }

    function getAccepterLabel(v) {
        const val = String(v || '').trim();
        return (val === 'Oui' || val === 'Non' || val === 'en Partie') ? val : 'Non';
    }

    function getHeaderWithIcon(label) {
        const colMap = {
            'Date': 'date', 'Dentiste': 'login', 'Patient': 'patient',
            'Montant devis': 'montant_devis', 'Accepté': 'accepter',
            'Date acceptation': 'date_acceptation', 'Montant': 'montant'
        };
        const col = colMap[label];
        if (col && col === sortConfig.column) {
            const icon = sortConfig.direction === 'asc'
                ? '<i class="bi bi-arrow-up ms-1" style="font-size:0.85rem;"></i>'
                : '<i class="bi bi-arrow-down ms-1" style="font-size:0.85rem;"></i>';
            return label + ' ' + icon;
        }
        return label + ' <i class="bi bi-arrow-down-up ms-1" style="font-size:0.85rem;opacity:0.5;"></i>';
    }

    function highlightMatch(text, term) {
        const safe = escHtml(text);
        if (!term) { return safe; }
        const re  = new RegExp('(' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
        return safe.replace(re, '<mark class="p-0">$1</mark>');
    }

    function frDate(iso) {
        if (!iso || iso.length !== 10) { return ''; }
        return iso.substring(8, 10) + '/' + iso.substring(5, 7) + '/' + iso.substring(0, 4);
    }

    function intFmt(v) {
        return (parseInt(v, 10) || 0).toLocaleString('fr-FR');
    }

    function escHtml(str) {
        return $('<div>').text(String(str || '')).html();
    }

    function escCsv(str) {
        str = String(str || '');
        if (/[;"\n]/.test(str)) { return '"' + str.replace(/"/g, '""') + '"'; }
        return str;
    }

    function debounce(fn, delay) {
        let timer;
        return function () {
            const ctx = this, args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

});
