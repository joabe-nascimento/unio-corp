/**
 * rh-hub-dashboard — animações do hub RH
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-rh-hub-dashboard]');
    if (!root) return;

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    root.querySelectorAll('.rh-hub-focus-item, .rh-hub-board-card').forEach(function (el, i) {
        el.style.animationDelay = (i * 0.035) + 's';
    });

    function easeOutCubic(t) {
        return 1 - Math.pow(1 - t, 3);
    }

    function animatePulse(container) {
        if (container.dataset.pulseAnimated) return;
        var ring = container.querySelector('[data-pulse-ring]');
        var valueEl = container.querySelector('[data-pulse-value]');
        if (!ring || !valueEl) return;

        var target = parseInt(container.getAttribute('data-pulse-score'), 10);
        if (isNaN(target)) target = 0;

        if (reducedMotion || target === 0) {
            ring.style.setProperty('--pulse-pct', String(target));
            valueEl.textContent = String(target);
            ring.classList.add('rh-hub-pulse-ring--animated');
            container.dataset.pulseAnimated = '1';
            return;
        }

        container.dataset.pulseAnimated = '1';
        var duration = 1400;
        var start = null;

        function finish() {
            ring.style.setProperty('--pulse-pct', String(target));
            valueEl.textContent = String(target);
            ring.classList.add('rh-hub-pulse-ring--animated');
        }

        function step(timestamp) {
            if (!start) start = timestamp;
            var progress = Math.min((timestamp - start) / duration, 1);
            var current = Math.round(easeOutCubic(progress) * target);
            ring.style.setProperty('--pulse-pct', String(current));
            valueEl.textContent = String(current);
            if (progress < 1) requestAnimationFrame(step);
            else finish();
        }

        requestAnimationFrame(step);
    }

    function bootPulseAnimations() {
        root.querySelectorAll('.rh-hub-pulse[data-pulse-score]').forEach(function (wrap) {
            if ('IntersectionObserver' in window) {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) return;
                        observer.disconnect();
                        animatePulse(entry.target);
                    });
                }, { threshold: 0.2 });
                observer.observe(wrap);
            } else {
                animatePulse(wrap);
            }
        });
    }

    bootPulseAnimations();
})();
