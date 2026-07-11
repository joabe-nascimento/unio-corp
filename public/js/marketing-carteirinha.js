/**
 * Página dedicada: Carteirinha digital + Guia médico
 */
(function () {
    'use strict';

    var secaoSelect = document.querySelector('[data-secao-select]');
    var planoSelect = document.querySelector('[data-plano-select]');
    var baseUrl = window.location.pathname;

    function showSecao(secao) {
        document.querySelectorAll('[data-secao-panel]').forEach(function (panel) {
            var show = panel.getAttribute('data-secao-panel') === secao;
            panel.classList.toggle('is-hidden', !show);
            if (show) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        });

        if (planoSelect) {
            var planWrap = planoSelect.closest('.mkt-carteirinha-sidebar');
            if (planWrap) {
                planWrap.style.display = secao === 'carterinha' ? '' : 'none';
            }
        }

        var params = new URLSearchParams(window.location.search);
        params.set('secao', secao);
        if (planoSelect && secao === 'carterinha') {
            params.set('plano', planoSelect.value);
        } else {
            params.delete('plano');
        }
        var qs = params.toString();
        window.history.replaceState({}, '', baseUrl + (qs ? '?' + qs : ''));
    }

    function showPlano(planoId) {
        document.querySelectorAll('[data-plan-panel]').forEach(function (panel) {
            var show = panel.getAttribute('data-plan-panel') === planoId;
            panel.classList.toggle('is-hidden', !show);
            if (show) {
                panel.removeAttribute('hidden');
                panel.querySelectorAll('[data-idcard-widget]').forEach(initIdcardWidget);
            } else {
                panel.setAttribute('hidden', 'hidden');
            }
        });

        document.querySelectorAll('[data-plan-detail]').forEach(function (detail) {
            var show = detail.getAttribute('data-plan-detail') === planoId;
            detail.classList.toggle('is-hidden', !show);
            if (show) {
                detail.removeAttribute('hidden');
            } else {
                detail.setAttribute('hidden', 'hidden');
            }
        });

        if (secaoSelect && secaoSelect.value === 'carterinha') {
            var params = new URLSearchParams(window.location.search);
            params.set('secao', 'carterinha');
            params.set('plano', planoId);
            window.history.replaceState({}, '', baseUrl + '?' + params.toString());
        }
    }

    function initIdcardWidget(widget) {
        if (widget.dataset.idcardReady === '1') {
            return;
        }
        widget.dataset.idcardReady = '1';

        var card = widget.querySelector('[data-idcard-flip]');
        var tabs = widget.querySelectorAll('[data-idcard-tab]');
        if (!card || !tabs.length) {
            return;
        }

        function setSide(side) {
            var flipped = side === 'verso';
            card.classList.toggle('is-flipped', flipped);
            card.setAttribute('aria-pressed', flipped ? 'true' : 'false');
            card.setAttribute('aria-label', flipped
                ? 'Carteira digital. Toque para ver a frente.'
                : 'Carteira digital. Toque para ver o verso.');

            tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-idcard-tab') === side;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.stopPropagation();
                setSide(tab.getAttribute('data-idcard-tab') || 'frente');
            });
        });

        card.addEventListener('click', function () {
            setSide(card.classList.contains('is-flipped') ? 'frente' : 'verso');
        });
    }

    if (secaoSelect) {
        secaoSelect.addEventListener('change', function () {
            showSecao(secaoSelect.value);
        });
        showSecao(secaoSelect.value);
    } else {
        var params = new URLSearchParams(window.location.search);
        showSecao(params.get('secao') || 'carterinha');
    }

    if (planoSelect) {
        planoSelect.addEventListener('change', function () {
            showPlano(planoSelect.value);
        });
        showPlano(planoSelect.value);
    } else if (!secaoSelect) {
        var planParams = new URLSearchParams(window.location.search);
        var initialPlano = planParams.get('plano');
        if (initialPlano) {
            showPlano(initialPlano);
        }
    }

    document.querySelectorAll('[data-idcard-widget]').forEach(initIdcardWidget);
})();
