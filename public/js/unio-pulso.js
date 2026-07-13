(function () {
    'use strict';

    var root = document.getElementById('pulsoRoot');
    if (!root) return;

    var apiUrl = root.dataset.apiUrl;
    var refreshBtn = document.getElementById('pulsoRefreshBtn');
    var lumenCta = document.getElementById('pulsoLumenCta');
    var grid = document.getElementById('pulsoCenasGrid');
    var sinaisList = document.getElementById('pulsoSinaisList');
    var vitalBadge = document.getElementById('pulsoVitalBadge');

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderCenaCard(cena) {
        var estado = cena.estado || 'ativa';
        var mockClass = cena.mock ? ' pulso-cena-card--mock' : '';
        var linkClass = cena.url ? ' pulso-cena-card--link' : '';
        var tag = cena.url ? 'a' : 'article';
        var href = cena.url ? ' href="' + escapeHtml(cena.url) + '"' : '';

        var praticas = (cena.praticas || []).map(function (p) {
            return '<span class="pulso-pratica-tag">' + escapeHtml(String(p).replace(/_/g, ' ')) + '</span>';
        }).join('');

        var meta = '';
        if (cena.dias_aberta > 0) {
            meta += '<span>' + cena.dias_aberta + 'd</span>';
        }
        if (cena.condutor) {
            meta += '<span>' + escapeHtml(cena.condutor) + '</span>';
        }

        var tipo = escapeHtml(String(cena.tipo || 'paciente').replace(/_/g, ' '));

        return '<' + tag + ' class="pulso-cena-card pulso-cena-card--' + escapeHtml(estado) + mockClass + linkClass + '"' + href + '>' +
            '<div class="pulso-cena-card__head">' +
            '<span class="pulso-cena-card__tipo">' + tipo + '</span>' +
            (cena.mock ? '<span class="pulso-cena-card__badge">Demo</span>' : '') +
            '</div>' +
            '<h3 class="pulso-cena-card__title">' + escapeHtml(cena.titulo || '') + '</h3>' +
            (meta ? '<div class="pulso-cena-card__meta">' + meta + '</div>' : '') +
            (praticas ? '<div class="pulso-cena-card__praticas">' + praticas + '</div>' : '') +
            '</' + tag + '>';
    }

    function applySnapshot(data) {
        if (!data || !data.pulso) return;

        var ativasEl = document.querySelector('[data-recrutamento-pipeline-kpi="pulso_ativas"]');
        var aguardEl = document.querySelector('[data-recrutamento-pipeline-kpi="pulso_aguardando"]');
        if (ativasEl) ativasEl.textContent = String(data.pulso.cenas_ativas || 0);
        if (aguardEl) aguardEl.textContent = String(data.pulso.cenas_aguardando || 0);

        if (vitalBadge && data.pulso.nivel) {
            vitalBadge.className = 'pulso-vital pulso-vital--' + data.pulso.nivel;
            var label = vitalBadge.querySelector('.pulso-vital__label');
            if (label) label.textContent = data.pulso.nivel.charAt(0).toUpperCase() + data.pulso.nivel.slice(1);
        }

        if (sinaisList && Array.isArray(data.sinais)) {
            if (!data.sinais.length) {
                sinaisList.innerHTML = '<li class="org-kpi-strip__sublist-item"><span class="org-kpi-strip__sublist-lbl">Nenhum alerta no momento</span></li>';
            } else {
                sinaisList.innerHTML = data.sinais.map(function (s) {
                    return '<li class="org-kpi-strip__sublist-item">' +
                        '<span class="org-kpi-strip__sublist-val">' + escapeHtml(String(s.valor)) + '</span>' +
                        '<span class="org-kpi-strip__sublist-lbl">' + escapeHtml(s.rotulo || '') + '</span>' +
                        '</li>';
                }).join('');
            }
        }

        if (grid && Array.isArray(data.cenas)) {
            if (!data.cenas.length) {
                grid.innerHTML = '<div class="pulso-empty"><p>Nenhum paciente em acompanhamento ativo.</p></div>';
            } else {
                grid.innerHTML = data.cenas.map(renderCenaCard).join('');
            }
        }
    }

    function refresh() {
        if (!apiUrl) return;
        if (refreshBtn) refreshBtn.disabled = true;
        fetch(apiUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(applySnapshot)
            .catch(function () {})
            .finally(function () {
                if (refreshBtn) refreshBtn.disabled = false;
            });
    }

    if (refreshBtn) refreshBtn.addEventListener('click', refresh);
    if (lumenCta) {
        lumenCta.addEventListener('click', function () {
            document.getElementById('helixOpenBtn')?.click();
        });
    }

    setInterval(refresh, 60000);
})();
