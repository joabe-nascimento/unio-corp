/**
 * Biblioteca de Jurisprudência — favoritar, copiar citação e repetir
 * pesquisas do histórico direto no formulário de busca com IA.
 */
(function (document) {
    'use strict';

    function showToast(el, text) {
        var original = el.innerHTML;
        el.classList.add('is-copied');
        el.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
        window.setTimeout(function () {
            el.classList.remove('is-copied');
            el.innerHTML = original;
        }, 1400);
        if (text) { /* no-op: mantém assinatura simples para futura extensão */ }
    }

    document.addEventListener('click', function (e) {
        var favBtn = e.target.closest('.jur-favorito-btn');
        if (favBtn) {
            e.preventDefault();
            if (favBtn.disabled) return;
            favBtn.disabled = true;

            fetch(favBtn.dataset.favoritarUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: '_token=' + encodeURIComponent(favBtn.dataset.token)
            })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    favBtn.disabled = false;
                    if (!result.ok || result.data.error) {
                        throw new Error(result.data && result.data.error ? result.data.error : 'Não foi possível favoritar.');
                    }
                    var icon = favBtn.querySelector('i');
                    if (result.data.favorito) {
                        favBtn.classList.add('is-favorito');
                        icon.className = 'fas fa-star';
                        favBtn.title = 'Remover dos favoritos';
                    } else {
                        favBtn.classList.remove('is-favorito');
                        icon.className = 'far fa-star';
                        favBtn.title = 'Marcar como favorito';
                    }
                })
                .catch(function () {
                    favBtn.disabled = false;
                });
            return;
        }

        var copyBtn = e.target.closest('.jur-copiar-citacao-btn');
        if (copyBtn) {
            e.preventDefault();
            var text = copyBtn.dataset.citacao || '';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { showToast(copyBtn); });
            } else {
                var helper = document.createElement('textarea');
                helper.value = text;
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                try { document.execCommand('copy'); } catch (err) { /* ignore */ }
                document.body.removeChild(helper);
                showToast(copyBtn);
            }
            return;
        }

        var repetirBtn = e.target.closest('.jur-historico-item__repetir');
        if (repetirBtn) {
            e.preventDefault();
            var temaInput = document.getElementById('jurIaTema');
            var tribunalInput = document.getElementById('jurIaTribunal');
            var periodoInput = document.getElementById('jurIaPeriodo');
            var form = document.getElementById('jurIaSearchForm');
            if (!temaInput || !form) return;

            temaInput.value = repetirBtn.dataset.tema || '';
            if (tribunalInput && repetirBtn.dataset.tribunal) tribunalInput.value = repetirBtn.dataset.tribunal;
            if (periodoInput && repetirBtn.dataset.periodo) periodoInput.value = repetirBtn.dataset.periodo;

            temaInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        }
    });
})(document);
