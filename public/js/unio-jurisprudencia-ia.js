/**
 * Jurisprudência IA — pesquisa jurisprudencial com a Sasha (JurisFlow).
 * Busca teses/julgados estruturados e permite salvar sugestões na biblioteca
 * do escritório com um clique.
 */
(function (document) {
    'use strict';

    var panel = document.getElementById('jurIaSearch');
    if (!panel) return;

    var form = document.getElementById('jurIaSearchForm');
    var submitBtn = document.getElementById('jurIaSubmit');
    var loadingEl = document.getElementById('jurIaLoading');
    var loadingTextEl = document.getElementById('jurIaLoadingText');
    var errorEl = document.getElementById('jurIaError');
    var resultsEl = document.getElementById('jurIaResults');
    var resultsGrid = document.getElementById('jurIaResultsGrid');
    var disclaimerEl = document.getElementById('jurIaDisclaimer');

    var searchUrl = panel.dataset.searchUrl;
    var saveUrl = panel.dataset.saveUrl;
    var token = panel.dataset.token;

    var LOADING_STEPS = [
        'Sasha está pesquisando…',
        'Consultando STF, STJ, TST e TRTs…',
        'Lendo súmulas e precedentes…',
        'Redigindo resumos e citações…'
    ];

    var loadingTimer = null;

    function startLoadingCycle() {
        var i = 0;
        loadingTextEl.textContent = LOADING_STEPS[0];
        loadingTimer = window.setInterval(function () {
            i = (i + 1) % LOADING_STEPS.length;
            loadingTextEl.textContent = LOADING_STEPS[i];
        }, 1600);
    }

    function stopLoadingCycle() {
        if (loadingTimer) {
            window.clearInterval(loadingTimer);
            loadingTimer = null;
        }
    }

    function setLoading(loading) {
        loadingEl.hidden = !loading;
        submitBtn.disabled = loading;
        submitBtn.classList.toggle('is-loading', loading);
        if (loading) {
            startLoadingCycle();
        } else {
            stopLoadingCycle();
        }
    }

    function showError(message) {
        errorEl.textContent = message;
        errorEl.hidden = false;
    }

    function clearError() {
        errorEl.hidden = true;
        errorEl.textContent = '';
    }

    function relevanciaBadge(relevancia) {
        var map = {
            alta: { label: 'Alta', cls: 'badge-danger' },
            media: { label: 'Média', cls: 'badge-warning' },
            baixa: { label: 'Baixa', cls: 'badge-secondary' }
        };
        var cfg = map[relevancia] || map.media;
        return '<span class="badge ' + cfg.cls + '">' + cfg.label + '</span>';
    }

    function escapeHtml(value) {
        var div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function buildCard(item, index) {
        var card = document.createElement('article');
        card.className = 'jur-ia-card';
        card.innerHTML =
            '<div class="jur-ia-card__head">' +
                '<span class="badge badge-soft-secondary"><i class="fas fa-landmark-dome mr-1" aria-hidden="true"></i>' + escapeHtml(item.tribunal) + '</span>' +
                relevanciaBadge(item.relevancia) +
            '</div>' +
            '<h3 class="jur-ia-card__title">' + escapeHtml(item.tema) + '</h3>' +
            (item.resultado ? '<p class="jur-ia-card__resultado"><i class="fas fa-gavel mr-1" aria-hidden="true"></i>' + escapeHtml(item.resultado) + '</p>' : '') +
            (item.resumo ? '<p class="jur-ia-card__resumo">' + escapeHtml(item.resumo) + '</p>' : '') +
            (item.referencia ? '<p class="jur-ia-card__referencia"><i class="fas fa-quote-left mr-1" aria-hidden="true"></i>' + escapeHtml(item.referencia) + '</p>' : '') +
            '<div class="jur-ia-card__actions">' +
                '<button type="button" class="btn-unio btn-sm jur-ia-card__save" data-index="' + index + '">' +
                    '<i class="fas fa-bookmark mr-1" aria-hidden="true"></i> Salvar na biblioteca' +
                '</button>' +
                '<button type="button" class="btn-unio-ghost btn-sm jur-ia-card__copy" title="Copiar citação">' +
                    '<i class="fas fa-copy" aria-hidden="true"></i>' +
                '</button>' +
            '</div>';

        var saveBtn = card.querySelector('.jur-ia-card__save');
        saveBtn.addEventListener('click', function () {
            saveSuggestion(item, saveBtn, card);
        });

        var copyBtn = card.querySelector('.jur-ia-card__copy');
        copyBtn.addEventListener('click', function () {
            var texto = item.tema + (item.referencia ? ' — ' + item.referencia : '') + (item.tribunal ? ' (' + item.tribunal + ')' : '');
            var done = function () {
                var original = copyBtn.innerHTML;
                copyBtn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
                window.setTimeout(function () { copyBtn.innerHTML = original; }, 1400);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(done);
            } else {
                done();
            }
        });

        return card;
    }

    function saveSuggestion(item, btn, card) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-1" aria-hidden="true"></i> Salvando…';

        fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: token, sugestao: item })
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                if (!result.ok || result.data.error) {
                    throw new Error(result.data && result.data.error ? result.data.error : 'Não foi possível salvar.');
                }
                btn.innerHTML = '<i class="fas fa-check mr-1" aria-hidden="true"></i> Salvo na biblioteca';
                card.classList.add('jur-ia-card--saved');
                window.setTimeout(function () { window.location.reload(); }, 900);
            })
            .catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-bookmark mr-1" aria-hidden="true"></i> Salvar na biblioteca';
                showError(err.message || 'Não foi possível salvar a sugestão.');
            });
    }

    function renderResults(resultados, disclaimer) {
        resultsGrid.innerHTML = '';
        resultados.forEach(function (item, index) {
            resultsGrid.appendChild(buildCard(item, index));
        });
        disclaimerEl.innerHTML = '<i class="fas fa-circle-info mr-1" aria-hidden="true"></i> ' + escapeHtml(disclaimer);
        resultsEl.hidden = resultados.length === 0;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearError();
        resultsEl.hidden = true;

        var tema = document.getElementById('jurIaTema').value.trim();
        if (!tema) return;

        var tribunal = document.getElementById('jurIaTribunal').value;
        var periodo = document.getElementById('jurIaPeriodo').value;

        setLoading(true);

        fetch(searchUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ _token: token, tema: tema, tribunal: tribunal, periodo: periodo })
        })
            .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
            .then(function (result) {
                setLoading(false);
                if (!result.ok || result.data.error) {
                    throw new Error(result.data && result.data.error ? result.data.error : 'Não foi possível pesquisar agora.');
                }
                renderResults(result.data.resultados || [], result.data.disclaimer || '');
            })
            .catch(function (err) {
                setLoading(false);
                showError(err.message || 'Não foi possível pesquisar agora. Tente novamente em instantes.');
            });
    });
})(document);
