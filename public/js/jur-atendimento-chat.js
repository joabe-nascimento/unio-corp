/**
 * Chat de atendimento — scroll, templates e Sasha com loading.
 */
(function (document, window) {
    'use strict';

    var thread = document.getElementById('jur-atendimento-thread');
    if (thread) thread.scrollTop = thread.scrollHeight;

    var tplSelect = document.getElementById('jurAtendimentoTemplateSelect');
    var corpo = document.getElementById('jurAtendimentoCorpo');
    if (tplSelect && corpo) {
        tplSelect.addEventListener('change', function () {
            var opt = tplSelect.options[tplSelect.selectedIndex];
            var url = opt.getAttribute('data-preview-url');
            if (!url) return;
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) { if (data.ok) corpo.value = data.texto; });
        });
    }

    var sashaBtn = document.getElementById('jurAtendimentoSugerirBtn');
    if (sashaBtn && corpo && window.UnioBtnLoading) {
        sashaBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('_token', sashaBtn.getAttribute('data-csrf'));
            window.UnioBtnLoading.wrap(
                sashaBtn,
                fetch(sashaBtn.getAttribute('data-url'), {
                    method: 'POST',
                    body: fd,
                    headers: { 'Accept': 'application/json' }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) corpo.value = data.sugestao;
                        else alert(data.erro || 'Sasha indisponível.');
                    })
                    .catch(function () { alert('Erro ao consultar a Sasha.'); }),
                'Consultando Sasha…'
            );
        });
    }
}(document, window));
