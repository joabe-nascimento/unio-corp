(function () {
    'use strict';

    var reveals = document.querySelectorAll('.jur-reveal');
    if (!reveals.length) return;

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        reveals.forEach(function (el) { observer.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('is-visible'); });
    }

    var heroPhoto = document.querySelector('.jur-hero__photo');
    if (heroPhoto && window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
        window.addEventListener('scroll', function () {
            var y = window.scrollY;
            if (y < window.innerHeight) {
                heroPhoto.style.transform = 'scale(' + (1.05 + y * 0.00015) + ') translateY(' + (y * 0.2) + 'px)';
            }
        }, { passive: true });
    }

    var header = document.getElementById('mktHeader');
    if (header) {
        window.addEventListener('scroll', function () {
            header.classList.toggle('is-scrolled', window.scrollY > 24);
        }, { passive: true });
    }

    var nav = document.getElementById('mktMainNav');
    var navToggle = document.getElementById('mktNavToggle');
    if (nav && navToggle) {
        var navIcon = navToggle.querySelector('[data-mkt-nav-icon]');
        var setNavOpen = function (open) {
            nav.classList.toggle('is-open', open);
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            navToggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
            if (navIcon) navIcon.className = open ? 'fas fa-xmark' : 'fas fa-bars';
            document.body.classList.toggle('mkt-nav-open', open);
        };
        navToggle.addEventListener('click', function () {
            setNavOpen(!nav.classList.contains('is-open'));
        });
        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { setNavOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setNavOpen(false);
        });
        window.addEventListener('resize', function () {
            if (window.matchMedia('(min-width: 1100px)').matches) setNavOpen(false);
        });
    }

    var counters = document.querySelectorAll('[data-jur-count]');
    if (counters.length && 'IntersectionObserver' in window) {
        var countObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                countObserver.unobserve(entry.target);
                var el = entry.target;
                var target = parseFloat(el.getAttribute('data-jur-count'));
                var suffix = el.getAttribute('data-jur-suffix') || '';
                var duration = 1200;
                var start = null;
                function step(ts) {
                    if (!start) start = ts;
                    var progress = Math.min((ts - start) / duration, 1);
                    var eased = 1 - Math.pow(1 - progress, 3);
                    var value = Math.round(target * eased * 10) / 10;
                    el.textContent = (value % 1 === 0 ? value.toFixed(0) : value.toFixed(1)) + suffix;
                    if (progress < 1) window.requestAnimationFrame(step);
                }
                window.requestAnimationFrame(step);
            });
        }, { threshold: 0.5 });
        counters.forEach(function (el) { countObserver.observe(el); });
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        var icon = document.getElementById('footerThemeIcon');
        if (icon) {
            icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }
        var btn = document.getElementById('footerThemeToggle');
        if (btn) {
            var label = theme === 'dark' ? 'Ativar tema claro' : 'Ativar tema escuro';
            btn.title = label;
            btn.setAttribute('aria-label', label);
        }
        try {
            localStorage.setItem('unio-theme', theme);
        } catch (e) { /* ignore */ }
    }

    var savedTheme = 'light';
    try {
        savedTheme = localStorage.getItem('unio-theme') || document.documentElement.getAttribute('data-theme') || 'light';
    } catch (e) {
        savedTheme = document.documentElement.getAttribute('data-theme') || 'light';
    }
    applyTheme(savedTheme);

    var footerThemeToggle = document.getElementById('footerThemeToggle');
    if (footerThemeToggle) {
        footerThemeToggle.addEventListener('click', function () {
            var current = document.documentElement.getAttribute('data-theme') || 'light';
            applyTheme(current === 'dark' ? 'light' : 'dark');
        });
    }
})();
