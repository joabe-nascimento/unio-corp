/**
 * Offcanvas — Novo projeto (abre à direita, sem trocar de página)
 */
(function () {
    'use strict';

    var offcanvas = document.querySelector('[data-dev-projeto-offcanvas]');
    if (!offcanvas) {
        return;
    }

    function open() {
        offcanvas.classList.add('is-open');
        offcanvas.setAttribute('aria-hidden', 'false');
        document.body.classList.add('huplex-offcanvas-open');
        var first = offcanvas.querySelector('input, select, textarea');
        if (first) {
            setTimeout(function () {
                first.focus();
            }, 280);
        }
    }

    function close() {
        offcanvas.classList.remove('is-open');
        offcanvas.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('huplex-offcanvas-open');
    }

    document.querySelectorAll('[data-dev-projeto-open]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            open();
        });
    });

    offcanvas.querySelectorAll('[data-dev-projeto-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            close();
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && offcanvas.classList.contains('is-open')) {
            close();
        }
    });

    if (offcanvas.classList.contains('is-open')) {
        document.body.classList.add('huplex-offcanvas-open');
    }
})();
