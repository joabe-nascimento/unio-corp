/**
 * Abas do processo (Dados / Tarefas / Partes) — Unio Jurídico
 * Sidebar compacta que alterna o painel visível sem precisar rolar a página.
 */
(function (document, window) {
    'use strict';

    var wrap = document.querySelector('[data-jur-tabs]');
    if (!wrap) return;

    var btns = wrap.querySelectorAll('[data-tab-btn]');
    var panels = wrap.querySelectorAll('[data-tab-panel]');

    function activate(target) {
        btns.forEach(function (b) {
            var active = b.dataset.tabBtn === target;
            b.classList.toggle('is-active', active);
            b.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        panels.forEach(function (p) {
            p.classList.toggle('is-active', p.dataset.tabPanel === target);
        });
    }

    btns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activate(btn.dataset.tabBtn);
            if (window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#' + btn.dataset.tabBtn);
            }
        });
    });

    var hash = (window.location.hash || '').replace('#', '');
    if (hash && wrap.querySelector('[data-tab-btn="' + hash + '"]')) {
        activate(hash);
    }
})(document, window);
