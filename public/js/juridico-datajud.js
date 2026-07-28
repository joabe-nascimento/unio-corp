(function () {
    'use strict';

    function setup(root) {
        var url = root.getAttribute('data-consultar-url');
        var csrf = root.getAttribute('data-csrf');
        if (!url) return;

        var input = document.getElementById('jurDatajudNumero');
        var btn = document.getElementById('jurDatajudBtn');
        var out = document.getElementById('jurDatajudResultado');
        if (!input || !btn || !out) return;

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = String(str || '');
            return div.innerHTML;
        }

        function renderLoading() {
            out.innerHTML = '<div class="jur-datajud-loading"><i class="fas fa-circle-notch fa-spin" aria-hidden="true"></i> Consultando base nacional do DataJud…</div>';
        }

        function renderError(msg) {
            out.innerHTML = '<div class="jur-datajud-error"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> ' + escapeHtml(msg) + '</div>';
        }

        function renderResultado(data) {
            var html = '<div class="jur-datajud-card">';
            html += '<div class="jur-datajud-card__head">';
            html += '<strong>' + escapeHtml(data.numero) + '</strong>';
            html += '<span class="badge badge-soft-primary">' + escapeHtml(data.tribunal) + '</span>';
            html += '</div>';
            html += '<dl class="jur-datajud-card__meta">';
            if (data.classe) html += '<dt>Classe</dt><dd>' + escapeHtml(data.classe) + '</dd>';
            if (data.orgaoJulgador) html += '<dt>Órgão julgador</dt><dd>' + escapeHtml(data.orgaoJulgador) + '</dd>';
            if (data.dataAjuizamento) html += '<dt>Ajuizamento</dt><dd>' + escapeHtml(data.dataAjuizamento) + '</dd>';
            if (data.ultimaAtualizacao) html += '<dt>Base atualizada em</dt><dd>' + escapeHtml(data.ultimaAtualizacao) + '</dd>';
            html += '</dl>';

            if (data.movimentos && data.movimentos.length > 0) {
                html += '<h4 class="jur-datajud-card__title">Últimas movimentações</h4>';
                html += '<ul class="jur-datajud-timeline">';
                data.movimentos.forEach(function (mov) {
                    html += '<li><span class="jur-datajud-timeline__data">' + escapeHtml(mov.data || '—') + '</span>';
                    html += '<span class="jur-datajud-timeline__nome">' + escapeHtml(mov.nome) + (mov.complemento ? ' — ' + escapeHtml(mov.complemento) : '') + '</span></li>';
                });
                html += '</ul>';
            }
            html += '</div>';
            out.innerHTML = html;
        }

        function consultar() {
            var numero = (input.value || '').trim();
            if (!numero) {
                renderError('Informe o número do processo no padrão CNJ.');
                return;
            }

            renderLoading();
            btn.disabled = true;

            var body = new URLSearchParams();
            body.set('numero', numero);
            body.set('_token', csrf || '');

            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString(),
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    if (!result.ok || result.data.error) {
                        renderError(result.data.error || 'Não foi possível consultar o DataJud agora.');
                        return;
                    }
                    renderResultado(result.data);
                })
                .catch(function () {
                    renderError('Falha de conexão ao consultar o DataJud.');
                })
                .finally(function () {
                    btn.disabled = false;
                });
        }

        btn.addEventListener('click', consultar);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                consultar();
            }
        });
    }

    document.querySelectorAll('script[data-consultar-url]').forEach(function (script) {
        setup(script);
    });
})();
