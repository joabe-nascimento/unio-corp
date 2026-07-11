(function () {
    'use strict';

    if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.mkt-reveal').forEach(function (el) {
            io.observe(el);
        });
    } else {
        document.querySelectorAll('.mkt-reveal').forEach(function (el) {
            el.classList.add('is-visible');
        });
    }

    document.querySelectorAll('.mkt-rich-day-card--interactive, .mkt-pillar-day--interactive').forEach(function (details) {
        details.addEventListener('toggle', function () {
            if (details.open) {
                document.querySelectorAll('.mkt-rich-day-card--interactive, .mkt-pillar-day--interactive').forEach(function (other) {
                    if (other !== details && other.open) {
                        other.open = false;
                    }
                });
            }
        });
    });

    document.querySelectorAll('.mkt-rich-module-card__bg, .mkt-studio-info__thumb').forEach(function (img) {
        img.addEventListener('error', function () {
            var fallback = img.getAttribute('data-fallback') || '/images/marketing/modules/fallback.jpg';
            if (img.src.indexOf(fallback) === -1) {
                img.src = fallback;
            }
        });
    });

    var faqRoot = document.querySelector('[data-faq-personas]');
    if (faqRoot) {
        var tabs = faqRoot.querySelectorAll('[data-faq-tab]');
        var panels = faqRoot.querySelectorAll('[data-faq-panel]');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var target = tab.getAttribute('data-faq-tab');

                tabs.forEach(function (btn) {
                    var active = btn === tab;
                    btn.classList.toggle('is-active', active);
                    btn.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    var show = panel.getAttribute('data-faq-panel') === target;
                    panel.classList.toggle('is-active', show);
                    if (show) {
                        panel.removeAttribute('hidden');
                    } else {
                        panel.setAttribute('hidden', 'hidden');
                    }
                });
            });
        });
    }

    document.querySelectorAll('.mkt-live-pulse').forEach(function (el) {
        el.classList.add('is-live');
    });

    var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    document.querySelectorAll('[data-idcard-deck-wrap]').forEach(function (wrap) {
        var deck = wrap.querySelector('[data-idcard-deck]');
        var layers = wrap.querySelectorAll('[data-deck-layer]');
        var legendBtns = wrap.querySelectorAll('[data-deck-legend]');
        if (!deck || !layers.length || !legendBtns.length) {
            return;
        }

        var pinnedLayer = 3;

        function applyLayer(index) {
            wrap.classList.remove('is-layer-1', 'is-layer-2', 'is-layer-3');
            wrap.classList.add('is-layer-' + index);

            layers.forEach(function (layer) {
                var n = parseInt(layer.getAttribute('data-deck-layer'), 10);
                var active = n === index;
                layer.classList.toggle('is-active', active);
                layer.setAttribute('aria-pressed', active ? 'true' : 'false');
            });

            legendBtns.forEach(function (btn) {
                var n = parseInt(btn.getAttribute('data-deck-legend'), 10);
                var active = n === index;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        }

        function selectLayer(index, pin) {
            if (pin) {
                pinnedLayer = index;
            }
            applyLayer(index);
        }

        applyLayer(pinnedLayer);

        layers.forEach(function (layer) {
            var index = parseInt(layer.getAttribute('data-deck-layer'), 10);
            layer.addEventListener('click', function () {
                selectLayer(index, true);
            });
        });

        legendBtns.forEach(function (btn) {
            var index = parseInt(btn.getAttribute('data-deck-legend'), 10);
            btn.addEventListener('click', function () {
                selectLayer(index, true);
            });
            btn.addEventListener('mouseenter', function () {
                applyLayer(index);
            });
            btn.addEventListener('mouseleave', function () {
                applyLayer(pinnedLayer);
            });
            btn.addEventListener('focus', function () {
                applyLayer(index);
            });
            btn.addEventListener('blur', function () {
                if (!wrap.contains(document.activeElement)) {
                    applyLayer(pinnedLayer);
                }
            });
        });

        if (!reducedMotion) {
            deck.addEventListener('mousemove', function (e) {
                var rect = deck.getBoundingClientRect();
                var x = (e.clientX - rect.left) / rect.width - 0.5;
                var y = (e.clientY - rect.top) / rect.height - 0.5;
                deck.style.setProperty('--deck-tilt-x', (x * 10).toFixed(2) + 'deg');
                deck.style.setProperty('--deck-tilt-y', (y * -5).toFixed(2) + 'deg');
            });

            deck.addEventListener('mouseleave', function () {
                deck.style.removeProperty('--deck-tilt-x');
                deck.style.removeProperty('--deck-tilt-y');
            });
        }
    });

})();
