/**
 * Checklist de onboarding — ocultar por usuário (localStorage).
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-onboarding-checklist]');
    if (!root || !root.hasAttribute('data-onboarding-dismissible')) {
        return;
    }

    var userId = root.getAttribute('data-user-id') || '0';
    var storageKey = 'unio-onboarding-dismissed-' + userId;

    try {
        if (localStorage.getItem(storageKey) === '1') {
            root.hidden = true;
            return;
        }
    } catch (e) { /* ignore */ }

    var dismissBtn = root.querySelector('[data-onboarding-dismiss]');
    if (!dismissBtn) {
        return;
    }

    dismissBtn.addEventListener('click', function () {
        root.hidden = true;
        try {
            localStorage.setItem(storageKey, '1');
        } catch (e) { /* quota */ }
    });
})();
