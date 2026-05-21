/**
 * app.js — Utilitaires communs
 * SELARL La Vespalienne — Suivi des Actes
 */
'use strict';

/* ---- Raccourci Ctrl+S global ---- */
document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault();
        if (typeof window.pageSaveHandler === 'function') {
            window.pageSaveHandler();
        }
    }
});

/* ---- Modales déplaçables ---- */
(function () {
    document.addEventListener('mousedown', function (e) {
        const header = e.target.closest('.modal-header');
        if (!header) { return; }
        const dialog = header.closest('.modal-dialog');
        if (!dialog) { return; }

        // Récupérer le décalage courant (transform précédent)
        const style  = window.getComputedStyle(dialog);
        const matrix = new DOMMatrix(style.transform);
        let currentX = matrix.m41;
        let currentY = matrix.m42;

        const startX = e.clientX - currentX;
        const startY = e.clientY - currentY;

        function onMove(ev) {
            dialog.style.transform = 'translate(' + (ev.clientX - startX) + 'px, ' + (ev.clientY - startY) + 'px)';
        }

        function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        }

        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    });

    // Réinitialiser la position à chaque ouverture
    document.addEventListener('show.bs.modal', function (e) {
        const dialog = e.target.querySelector('.modal-dialog');
        if (dialog) { dialog.style.transform = ''; }
    });
}());

/* ---- Configuration jQuery AJAX (token CSRF automatique) ---- */
$(function () {
    const csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
    $.ajaxSetup({
        headers: { 'X-CSRF-Token': csrfToken }
    });
});

/* ---- Formatage des valeurs ---- */

/**
 * Formate une valeur entière.
 * @param {number} val
 * @returns {string}
 */
function formatValeur(val) {
    const n = parseInt(val, 10);
    return (n === 0) ? '0' : n.toLocaleString('fr-FR');
}

/* ---- Notifications toast ---- */

/**
 * Affiche une notification toast.
 * @param {string} message
 * @param {string} type  'success' | 'danger' | 'warning' | 'info'
 */
function showToast(message, type) {
    type = type || 'info';
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }

    const icons = {
        success: 'bi-check-circle-fill',
        danger:  'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info:    'bi-info-circle-fill'
    };
    const icon = icons[type] || icons.info;
    const id = 'toast-' + Date.now();

    const html = '<div id="' + id + '" class="toast align-items-center text-white bg-' + type
        + ' border-0 mb-2 show" role="alert">'
        + '<div class="d-flex"><div class="toast-body">'
        + '<i class="bi ' + icon + ' me-2"></i>' + message
        + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" '
        + 'data-bs-dismiss="toast"></button></div></div>';

    $(container).append(html);
    // Supprimer automatiquement après 4s
    setTimeout(function () { $('#' + id).fadeOut(300, function () { $(this).remove(); }); }, 4000);
}

/**
 * Affiche un message dans une zone d'alerte (alertZone).
 * @param {string} message
 * @param {string} type
 * @param {string} zoneId
 */
function showAlert(message, type, zoneId) {
    zoneId = zoneId || 'alertZone';
    type   = type   || 'info';
    const icons = {
        success: 'bi-check-circle-fill',
        danger:  'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        info:    'bi-info-circle-fill'
    };
    const icon = icons[type] || icons.info;
    const html = '<div class="alert alert-' + type + ' alert-dismissible fade show py-2 mb-0" role="alert">'
        + '<i class="bi ' + icon + ' me-2"></i>' + message
        + '<button type="button" class="btn-close btn-sm" data-bs-dismiss="alert"></button>'
        + '</div>';
    $('#' + zoneId).html(html).show();
}
