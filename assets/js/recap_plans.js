/**
 * recap_plans.js — Récap mensuel des plans de traitement
 */

'use strict';

$(function () {
    let currentData = null;
    let sortConfig = {
        column: null,
        direction: 'asc'
    };

    // Instance flatpickr pour la modale
    let fpEditDate = null;

    loadRecapPlans();

    $('#moisRecapPlans').on('change', function () {
        loadRecapPlans();
    });

    $('#filtreDentistePlans').on('change', function () {
        loadRecapPlans();
    });

    $('#btnExportPlansCsv').on('click', function () {
        exportCsv();
    });

    $(document).on('click', 'th.sortable', function () {
        const columnName = $(this).data('column');
        setSortColumn(columnName);
        renderTable(currentData);
    });

    // Ouverture de la modale via le bouton d'édition
    $(document).on('click', '.btn-edit-plan', function () {
        const idPlan = parseInt($(this).data('id'), 10);
        if (!currentData || !currentData.plans) { return; }

        const plan = currentData.plans.find(function (p) { return p.id_plan === idPlan; });
        if (!plan) { return; }

        $('#editPlanId').val(plan.id_plan);
        $('#editPlanInfo').text(plan.patient + ' — ' + frDate(plan.date));
        $('#editAccepter').val(plan.accepter || 'Non');
        $('#editMontant').val(plan.montant || plan.montant_devis || 0);

        // Initialiser flatpickr une seule fois
        if (!fpEditDate) {
            fpEditDate = flatpickr('#editDateAcceptation', {
                locale: 'fr',
                dateFormat: 'd/m/Y',
                allowInput: true
            });
        }

        const dateAcc = plan.date_acceptation;
        if (dateAcc && dateAcc.length === 10 && dateAcc !== '0000-00-00') {
            fpEditDate.setDate(dateAcc, true, 'Y-m-d');
        } else {
            fpEditDate.setDate(new Date(), true);
        }

        const modal = new bootstrap.Modal(document.getElementById('modalEditPlan'));
        modal.show();
    });

    // Sauvegarde depuis la modale
    $('#btnSaveEditPlan').on('click', function () {
        const idPlan = parseInt($('#editPlanId').val(), 10);
        if (!idPlan) { return; }

        const accepter = $('#editAccepter').val();
        const montant  = parseInt($('#editMontant').val(), 10) || 0;

        // Récupérer la date saisie et la convertir en Y-m-d pour l'API
        let dateAcceptation = '';
        const rawDate = fpEditDate ? fpEditDate.input.value.trim() : '';
        if (rawDate) {
            const parts = rawDate.split('/');
            if (parts.length === 3) {
                dateAcceptation = parts[2] + '-' + parts[1] + '-' + parts[0];
            }
        }

        const $btn = $('#btnSaveEditPlan').prop('disabled', true)
                         .html('<span class="spinner-border spinner-border-sm me-1"></span>Enregistrement…');

        $.ajax({
            url: window.SITE_ROOT + '/api/update_plan_traitement.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                id_plan:          idPlan,
                accepter:         accepter,
                date_acceptation: dateAcceptation,
                montant:          montant
            }),
            dataType: 'json'
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
            $btn.prop('disabled', false)
                .html('<i class="bi bi-check-lg me-1"></i>Enregistrer');
        });
    });

    function loadRecapPlans() {
        const mois = $('#moisRecapPlans').val() || window.INIT_MOIS;
        const filtreUser = parseInt($('#filtreDentistePlans').val() || '0', 10);

        $('#recapPlansTableZone').html(
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
            url: window.SITE_ROOT + '/api/get_recap_plans.php',
            method: 'GET',
            data: params,
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                currentData = resp;
                renderStats(resp.stats || {});
                renderTable(resp);
                renderCompare(resp.compare || []);
            } else {
                showAlert(resp.error || 'Erreur lors du chargement.', 'danger');
            }
        }).fail(function () {
            showAlert('Erreur réseau.', 'danger');
        });
    }

    function renderCompare(compare) {
        if (!window.IS_ADMIN) { return; }

        if (!compare || compare.length === 0) {
            $('#comparePlansTableZone').html(
                '<div class="text-center py-4 text-muted">Aucune donnée comparative.</div>'
            );
            return;
        }

        let html = '<table class="table table-sm table-bordered mb-0">';
        html += '<thead class="table-dark"><tr>';
        html += '<th>Dentiste</th>';
        html += '<th class="text-end">Plans</th>';
        html += '<th class="text-end">Acceptés</th>';
        html += '<th class="text-end">Montant devis</th>';
        html += '<th class="text-end">Montant acceptés</th>';
        html += '<th class="text-end">Taux acceptation</th>';
        html += '</tr></thead><tbody>';

        compare.forEach(function (row) {
            html += '<tr>';
            html += '<td>' + escHtml(row.login || '') + '</td>';
            html += '<td class="text-end">' + intFmt(row.total_plans || 0) + '</td>';
            html += '<td class="text-end">' + intFmt(row.total_acceptes || 0) + '</td>';
            html += '<td class="text-end">' + intFmt(row.total_devis || 0) + '</td>';
            html += '<td class="text-end">' + intFmt(row.total_montants || 0) + '</td>';
            html += '<td class="text-end">' + (row.taux_acceptation || 0) + '%</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#comparePlansTableZone').html(html);
    }

    function renderStats(stats) {
        $('#statTotalPlans').text(intFmt(stats.total_plans || 0));
        $('#statAcceptes').text(intFmt(stats.total_acceptes || 0));
        $('#statDevis').text(intFmt(stats.total_devis || 0));
        $('#statTaux').text((stats.taux_acceptation || 0) + '%');
    }

    function renderTable(data) {
        const plans = data.plans || [];

        if (plans.length === 0) {
            $('#recapPlansTableZone').html(
                '<div class="text-center py-5 text-muted">'
                + '<i class="bi bi-inbox display-6 d-block mb-2"></i>'
                + 'Aucun plan de traitement pour cette période.'
                + '</div>'
            );
            return;
        }

        let sortedPlans = [...plans];
        if (sortConfig.column) {
            sortedPlans = sortPlans(sortedPlans, sortConfig.column, sortConfig.direction);
        }

        let html = '<table class="table table-sm table-bordered mb-0">';
        html += '<thead class="table-dark"><tr>';
        html += '<th class="sortable" data-column="date" style="cursor:pointer;">' + getHeaderWithIcon('Date') + '</th>';
        if (window.IS_ADMIN) {
            html += '<th class="sortable" data-column="login" style="cursor:pointer;">' + getHeaderWithIcon('Dentiste') + '</th>';
        }
        html += '<th class="sortable" data-column="patient" style="cursor:pointer;">' + getHeaderWithIcon('Patient') + '</th>';
        html += '<th class="sortable text-end" data-column="montant_devis" style="cursor:pointer;">' + getHeaderWithIcon('Montant devis') + '</th>';
        html += '<th class="sortable text-center" data-column="accepter" style="cursor:pointer;">' + getHeaderWithIcon('Accepté') + '</th>';
        html += '<th class="sortable" data-column="date_acceptation" style="cursor:pointer;">' + getHeaderWithIcon('Date acceptation') + '</th>';
        html += '<th class="sortable text-end" data-column="montant" style="cursor:pointer;">' + getHeaderWithIcon('Montant') + '</th>';
        html += '<th class="text-center" style="width:40px;"></th>';
        html += '</tr></thead><tbody>';

        sortedPlans.forEach(function (p) {
            html += '<tr>';
            html += '<td>' + escHtml(frDate(p.date)) + '</td>';
            if (window.IS_ADMIN) {
                html += '<td>' + escHtml(p.login || '') + '</td>';
            }
            html += '<td>' + escHtml(p.patient || '') + '</td>';
            html += '<td class="text-end">' + intFmt(p.montant_devis || 0) + '</td>';
            html += '<td class="text-center">' + getAccepterBadge(p.accepter) + '</td>';
            html += '<td>' + escHtml(frDate(p.date_acceptation)) + '</td>';
            html += '<td class="text-end">' + intFmt(p.montant || 0) + '</td>';
            html += '<td class="text-center">'
                  + '<button class="btn btn-outline-primary btn-sm py-0 px-1 btn-edit-plan" '
                  + 'data-id="' + p.id_plan + '" title="Modifier">'
                  + '<i class="bi bi-pencil" style="font-size:.8rem;"></i>'
                  + '</button></td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        $('#recapPlansTableZone').html(html);
    }

    function getAccepterBadge(accepterValue) {
        const value = String(accepterValue || '').trim();
        if (value === 'Oui') {
            return '<span class="badge bg-success">Oui</span>';
        } else if (value === 'Non') {
            return '<span class="badge bg-danger">Non</span>';
        } else if (value === 'en Partie') {
            return '<span class="badge bg-warning text-dark">En Partie</span>';
        }
        return '<span class="badge bg-secondary">Non</span>';
    }

    function getHeaderWithIcon(headerText) {
        if (!sortConfig.column) {
            return headerText + ' <i class="bi bi-arrow-down-up ms-1" style="font-size:0.85rem;opacity:0.5;"></i>';
        }
        const columnMap = {
            'Date': 'date', 'Dentiste': 'login', 'Patient': 'patient',
            'Montant devis': 'montant_devis', 'Accepté': 'accepter',
            'Date acceptation': 'date_acceptation', 'Montant': 'montant'
        };
        const column = columnMap[headerText];
        if (column === sortConfig.column) {
            const icon = sortConfig.direction === 'asc'
                ? '<i class="bi bi-arrow-up ms-1" style="font-size:0.85rem;"></i>'
                : '<i class="bi bi-arrow-down ms-1" style="font-size:0.85rem;"></i>';
            return headerText + ' ' + icon;
        }
        return headerText + ' <i class="bi bi-arrow-down-up ms-1" style="font-size:0.85rem;opacity:0.5;"></i>';
    }

    function setSortColumn(columnName) {
        if (sortConfig.column === columnName) {
            sortConfig.direction = sortConfig.direction === 'asc' ? 'desc' : 'asc';
        } else {
            sortConfig.column    = columnName;
            sortConfig.direction = 'asc';
        }
    }

    function sortPlans(plans, column, direction) {
        return [...plans].sort(function (a, b) {
            let valueA = a[column];
            let valueB = b[column];

            if (column === 'date' || column === 'date_acceptation') {
                return direction === 'asc'
                    ? new Date(valueA) - new Date(valueB)
                    : new Date(valueB) - new Date(valueA);
            }
            if (column === 'montant_devis' || column === 'montant') {
                valueA = parseInt(valueA, 10) || 0;
                valueB = parseInt(valueB, 10) || 0;
                return direction === 'asc' ? valueA - valueB : valueB - valueA;
            }
            if (column === 'accepter') {
                const orderMap = { 'Oui': 0, 'Non': 1, 'en Partie': 2 };
                const orderA = orderMap[valueA] !== undefined ? orderMap[valueA] : 3;
                const orderB = orderMap[valueB] !== undefined ? orderMap[valueB] : 3;
                return direction === 'asc' ? orderA - orderB : orderB - orderA;
            }

            valueA = String(valueA || '').toLowerCase();
            valueB = String(valueB || '').toLowerCase();
            return direction === 'asc'
                ? valueA.localeCompare(valueB, 'fr-FR')
                : valueB.localeCompare(valueA, 'fr-FR');
        });
    }

    function exportCsv() {
        if (!currentData || !currentData.plans || currentData.plans.length === 0) {
            showToast('Aucune donnée à exporter.', 'warning');
            return;
        }

        let csv = 'Date;';
        if (window.IS_ADMIN) { csv += 'Dentiste;'; }
        csv += 'Patient;Montant devis;Accepté;Date acceptation;Montant\n';

        currentData.plans.forEach(function (p) {
            csv += escCsv(frDate(p.date)) + ';';
            if (window.IS_ADMIN) { csv += escCsv(p.login || '') + ';'; }
            csv += escCsv(p.patient || '') + ';';
            csv += (p.montant_devis || 0) + ';';
            csv += getAccepterLabel(p.accepter) + ';';
            csv += escCsv(frDate(p.date_acceptation)) + ';';
            csv += (p.montant || 0) + '\n';
        });

        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'recap_plans_' + (currentData.mois || 'mois') + '.csv';
        a.click();
        URL.revokeObjectURL(url);
    }

    function getAccepterLabel(accepterValue) {
        const value = String(accepterValue || '').trim();
        return (value === 'Oui' || value === 'Non' || value === 'en Partie') ? value : 'Non';
    }

    function frDate(isoDate) {
        if (!isoDate || isoDate.length !== 10) { return ''; }
        return isoDate.substring(8, 10) + '/' + isoDate.substring(5, 7) + '/' + isoDate.substring(0, 4);
    }

    function intFmt(v) {
        return (parseInt(v, 10) || 0).toLocaleString('fr-FR');
    }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function escCsv(str) {
        str = String(str);
        if (/[;"\n]/.test(str)) { return '"' + str.replace(/"/g, '""') + '"'; }
        return str;
    }
});
