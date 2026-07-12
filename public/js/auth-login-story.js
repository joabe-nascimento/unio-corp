/**
 * Carrossel editorial do painel de marca (login Unio Saúde).
 * Auto-rotação com pause on hover/focus; respeita prefers-reduced-motion.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-auth-story]');
    if (!root) {
        return;
    }

    var slides = Array.prototype.slice.call(root.querySelectorAll('[data-story-slide]'));
    var dots = Array.prototype.slice.call(root.querySelectorAll('[data-story-dot]'));
    if (slides.length < 2) {
        return;
    }

    var intervalMs = parseInt(root.getAttribute('data-interval') || '6000', 10);
    var index = 0;
    var timer = null;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function show(i) {
        index = ((i % slides.length) + slides.length) % slides.length;
        slides.forEach(function (slide, n) {
            var on = n === index;
            slide.classList.toggle('is-active', on);
            if (on) {
                slide.removeAttribute('hidden');
            } else {
                slide.setAttribute('hidden', '');
            }
        });
        dots.forEach(function (dot, n) {
            var on = n === index;
            dot.classList.toggle('is-active', on);
            dot.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        root.setAttribute('data-active-tone', slides[index].getAttribute('data-tone') || '');
    }

    function next() {
        show(index + 1);
    }

    function stop() {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start() {
        if (reduced) {
            return;
        }
        stop();
        timer = setInterval(next, intervalMs);
    }

    dots.forEach(function (dot, n) {
        dot.addEventListener('click', function () {
            show(n);
            start();
        });
    });

    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', function (e) {
        if (!root.contains(e.relatedTarget)) {
            start();
        }
    });

    show(0);
    start();
})();
