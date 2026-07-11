/**
 * Prévia ao vivo do plano visual na emissão de carteirinha (clínica).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-clinic-carteirinha-preview]');
    var planoSelect = document.getElementById('plano');
    if (!root || !planoSelect) {
        return;
    }

    var planos;
    try {
        planos = JSON.parse(root.getAttribute('data-planos') || '{}');
    } catch (e) {
        return;
    }

    var themes = ['essencial', 'profissional', 'premium'];

    function widget() {
        return root.querySelector('[data-idcard-widget]');
    }

    function cardEl() {
        var w = widget();
        return w ? w.querySelector('[data-idcard-flip]') : null;
    }

    function applyPlano(plano) {
        var meta = planos[plano];
        var card = cardEl();
        if (!meta || !card) {
            return;
        }

        themes.forEach(function (theme) {
            card.classList.remove('mkt-idcard--' + theme);
        });
        card.classList.add('mkt-idcard--' + plano);

        var planTag = card.querySelector('.mkt-idcard__plan-tag');
        if (planTag) {
            planTag.textContent = meta.plano_label;
        }

        var role = card.querySelector('.mkt-idcard__role');
        if (role) {
            role.textContent = meta.role;
        }

        var header = card.querySelector('.mkt-idcard__header');
        var ribbon = card.querySelector('.mkt-idcard__ribbon');
        if (meta.ribbon) {
            if (!ribbon && header) {
                ribbon = document.createElement('span');
                ribbon.className = 'mkt-idcard__ribbon';
                header.appendChild(ribbon);
            }
            if (ribbon) {
                ribbon.textContent = meta.ribbon;
            }
        } else if (ribbon) {
            ribbon.remove();
        }

        var qr = card.querySelector('.mkt-idcard__qr');
        if (qr) {
            qr.classList.toggle('mkt-idcard__qr--premium', plano === 'premium');
        }

        var contactDl = card.querySelector('.mkt-idcard__dl--contact');
        var suporteRow = card.querySelector('[data-idcard-suporte-row]');
        if (meta.suporte && contactDl) {
            if (!suporteRow) {
                suporteRow = document.createElement('div');
                suporteRow.className = 'mkt-idcard__dl-item--wide';
                suporteRow.setAttribute('data-idcard-suporte-row', '');
                suporteRow.innerHTML = '<dt>Suporte</dt><dd data-idcard-suporte-text></dd>';
                contactDl.insertBefore(suporteRow, contactDl.firstChild);
            }
            var suporteText = suporteRow.querySelector('[data-idcard-suporte-text]');
            if (suporteText) {
                suporteText.textContent = meta.suporte;
            }
        } else if (suporteRow) {
            suporteRow.remove();
        }
    }

    planoSelect.addEventListener('change', function () {
        applyPlano(planoSelect.value);
    });

    applyPlano(planoSelect.value);
})();
