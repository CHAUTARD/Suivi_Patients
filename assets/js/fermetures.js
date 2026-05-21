/**
 * fermetures.js — Administration des périodes de fermeture du cabinet
 * SELARL La Vespalienne — Suivi des Actes
 */
'use strict';

$(function () {

    /* ---- Flatpickr ---- */
    var fpDebut = flatpickr('#fermetureDebut', {
        locale:     'fr',
        dateFormat: 'Y-m-d',
        altInput:   true,
        altFormat:  'd/m/Y'
    });
    var fpFin = flatpickr('#fermetureFin', {
        locale:     'fr',
        dateFormat: 'Y-m-d',
        altInput:   true,
        altFormat:  'd/m/Y'
    });

    /* ---- Chargement initial ---- */
    loadFermetures();

    /* ---- Événements boutons ---- */
    $('#btnAddFermeture').on('click', function () {
        openAddModal();
    });

    $('#btnSaveFermeture').on('click', function () {
        saveFermeture();
    });

    /* ================================================================
       Fonctions principales
       ================================================================ */

    function loadFermetures() {
        $.ajax({
            url:      window.SITE_ROOT + '/api/get_fermetures.php',
            method:   'GET',
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                renderTable(resp.fermetures);
            } else {
                showAlert(resp.error || 'Erreur lors du chargement.', 'danger');
                $('#fermeturesBody').html(
                    '<tr><td colspan="5" class="text-center py-3 text-danger">'
                    + 'Erreur de chargement.</td></tr>'
                );
            }
        }).fail(function () {
            showAlert('Erreur réseau.', 'danger');
        });
    }

    function renderTable(fermetures) {
        if (!fermetures || fermetures.length === 0) {
            $('#fermeturesBody').html(
                '<tr><td colspan="5" class="text-center py-4 text-muted">'
                + '<i class="bi bi-calendar-check me-2"></i>'
                + 'Aucune période de fermeture enregistrée.'
                + '</td></tr>'
            );
            return;
        }

        var today = new Date();
        today.setHours(0, 0, 0, 0);

        var html = '';
        fermetures.forEach(function (f) {
            var debut     = new Date(f.date_debut + 'T00:00:00');
            var fin       = new Date(f.date_fin   + 'T00:00:00');
            var nbJours   = Math.round((fin - debut) / 86400000) + 1;
            var motifHtml = f.motif
                ? escHtml(f.motif)
                : '<span class="text-muted fst-italic">—</span>';

            var badge = '';
            if (debut <= today && fin >= today) {
                badge = ' <span class="badge bg-danger ms-1">En cours</span>';
            } else if (debut > today) {
                badge = ' <span class="badge bg-primary ms-1">À venir</span>';
            }

            var rowClass = fin < today ? ' class="text-muted"' : '';

            html += '<tr id="row-' + f.id_fermeture + '"' + rowClass + '>'
                + '<td>' + formatDateFr(f.date_debut) + '</td>'
                + '<td>' + formatDateFr(f.date_fin)   + '</td>'
                + '<td>' + nbJours + ' jour' + (nbJours > 1 ? 's' : '') + badge + '</td>'
                + '<td>' + motifHtml + '</td>'
                + '<td class="text-center">'
                +   '<button class="btn btn-outline-primary btn-sm me-1 btn-edit"'
                +     ' data-id="'    + f.id_fermeture + '"'
                +     ' data-debut="' + escHtml(f.date_debut)  + '"'
                +     ' data-fin="'   + escHtml(f.date_fin)    + '"'
                +     ' data-motif="' + escHtml(f.motif || '') + '"'
                +     ' title="Modifier"><i class="bi bi-pencil"></i></button>'
                +   '<button class="btn btn-outline-danger btn-sm btn-del"'
                +     ' data-id="'    + f.id_fermeture + '"'
                +     ' data-label="' + escHtml(formatDateFr(f.date_debut) + ' → ' + formatDateFr(f.date_fin)) + '"'
                +     ' title="Supprimer"><i class="bi bi-trash3"></i></button>'
                + '</td>'
                + '</tr>';
        });

        $('#fermeturesBody').html(html);

        /* Délégation des événements sur les boutons */
        $('#fermeturesBody')
            .off('click', '.btn-edit').on('click', '.btn-edit', function () {
                openEditModal($(this));
            })
            .off('click', '.btn-del').on('click', '.btn-del', function () {
                openDeleteModal(
                    parseInt($(this).data('id'), 10),
                    $(this).data('label')
                );
            });
    }

    /* ---- Ouverture modales ---- */

    function openAddModal() {
        $('#fermetureId').val('0');
        fpDebut.clear();
        fpFin.clear();
        $('#fermetureMotif').val('');
        $('#modalFermetureLabel').text('Ajouter une période de fermeture');
        $('#modalAlertZone').hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFermeture')).show();
    }

    function openEditModal($btn) {
        $('#fermetureId').val($btn.data('id'));
        fpDebut.setDate($btn.data('debut'), false);
        fpFin.setDate($btn.data('fin'),   false);
        $('#fermetureMotif').val($btn.data('motif'));
        $('#modalFermetureLabel').text('Modifier la période de fermeture');
        $('#modalAlertZone').hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFermeture')).show();
    }

    function openDeleteModal(id, label) {
        $('#deleteMessage').html(
            'Voulez-vous vraiment supprimer la période '
            + '<strong>' + escHtml(label) + '</strong> ?'
            + '<br><small class="text-muted">Cette opération est irréversible.</small>'
        );
        $('#btnConfirmDelete').off('click').on('click', function () {
            doDelete(id);
        });
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDelete')).show();
    }

    /* ---- Sauvegarde ---- */

    function saveFermeture() {
        var id    = parseInt($('#fermetureId').val(), 10);
        var debut = fpDebut.selectedDates[0];
        var fin   = fpFin.selectedDates[0];
        var motif = $.trim($('#fermetureMotif').val());

        if (!debut) { showModalAlert('La date de début est obligatoire.'); return; }
        if (!fin)   { showModalAlert('La date de fin est obligatoire.');   return; }
        if (fin < debut) {
            showModalAlert('La date de fin doit être supérieure ou égale à la date de début.');
            return;
        }

        $.ajax({
            url:         window.SITE_ROOT + '/api/save_fermeture.php',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify({
                id:         id,
                date_debut: toISO(debut),
                date_fin:   toISO(fin),
                motif:      motif
            }),
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                bootstrap.Modal.getInstance(
                    document.getElementById('modalFermeture')
                ).hide();
                showToast(resp.message || 'Enregistré.', 'success');
                loadFermetures();
            } else {
                showModalAlert(resp.error || 'Erreur lors de l\'enregistrement.');
            }
        }).fail(function () {
            showModalAlert('Erreur réseau.');
        });
    }

    /* ---- Suppression ---- */

    function doDelete(id) {
        $.ajax({
            url:         window.SITE_ROOT + '/api/delete_fermeture.php',
            method:      'POST',
            contentType: 'application/json',
            data:        JSON.stringify({ id: id }),
            dataType:    'json'
        }).done(function (resp) {
            bootstrap.Modal.getInstance(document.getElementById('modalDelete')).hide();
            if (resp.success) {
                $('#row-' + id).fadeOut(300, function () {
                    $(this).remove();
                    if ($('#fermeturesBody tr').length === 0) {
                        loadFermetures(); // affiche le message "aucune période"
                    }
                });
                showToast(resp.message || 'Période supprimée.', 'success');
            } else {
                showAlert(resp.error || 'Erreur lors de la suppression.', 'danger');
            }
        }).fail(function () {
            showAlert('Erreur réseau.', 'danger');
        });
    }

    /* ================================================================
       Helpers
       ================================================================ */

    function showModalAlert(msg) {
        $('#modalAlertZone')
            .html('<div class="alert alert-danger py-2 mb-0">'
                + '<i class="bi bi-exclamation-triangle-fill me-2"></i>'
                + escHtml(msg) + '</div>')
            .show();
    }

    function escHtml(str) {
        return $('<div>').text(String(str || '')).html();
    }

    function formatDateFr(isoStr) {
        if (!isoStr) { return '—'; }
        var p = isoStr.split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : isoStr;
    }

    function toISO(date) {
        var y = date.getFullYear();
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return y + '-' + m + '-' + d;
    }
});
