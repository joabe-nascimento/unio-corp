/**
 * Unio Signature — ritual de onboarding + microinteração de atestação.
 */
(function () {
    'use strict';

    var RITUAL_KEY = 'unio-ritual-v1-done';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function showSeloToast(title, sub) {
        var existing = qs('.unio-selo-toast');
        if (existing) existing.remove();
        var el = document.createElement('div');
        el.className = 'unio-selo-toast';
        el.setAttribute('role', 'status');
        el.innerHTML =
            '<span class="unio-selo unio-selo--compact">' +
            '<span class="unio-selo__mark" aria-hidden="true"><i class="fas fa-heart-pulse"></i></span>' +
            '</span>' +
            '<span><span class="unio-selo-toast__copy">' + title + '</span>' +
            (sub ? '<span class="unio-selo-toast__sub">' + sub + '</span>' : '') +
            '</span>';
        document.body.appendChild(el);
        requestAnimationFrame(function () {
            el.classList.add('is-on');
        });
        setTimeout(function () {
            el.classList.remove('is-on');
            setTimeout(function () { el.remove(); }, 400);
        }, 3200);
    }

    function initRitual() {
        var ritual = qs('[data-unio-ritual]');
        if (!ritual) return;
        try {
            if (localStorage.getItem(RITUAL_KEY) === '1') return;
        } catch (e) { /* ignore */ }
        ritual.classList.add('is-visible');
        var dismiss = qs('[data-unio-ritual-dismiss]', ritual);
        if (dismiss) {
            dismiss.addEventListener('click', function () {
                ritual.classList.remove('is-visible');
                try { localStorage.setItem(RITUAL_KEY, '1'); } catch (e2) { /* ignore */ }
            });
        }
        qsa('[data-unio-ritual-action]', ritual).forEach(function (btn) {
            btn.addEventListener('click', function () {
                try { localStorage.setItem(RITUAL_KEY, '1'); } catch (e3) { /* ignore */ }
            });
        });
    }

    function initAttest() {
        qsa('form.organismo-care-milestones__form, form[data-unio-attest]').forEach(function (form) {
            form.addEventListener('submit', function () {
                form.classList.add('is-attesting');
                var item = form.closest('.organismo-care-milestones__item');
                if (item) item.classList.add('is-sealing');
                try { sessionStorage.setItem('unio-selo-attest', '1'); } catch (e) { /* ignore */ }
            });
        });

        var shouldToast = false;
        try {
            shouldToast = sessionStorage.getItem('unio-selo-attest') === '1';
            if (shouldToast) sessionStorage.removeItem('unio-selo-attest');
        } catch (e4) { /* ignore */ }

        if (!shouldToast) {
            var flash = qs('.alert-success, .unio-flash--success, [data-flash="success"]');
            if (flash && /Trilha Unio|atestado/i.test(flash.textContent || '')) {
                shouldToast = true;
            }
        }
        if (shouldToast) {
            showSeloToast('Marco atestado na Trilha Unio', 'Saúde que acompanha.');
        }
    }

    function boot() {
        initRitual();
        initAttest();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
