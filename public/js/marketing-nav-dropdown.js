(function () {
    'use strict';

    function initDropdown(root) {
        var trigger = root.querySelector('.mkt-nav-dropdown__trigger');
        var panel = root.querySelector('.mkt-nav-dropdown__panel');
        if (!trigger || !panel) {
            return;
        }

        function positionPanel() {
            if (panel.hasAttribute('hidden')) {
                return;
            }
            var rect = trigger.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.top = Math.round(rect.bottom + 8) + 'px';
            panel.style.left = Math.round(rect.left + rect.width / 2) + 'px';
            panel.style.transform = 'translateX(-50%)';
            panel.style.zIndex = '600';
        }

        function close() {
            root.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
            panel.setAttribute('hidden', 'hidden');
            panel.style.position = '';
            panel.style.top = '';
            panel.style.left = '';
            panel.style.transform = '';
        }

        function open() {
            document.querySelectorAll('[data-nav-dropdown].is-open').forEach(function (other) {
                if (other !== root) {
                    var t = other.querySelector('.mkt-nav-dropdown__trigger');
                    var p = other.querySelector('.mkt-nav-dropdown__panel');
                    other.classList.remove('is-open');
                    if (t) {
                        t.setAttribute('aria-expanded', 'false');
                    }
                    if (p) {
                        p.setAttribute('hidden', 'hidden');
                    }
                }
            });
            root.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            panel.removeAttribute('hidden');
            positionPanel();
        }

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (root.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        panel.querySelectorAll('[data-nav-product-link]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                var href = link.getAttribute('href');
                if (!href || href.indexOf('#') === -1) {
                    close();
                    return;
                }

                try {
                    var target = new URL(href, window.location.origin);
                    if (target.pathname === window.location.pathname && target.hash) {
                        var el = document.querySelector(target.hash);
                        if (el) {
                            e.preventDefault();
                            close();
                            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                            window.history.replaceState(null, '', target.pathname + target.search + target.hash);
                        }
                    }
                } catch (err) {
                    /* navegação normal */
                }
            });
        });

        window.addEventListener('resize', positionPanel);
        window.addEventListener('scroll', positionPanel, true);

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                close();
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                close();
            }
        });
    }

    function boot() {
        document.querySelectorAll('[data-nav-dropdown]').forEach(initDropdown);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
