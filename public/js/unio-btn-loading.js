/**
 * Estado de carregamento em botões (Unio) — spinner + label.
 * Auto: formulários em offcanvas e em body.org-juridico.
 */
(function (window, document) {
    'use strict';

    function inferLabel(btn) {
        var custom = btn.getAttribute('data-loading-label');
        if (custom) return custom;
        var text = (btn.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (text.indexOf('salvar') !== -1) return 'Salvando…';
        if (text.indexOf('criar') !== -1) return 'Criando…';
        if (text.indexOf('enviar') !== -1) return 'Enviando…';
        if (text.indexOf('registrar') !== -1) return 'Registrando…';
        if (text.indexOf('remover') !== -1) return 'Removendo…';
        if (text.indexOf('adicionar') !== -1) return 'Adicionando…';
        if (text.indexOf('sasha') !== -1) return 'Consultando Sasha…';
        return 'Carregando…';
    }

    function resolveSubmitButton(form) {
        if (!form || !form.id) {
            return form ? form.querySelector('button[type="submit"], input[type="submit"]') : null;
        }
        var external = document.querySelector(
            'button[type="submit"][form="' + form.id + '"], input[type="submit"][form="' + form.id + '"]'
        );
        return external || form.querySelector('button[type="submit"], input[type="submit"]');
    }

    function start(btn, label) {
        if (!btn || btn.classList.contains('is-loading')) return;
        btn._unioBtnOrigHtml = btn.innerHTML;
        btn._unioBtnOrigDisabled = btn.disabled;
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.setAttribute('aria-busy', 'true');
        var text = label || inferLabel(btn);
        btn.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>' + text + '</span>';
    }

    function stop(btn) {
        if (!btn || !btn.classList.contains('is-loading')) return;
        if (btn._unioBtnOrigHtml !== undefined) {
            btn.innerHTML = btn._unioBtnOrigHtml;
        }
        btn.disabled = !!btn._unioBtnOrigDisabled;
        btn.classList.remove('is-loading');
        btn.removeAttribute('aria-busy');
        delete btn._unioBtnOrigHtml;
        delete btn._unioBtnOrigDisabled;
    }

    function shouldAutoLoadForm(form) {
        if (!form || form.tagName !== 'FORM') return false;
        if (form.dataset.unioNoLoading !== undefined) return false;
        if (form.id === 'helixForm' || form.closest('.helix-panel')) return false;
        if (form.closest('[data-unio-offcanvas]')) return true;
        if (document.body.classList.contains('org-juridico')) return true;
        return false;
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!shouldAutoLoadForm(form)) return;
        var btn = resolveSubmitButton(form);
        if (btn) start(btn);
    }, true);

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-unio-btn-loading]');
        if (!btn || btn.classList.contains('is-loading')) return;
        if (btn.type === 'submit') return;
        start(btn);
    }, true);

    window.UnioBtnLoading = {
        start: start,
        stop: stop,
        inferLabel: inferLabel,
        wrap: function (btn, promise, label) {
            start(btn, label);
            return Promise.resolve(promise).finally(function () { stop(btn); });
        }
    };
}(window, document));
