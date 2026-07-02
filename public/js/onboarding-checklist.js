/**
 * Checklist de onboarding — dismiss, tour integrado e atualização em tempo real.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-onboarding-checklist]');
    if (!root) {
        return;
    }

    var userId = root.getAttribute('data-user-id') || '0';
    var storageKey = 'unio-onboarding-dismissed-' + userId;

    function markShellTourStepDone() {
        var step = root.querySelector('[data-onboarding-step="shell_tour"]');
        if (!step || step.classList.contains('is-done')) {
            return;
        }

        step.classList.add('is-done');

        var check = step.querySelector('.onboarding-checklist__check');
        if (check) {
            check.innerHTML = '<i class="fas fa-check"></i>';
        }

        var cta = step.querySelector('.onboarding-checklist__cta, .onboarding-checklist__cta--tour');
        if (cta) {
            var badge = document.createElement('span');
            badge.className = 'onboarding-checklist__done-badge';
            badge.textContent = 'Feito';
            cta.replaceWith(badge);
        }

        var hint = step.querySelector('.onboarding-checklist__hint');
        if (hint) {
            hint.textContent = 'Você concluiu o tour do menu, busca e núcleos.';
        }

        updateProgress();
    }

    function updateProgress() {
        var steps = root.querySelectorAll('.onboarding-checklist__step');
        var total = steps.length;
        var completed = root.querySelectorAll('.onboarding-checklist__step.is-done').length;
        var percent = total > 0 ? Math.round((completed / total) * 100) : 100;

        var sub = root.querySelector('.onboarding-checklist__sub');
        if (sub) {
            sub.textContent = completed + ' de ' + total + ' concluídos';
        }

        var bar = root.querySelector('.onboarding-checklist__progress-bar');
        if (bar) {
            bar.style.width = percent + '%';
        }

        var progress = root.querySelector('.onboarding-checklist__progress');
        if (progress) {
            progress.setAttribute('aria-valuenow', String(percent));
        }
    }

    if (root.hasAttribute('data-onboarding-dismissible')) {
        try {
            if (localStorage.getItem(storageKey) === '1') {
                root.hidden = true;
            }
        } catch (e) { /* ignore */ }

        var dismissBtn = root.querySelector('[data-onboarding-dismiss]');
        if (dismissBtn) {
            dismissBtn.addEventListener('click', function () {
                root.hidden = true;
                try {
                    localStorage.setItem(storageKey, '1');
                } catch (e) { /* quota */ }
            });
        }
    }

    document.addEventListener('unio:shell-tour-complete', markShellTourStepDone);
})();
