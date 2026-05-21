/**
 * saisie.js — Page de saisie des actes
 */
'use strict';

$(function () {

    /* ---- État ---- */
    let isDirtyActes = false;
    let isDirtyPlans = false;
    let originalValues = {}; // { id_action: nombre_original }

    /* ---- Ctrl+S : enregistrer les actes ---- */
    window.pageSaveHandler = function () { saveSaisie(); };

    /* ---- Flatpickr date ---- */
    const fp = flatpickr('#dateSaisie', {
        locale:      'fr',
        dateFormat:  'Y-m-d',
        altInput:    true,
        altFormat:   'j F Y',
        maxDate:     'today',
        defaultDate: window.INIT_DATE || new Date(),
        onChange: function (dates, dateStr) {
            const id = getSelectedUserId();
            loadSaisie(dateStr, id);
            if (!window.IS_ADMIN || id > 0) {
                loadPlans(dateStr, id);
            }
            updateNavButtons(dateStr);
        }
    });

    /* ---- Bouton "Aujourd'hui" ---- */
    $('#btnToday').on('click', function () {
        const todayStr = fp.formatDate(new Date(), 'Y-m-d');
        fp.setDate(todayStr, false);
        const id = getSelectedUserId();
        loadSaisie(todayStr, id);
        if (!window.IS_ADMIN || id > 0) {
            loadPlans(todayStr, id);
        }
        updateNavButtons(todayStr);
    });

    /* ---- Boutons navigation jour ---- */
    $('#btnPrevDay').on('click', function () { navigateDay(-1); });
    $('#btnNextDay').on('click', function () { navigateDay(+1); });

    /* ---- Chargement initial ---- */
    const initDate = window.INIT_DATE || fp.formatDate(new Date(), 'Y-m-d');

    if (window.IS_ADMIN) {
        loadSaisie(initDate, 0); // Vue consolidée par défaut
        showPlanPlaceholder();
    } else {
        loadSaisie(initDate);
        loadPlans(initDate);
    }

    /* ---- Sélection du dentiste (admin) ---- */
    $('#filtreDentiste').on('change', function () {
        const id = getSelectedUserId();
        const d  = fp.formatDate(fp.selectedDates[0], 'Y-m-d');
        loadSaisie(d, id);
        if (id > 0) {
            loadPlans(d, id);
        } else {
            showPlanPlaceholder();
        }
    });

    /* ---- Inputs saisie actes ---- */
    $(document).on('input', '#tableSaisie .saisie-input', function () {
        const idAction = parseInt($(this).data('action'), 10);
        const current  = parseInt($(this).val(), 10) || 0;
        const original = originalValues[idAction] !== undefined ? originalValues[idAction] : 0;

        if (current !== original) {
            $(this).closest('tr').addClass('row-modified');
        } else {
            $(this).closest('tr').removeClass('row-modified');
        }

        // Dirty si au moins une ligne a changé
        const anyDirty = Object.keys(originalValues).some(function (id) {
            const inp = $('#tableSaisie .saisie-input[data-action="' + id + '"]');
            return inp.length && (parseInt(inp.val(), 10) || 0) !== originalValues[id];
        });
        setDirty('actes', anyDirty);

        updatePercentages();
        updateComputedRows();
    });

    /* ---- Inputs plans (dirty) ---- */
    $(document).on('input change', '#plansBody input, #plansBody select', function () {
        setDirty('plans', true);
    });

    /* ---- Enregistrer actes ---- */
    $('#btnEnregistrer').on('click', function () {
        saveSaisie();
    });

    /* ---- Plans ---- */
    $('#btnAddPlan').on('click', function () {
        appendPlanRow({ patient: '', montant_devis: 0, accepter: 'Non', montant: 0 });
        setDirty('plans', true);
    });

    $('#btnSavePlans').on('click', function () {
        savePlans();
    });

    $('#plansBody').on('click', '.btn-delete-plan', function () {
        $(this).closest('tr').remove();
        setDirty('plans', true);
        if ($('#plansBody tr').length === 0) {
            renderPlans([]);
        }
    });

    /* ---- Touche Entrée dans les inputs actes ---- */
    $('#tableSaisie').on('keydown', '.saisie-input:not([type="hidden"])', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const inputs = $('#tableSaisie .saisie-input:not([type="hidden"])');
            const idx = inputs.index(this);
            if (idx < inputs.length - 1) {
                inputs.eq(idx + 1).focus().select();
            } else {
                saveSaisie();
            }
        }
    });

    /* ---- Avertissement quitter page si non enregistré ---- */
    window.addEventListener('beforeunload', function (e) {
        if (isDirtyActes || isDirtyPlans) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    /* ================================================================
       Gestion de l'état "non enregistré"
       ================================================================ */

    function setDirty(type, dirty) {
        if (type === 'actes') {
            isDirtyActes = dirty;
            if (dirty) {
                $('#btnEnregistrer')
                    .addClass('btn-unsaved')
                    .html('<i class="bi bi-floppy2-fill me-1"></i> Enregistrer'
                        + ' <span class="badge bg-warning text-dark ms-1">!</span>');
            } else {
                $('#btnEnregistrer')
                    .removeClass('btn-unsaved')
                    .html('<i class="bi bi-floppy2-fill me-1"></i> Enregistrer');
                // Retirer le surlignage de toutes les lignes
                $('#tableSaisie tr').removeClass('row-modified');
            }
        } else {
            isDirtyPlans = dirty;
            if (dirty) {
                $('#btnSavePlans')
                    .addClass('btn-unsaved')
                    .html('<i class="bi bi-floppy2-fill me-1"></i>Enregistrer plans'
                        + ' <span class="badge bg-warning text-dark ms-1">!</span>');
            } else {
                $('#btnSavePlans')
                    .removeClass('btn-unsaved')
                    .html('<i class="bi bi-floppy2-fill me-1"></i>Enregistrer plans');
            }
        }
    }

    /* ================================================================
       Fonctions utilitaires admin
       ================================================================ */

    function navigateDay(delta) {
        const current = fp.selectedDates[0] ? new Date(fp.selectedDates[0]) : new Date();
        const next = new Date(current);
        next.setDate(next.getDate() + delta);

        const today = new Date();
        today.setHours(0, 0, 0, 0);
        if (next > today) { return; } // Respecter maxDate

        const nextStr = fp.formatDate(next, 'Y-m-d');
        fp.setDate(nextStr, false);
        const id = getSelectedUserId();
        loadSaisie(nextStr, id);
        if (!window.IS_ADMIN || id > 0) {
            loadPlans(nextStr, id);
        }
        updateNavButtons(nextStr);
    }

    function updateNavButtons(dateStr) {
        const todayStr = fp.formatDate(new Date(), 'Y-m-d');
        $('#btnNextDay').prop('disabled', dateStr >= todayStr);
    }

    function getSelectedUserId() {
        if (!window.IS_ADMIN) { return 0; }
        return parseInt($('#filtreDentiste').val() || '0', 10);
    }

    function showPlanPlaceholder() {
        $('#plansBody').html(
            '<tr><td colspan="6" class="text-center py-4 text-muted">'
            + '<i class="bi bi-person-circle me-2"></i>Sélectionnez un dentiste pour afficher les plans du jour.'
            + '</td></tr>'
        );
    }

    /* ================================================================
       Chargement des actes
       ================================================================ */

    function loadSaisie(date, idUser) {
        $('#saisieBody').html(
            '<tr><td colspan="3" class="text-center py-4 text-muted">'
            + '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Chargement…'
            + '</td></tr>'
        );
        $('#alertZone').hide();
        originalValues = {};

        const params = { date: date };
        if (window.IS_ADMIN) { params.id_utilisateur = idUser || 0; }

        $.ajax({
            url:      window.SITE_ROOT + '/api/get_saisie.php',
            method:   'GET',
            data:     params,
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                // Capturer les valeurs originales avant le rendu
                resp.piliers.forEach(function (pilier) {
                    pilier.actions.forEach(function (action) {
                        if (!action.formule) {
                            originalValues[action.id_action] = action.nombre || 0;
                        }
                    });
                });
                renderTable(resp.piliers);
                setDirty('actes', false);

                // Mode consolidé : lecture seule
                if (resp.consolidated) {
                    $('#tableSaisie .saisie-input').prop('disabled', true);
                    if (!$('#consolidatedBanner').length) {
                        $('#alertZone').after(
                            '<div id="consolidatedBanner" class="alert alert-info py-2 mb-3">'
                            + '<i class="bi bi-people-fill me-2"></i>'
                            + '<strong>Vue consolidée</strong> — total de l\'ensemble des dentistes. '
                            + 'Sélectionnez un dentiste pour modifier les données.'
                            + '</div>'
                        );
                    }
                } else {
                    $('#consolidatedBanner').remove();
                }
            } else {
                showAlert('Erreur lors du chargement des données.', 'danger');
                $('#saisieBody').html('<tr><td colspan="3" class="text-center text-muted py-3">Erreur de chargement.</td></tr>');
            }
        }).fail(function () {
            showAlert('Erreur réseau lors du chargement.', 'danger');
            $('#saisieBody').html('<tr><td colspan="3" class="text-center text-muted py-3">Erreur réseau.</td></tr>');
        });
    }

    /* ================================================================
       Rendu du tableau actes
       ================================================================ */

    function renderTable(piliers) {
        let html = '';

        piliers.forEach(function (pilier) {
            html += '<tr class="pilier-row">'
                + '<td colspan="3"><i class="bi bi-layer-forward me-2"></i>'
                + escHtml(pilier.Pilier) + '</td></tr>';

            pilier.actions.forEach(function (action) {
                const ids = parseFormula(action.formule);
                const isComputed = ids.length > 0;

                if (isComputed) {
                    html += '<tr class="table-info">';
                    html += '<td class="align-middle">'
                        + '<strong>' + escHtml(action.action) + '</strong>'
                        + ' <small class="text-muted ms-1" title="Somme des actions ' + ids.join(', ') + '">∑</small>'
                        + '</td>';
                    html += '<td class="py-1">'
                        + '<div class="input-group input-group-sm" style="max-width:180px;">'
                        + '<span class="form-control form-control-sm bg-info bg-opacity-10 text-end fw-semibold computed-value"'
                        + ' data-formula="' + ids.join(',') + '">0</span>'
                        + '</div>'
                        + '</td>';
                    html += '<td></td>';
                    html += '</tr>';
                } else {
                    html += '<tr>';
                    html += '<td class="align-middle">' + escHtml(action.action) + '</td>';
                    html += '<td class="py-1">'
                        + '<div class="input-group input-group-sm" style="max-width:180px;">'
                        + '<input type="number"'
                        + ' class="form-control form-control-sm saisie-input"'
                        + ' data-action="' + action.id_action + '"'
                        + ' min="0" step="1"'
                        + ' value="' + formatInputVal(action.nombre) + '"'
                        + ' title="' + escHtml(action.action) + '">'
                        + '</div>'
                        + '</td>';

                    let percentageHtml = '<td class="py-1 text-end align-middle">';
                    if (action.id_action == 12) {
                        percentageHtml += '<small class="percentage-display" data-numerator="12" data-denominator="1">-</small>';
                    } else if (action.id_action == 5) {
                        percentageHtml += '<small class="percentage-display" data-numerator="5" data-denominator="2">-</small>';
                    }
                    percentageHtml += '</td>';
                    html += percentageHtml;
                    html += '</tr>';
                }
            });
        });

        $('#saisieBody').html(html);
        updatePercentages();
        updateComputedRows();
    }

    /* ================================================================
       Enregistrement des actes
       ================================================================ */

    function saveSaisie() {
        const idUser = getSelectedUserId();
        if (window.IS_ADMIN && idUser === 0) {
            showToast('Veuillez sélectionner un dentiste.', 'warning');
            $('#filtreDentiste').focus();
            return;
        }

        const date = fp.formatDate(fp.selectedDates[0], 'Y-m-d');
        const data = [];

        $('#tableSaisie .saisie-input').each(function () {
            const idAction = $(this).data('action');
            let val = parseInt($(this).val(), 10) || 0;
            if (val < 0) val = 0;
            data.push({ id_action: idAction, nombre: val });
        });

        if (data.length === 0) {
            showToast('Aucune donnée à enregistrer.', 'warning');
            return;
        }

        $('#btnEnregistrer').prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span> Enregistrement…'
        );

        const payload = { date: date, data: data };
        if (window.IS_ADMIN && idUser) { payload.id_utilisateur = idUser; }

        $.ajax({
            url:         window.SITE_ROOT + '/api/save_saisie.php',
            method:      'POST',
            contentType: 'application/json',
            dataType:    'json',
            data:        JSON.stringify(payload)
        }).done(function (resp) {
            if (resp.success) {
                // Mettre à jour les valeurs originales
                $('#tableSaisie .saisie-input').each(function () {
                    const idAction = parseInt($(this).data('action'), 10);
                    originalValues[idAction] = parseInt($(this).val(), 10) || 0;
                });
                setDirty('actes', false);
                showToast('Données enregistrées avec succès.', 'success');
            } else {
                showToast(resp.error || 'Erreur lors de l\'enregistrement.', 'danger');
            }
        }).fail(function () {
            showToast('Erreur réseau lors de l\'enregistrement.', 'danger');
        }).always(function () {
            $('#btnEnregistrer').prop('disabled', false);
            // setDirty remet le bon label, mais si erreur on remet manuellement
            if (isDirtyActes) {
                $('#btnEnregistrer').html('<i class="bi bi-floppy2-fill me-1"></i> Enregistrer'
                    + ' <span class="badge bg-warning text-dark ms-1">!</span>');
            }
        });
    }

    /* ================================================================
       Plans de traitement
       ================================================================ */

    function loadPlans(date, idUser) {
        $('#plansBody').html(
            '<tr><td colspan="6" class="text-center py-4 text-muted">'
            + '<div class="spinner-border spinner-border-sm me-2" role="status"></div>Chargement…'
            + '</td></tr>'
        );

        const params = { date: date };
        if (window.IS_ADMIN && idUser) { params.id_utilisateur = idUser; }

        $.ajax({
            url:      window.SITE_ROOT + '/api/get_plan_traitement.php',
            method:   'GET',
            data:     params,
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                renderPlans(resp.plans || []);
                setDirty('plans', false);
            } else {
                showToast(resp.error || 'Erreur lors du chargement des plans.', 'danger');
                renderPlans([]);
            }
        }).fail(function () {
            showToast('Erreur réseau lors du chargement des plans.', 'danger');
            renderPlans([]);
        });
    }

    function renderPlans(plans) {
        $('#plansBody').empty();

        if (!plans || plans.length === 0) {
            $('#plansBody').html(
                '<tr class="plan-empty">'
                + '<td colspan="6" class="text-center py-3 text-muted">Aucun plan saisi pour cette date.</td>'
                + '</tr>'
            );
            return;
        }

        plans.forEach(function (plan) {
            appendPlanRow(plan);
        });
    }

    function appendPlanRow(plan) {
        if ($('#plansBody .plan-empty').length) {
            $('#plansBody').empty();
        }

        const html = '<tr>'
            + '<td><input type="text" class="form-control form-control-sm plan-patient" maxlength="150" value="' + escHtmlAttr(plan.patient || '') + '"></td>'
            + '<td><input type="number" class="form-control form-control-sm plan-devis text-end" min="0" step="100" value="' + intVal(plan.montant_devis) + '"></td>'
            + '<td class="text-center align-middle"><select class="form-select form-select-sm plan-accepter">'
            + '<option value="Oui" '   + (plan.accepter === 'Oui'        ? 'selected' : '') + '>Oui</option>'
            + '<option value="Non" '   + (plan.accepter === 'Non'        ? 'selected' : '') + '>Non</option>'
            + '<option value="En Partie" ' + (plan.accepter === 'En Partie' ? 'selected' : '') + '>En Partie</option>'
            + '</select></td>'
            + '<td><input type="date" class="form-control form-control-sm plan-date-acceptation" value="' + escHtmlAttr(plan.date_acceptation || '') + '"></td>'
            + '<td><input type="number" class="form-control form-control-sm plan-montant text-end" min="0" step="1" value="' + intVal(plan.montant) + '"></td>'
            + '<td class="text-center align-middle">'
            + '<button type="button" class="btn btn-sm btn-outline-danger btn-delete-plan" title="Supprimer">'
            + '<i class="bi bi-trash3"></i></button></td>'
            + '</tr>';

        $('#plansBody').append(html);
    }

    function savePlans() {
        const idUser = getSelectedUserId();
        if (window.IS_ADMIN && idUser === 0) {
            showToast('Veuillez sélectionner un dentiste.', 'warning');
            $('#filtreDentiste').focus();
            return;
        }

        const date  = fp.formatDate(fp.selectedDates[0], 'Y-m-d');
        const plans = [];
        let hasError = false;

        $('#plansBody tr').each(function () {
            const patient = $.trim($(this).find('.plan-patient').val() || '');
            if (patient === '') { return; }

            const montantDevis = intVal($(this).find('.plan-devis').val());
            const montant      = intVal($(this).find('.plan-montant').val());
            if (montantDevis < 0 || montant < 0) { hasError = true; return false; }

            plans.push({
                patient:          patient,
                montant_devis:    montantDevis,
                accepter:         $(this).find('.plan-accepter').val(),
                date_acceptation: $(this).find('.plan-date-acceptation').val(),
                montant:          montant
            });
        });

        if (hasError) { showToast('Les montants des plans doivent être positifs.', 'danger'); return; }

        $('#btnSavePlans').prop('disabled', true).html(
            '<span class="spinner-border spinner-border-sm me-1"></span> Enregistrement…'
        );

        const payload = { date: date, plans: plans };
        if (window.IS_ADMIN && idUser) { payload.id_utilisateur = idUser; }

        $.ajax({
            url:         window.SITE_ROOT + '/api/save_plan_traitement.php',
            method:      'POST',
            contentType: 'application/json',
            dataType:    'json',
            data:        JSON.stringify(payload)
        }).done(function (resp) {
            if (resp.success) {
                setDirty('plans', false);
                showToast('Plans de traitement enregistrés avec succès.', 'success');
                loadPlans(date, idUser);
            } else {
                showToast(resp.error || 'Erreur lors de l\'enregistrement des plans.', 'danger');
            }
        }).fail(function () {
            showToast('Erreur réseau lors de l\'enregistrement des plans.', 'danger');
        }).always(function () {
            $('#btnSavePlans').prop('disabled', false);
            if (isDirtyPlans) {
                $('#btnSavePlans').html('<i class="bi bi-floppy2-fill me-1"></i>Enregistrer plans'
                    + ' <span class="badge bg-warning text-dark ms-1">!</span>');
            }
        });
    }

    /* ================================================================
       Helpers
       ================================================================ */

    function updatePercentages() {
        $('.percentage-display').each(function () {
            const numId  = $(this).data('numerator');
            const denId  = $(this).data('denominator');
            const numVal = intVal($('#tableSaisie .saisie-input[data-action="' + numId + '"]').val());
            const denVal = intVal($('#tableSaisie .saisie-input[data-action="' + denId + '"]').val());

            if (denVal === 0) {
                $(this).text('-').removeClass('text-success text-danger text-warning');
            } else {
                const pct = Math.round((numVal / denVal) * 100);
                $(this).text(pct + ' %').removeClass('text-success text-danger text-warning');
                $(this).addClass(pct >= 80 ? 'text-success' : pct >= 50 ? 'text-warning' : 'text-danger');
            }
        });
    }

    function updateComputedRows() {
        $('.computed-value').each(function () {
            const ids = $(this).data('formula').toString().split(',').map(Number);
            let sum = 0;
            ids.forEach(function (id) {
                sum += intVal($('#tableSaisie .saisie-input[data-action="' + id + '"]').val());
            });
            $(this).text(sum.toLocaleString('fr-FR'));
        });
    }

    function parseFormula(formule) {
        if (!formule || formule[0] !== '=') return [];
        return formule.replace(/[^0-9+]/g, '').split('+').map(Number).filter(function (n) { return n > 0; });
    }

    function formatInputVal(val) { return parseInt(val, 10) || 0; }
    function intVal(val)         { return parseInt(val, 10) || 0; }

    function escHtml(str) {
        return $('<div>').text(str).html();
    }

    function escHtmlAttr(str) {
        return escHtml(str).replace(/"/g, '&quot;');
    }
});
