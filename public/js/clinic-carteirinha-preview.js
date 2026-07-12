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

    function contactDl(card) {
        return card.querySelector('.mkt-idcard__dl--contact');
    }

    function findSuporteRows(card) {
        var dl = contactDl(card);
        if (!dl) {
            return [];
        }
        return Array.prototype.slice.call(dl.children).filter(function (row) {
            if (row.hasAttribute('data-idcard-suporte-row')) {
                return true;
            }
            var dt = row.querySelector('dt');
            return !!(dt && /suporte/i.test((dt.textContent || '').trim()));
        });
    }

    function ensureSingleSuporteRow(card, text) {
        var dl = contactDl(card);
        if (!dl) {
            return;
        }

        var rows = findSuporteRows(card);
        var row = rows[0] || null;

        rows.slice(1).forEach(function (extra) {
            extra.remove();
        });

        if (!row) {
            row = document.createElement('div');
            row.className = 'mkt-idcard__dl-item--wide';
            row.setAttribute('data-idcard-suporte-row', '');
            row.innerHTML = '<dt>Suporte</dt><dd data-idcard-suporte-text></dd>';
            dl.insertBefore(row, dl.firstChild);
        } else {
            row.setAttribute('data-idcard-suporte-row', '');
            var dd = row.querySelector('dd');
            if (dd && !dd.hasAttribute('data-idcard-suporte-text')) {
                dd.setAttribute('data-idcard-suporte-text', '');
            }
        }

        var suporteText = row.querySelector('[data-idcard-suporte-text]') || row.querySelector('dd');
        if (suporteText) {
            suporteText.textContent = text;
        }
    }

    function removeSuporteRows(card) {
        findSuporteRows(card).forEach(function (row) {
            row.remove();
        });
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

        if (meta.suporte) {
            ensureSingleSuporteRow(card, meta.suporte);
        } else {
            removeSuporteRows(card);
        }
    }

    planoSelect.addEventListener('change', function () {
        applyPlano(planoSelect.value);
    });

    applyPlano(planoSelect.value);
})();
