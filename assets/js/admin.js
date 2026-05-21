/**
 * admin.js — Pages d'administration (utilisateurs, piliers, actions)
 * SELARL La Vespalienne — Suivi des Actes
 */
'use strict';

/* ================================================================
   Variables globales
   ================================================================ */
var pendingDeleteId = 0;

/* ================================================================
   Commun : Modal de suppression
   ================================================================ */
function confirmDelete(id, label) {
    pendingDeleteId = id;
    $('#deleteMessage').html(
        'Voulez-vous vraiment supprimer <strong>' + escHtml(label) + '</strong> ?'
        + '<br><small class="text-muted">Cette opération est irréversible.</small>'
    );
    $('#btnConfirmDelete').off('click').on('click', function () {
        doDelete(id);
    });
    new bootstrap.Modal(document.getElementById('modalDelete')).show();
}

function doDelete(id) {
    const page = window.ADMIN_PAGE;
    const url  = window.SITE_ROOT + '/admin/' + page + '.php';

    $.post(url, {
        action: 'delete',
        id:     id
    }, null, 'json')
    .done(function (resp) {
        bootstrap.Modal.getInstance(document.getElementById('modalDelete')).hide();
        if (resp.success) {
            $('#row-' + id).fadeOut(300, function () { $(this).remove(); });
            showToast('Suppression effectuée.', 'success');
        } else {
            showAlert(resp.error || 'Erreur lors de la suppression.', 'danger');
        }
    })
    .fail(function () {
        showAlert('Erreur réseau.', 'danger');
    });
}

/* ================================================================
   UTILISATEURS
   ================================================================ */
function openCreateModal() {
    const page = window.ADMIN_PAGE;

    if (page === 'actions') {
        $('#modalAction2').val('create');
        $('#modalId').val('0');
        $('#modalLibelle').val('');
        $('#modalOrd').val('0');
        $('#modalFormule').val('');
        $('#modalPilier').prop('selectedIndex', 0);
        $('#modalActionLabel').text('Ajouter une action');
        $('#modalAlertZone').hide();
        return;
    }

    // utilisateurs (default)
    $('#modalAction').val('create');
    $('#modalId').val('0');
    $('#modalLogin').val('');
    $('#modalRole').val('1');
    $('#modalPwd').val('').attr('placeholder', '');
    $('#pwdEditHint').hide();
    $('#pwdHint').show();
    $('#modalUtilisateurLabel').text('Ajouter un utilisateur');
    $('#modalAlertZone').hide();
}

function openEditModal(id, login, idRole, extra1, extra2) {
    const page = window.ADMIN_PAGE;

    if (page === 'utilisateurs') {
        $('#modalAction').val('update');
        $('#modalId').val(id);
        $('#modalLogin').val(login);
        $('#modalRole').val(idRole);
        $('#modalPwd').val('').attr('placeholder', '');
        $('#pwdEditHint').show();
        $('#pwdHint').hide();
        $('#modalUtilisateurLabel').text('Modifier l\'utilisateur');
        $('#modalAlertZone').hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalUtilisateur')).show();

    } else if (page === 'piliers') {
        $('#modalAction').val('update');
        $('#modalId').val(id);
        $('#modalLibelle').val(login); // login = libelle pour piliers
        $('#modalPilierLabel').text('Modifier le pilier');
        $('#modalAlertZone').hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPilier')).show();

    } else if (page === 'actions') {
        $('#modalAction2').val('update');
        $('#modalId').val(id);
        $('#modalLibelle').val(login);    // login = libelle de l'action
        $('#modalPilier').val(idRole);    // idRole = id_pilier
        $('#modalOrd').val(extra1 || 0);  // extra1 = ord
        $('#modalFormule').val(extra2 || ''); // extra2 = formule
        $('#modalActionLabel').text('Modifier une action');
        $('#modalAlertZone').hide();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAction')).show();
    }
}

function saveUtilisateur() {
    const action = $('#modalAction').val();
    const id     = $('#modalId').val();
    const login  = $.trim($('#modalLogin').val());
    const idRole = $('#modalRole').val();
    const pwd    = $('#modalPwd').val();

    if (!login) {
        showModalAlert('Le login est obligatoire.');
        return;
    }
    if (action === 'create' && !pwd) {
        showModalAlert('Le mot de passe est obligatoire.');
        return;
    }
    if (pwd && pwd.length < 6) {
        showModalAlert('Le mot de passe doit comporter au moins 6 caractères.');
        return;
    }

    const data = { action: action, id: id, login: login, id_role: idRole, pwd: pwd };

    $.post(window.SITE_ROOT + '/admin/utilisateurs.php', data, null, 'json')
    .done(function (resp) {
        if (resp.success) {
            showToast(action === 'create' ? 'Utilisateur créé.' : 'Utilisateur modifié.', 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalUtilisateur')).hide();
            setTimeout(function () { location.reload(); }, 700);
        } else {
            showModalAlert(resp.error || 'Erreur lors de l\'enregistrement.');
        }
    })
    .fail(function () {
        showModalAlert('Erreur réseau.');
    });
}

/* ================================================================
   PILIERS
   ================================================================ */
function savePilier() {
    const action  = $('#modalAction').val();
    const id      = $('#modalId').val();
    const libelle = $.trim($('#modalLibelle').val());

    if (!libelle) {
        showModalAlert('Le libellé est obligatoire.');
        return;
    }

    $.post(window.SITE_ROOT + '/admin/piliers.php',
        { action: action, id: id, libelle: libelle }, null, 'json')
    .done(function (resp) {
        if (resp.success) {
            showToast(action === 'create' ? 'Pilier créé.' : 'Pilier modifié.', 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalPilier')).hide();
            setTimeout(function () { location.reload(); }, 700);
        } else {
            showModalAlert(resp.error || 'Erreur.');
        }
    })
    .fail(function () { showModalAlert('Erreur réseau.'); });
}

/* ================================================================
   ACTIONS
   ================================================================ */
function saveAction() {
    const action   = $('#modalAction2').val();
    const id       = $('#modalId').val();
    const libelle  = $.trim($('#modalLibelle').val());
    const idPilier = $('#modalPilier').val();
    const formule  = $.trim($('#modalFormule').val());

    if (!libelle) {
        showModalAlert('Le libellé est obligatoire.');
        return;
    }
    if (formule && !/^=[\d+\s]+$/.test(formule)) {
        showModalAlert('Formule invalide. Format attendu : =1+2+3');
        return;
    }

    const ord = parseInt($('#modalOrd').val(), 10) || 0;

    $.post(window.SITE_ROOT + '/admin/actions.php',
        { action: action, id: id, libelle: libelle,
          id_pilier: idPilier, ord: ord, formule: formule }, null, 'json')
    .done(function (resp) {
        if (resp.success) {
            showToast(action === 'create' ? 'Action créée.' : 'Action modifiée.', 'success');
            bootstrap.Modal.getInstance(document.getElementById('modalAction')).hide();
            setTimeout(function () { location.reload(); }, 700);
        } else {
            showModalAlert(resp.error || 'Erreur.');
        }
    })
    .fail(function () { showModalAlert('Erreur réseau.'); });
}

/* ================================================================
   Helpers internes
   ================================================================ */
function showModalAlert(msg) {
    $('#modalAlertZone')
        .html('<div class="alert alert-danger py-2 mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>'
              + escHtml(msg) + '</div>')
        .show();
}

function escHtml(str) {
    return $('<div>').text(str || '').html();
}
