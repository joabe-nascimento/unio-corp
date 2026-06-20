/**
 * Login — logotipo SVG animado com GSAP (https://gsap.com)
 * Carrega logo-completa.svg inline e anima ícone + wordmark no DOM SVG.
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-auth-logo-anim][data-logo-src]');
    if (!root || typeof gsap === 'undefined') return;

    var stage = root.querySelector('[data-auth-logo-stage]');
    var src = root.getAttribute('data-logo-src');
    var label = root.getAttribute('data-logo-label') || 'Unio';
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var idleTweens = [];

    if (!stage || !src) return;

    function markReady() {
        root.classList.add('auth-logo-anim--ready');
        root.classList.remove('auth-logo-anim--loading');
    }

    function startIdle() {
        root.classList.add('auth-logo-anim--idle');
        if (reduce) return;

        idleTweens.push(
            gsap.to(stage, {
                y: -5,
                duration: 3.6,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
            })
        );

        var glow = root.querySelector('.auth-logo-anim__glow');
        if (glow) {
            idleTweens.push(
                gsap.to(glow, {
                    opacity: 0.42,
                    scale: 1.04,
                    duration: 3.2,
                    repeat: -1,
                    yoyo: true,
                    ease: 'sine.inOut',
                    transformOrigin: '21% 50%',
                })
            );
        }
    }

    function animateSvg(svg) {
        var wordmark = svg.querySelector('g[mask]');
        var rings = root.querySelectorAll('.auth-logo-anim__ring');
        var sparks = root.querySelectorAll('.auth-logo-anim__spark');
        var glow = root.querySelector('.auth-logo-anim__glow');

        if (reduce) {
            markReady();
            return;
        }

        gsap.set(svg, {
            opacity: 0,
            scale: 0.9,
            transformOrigin: '22% 50%',
            transformBox: 'fill-box',
        });

        if (wordmark) {
            gsap.set(wordmark, {
                opacity: 0,
                x: 48,
                transformOrigin: '424px 221px',
                transformBox: 'fill-box',
            });
        }

        gsap.set(rings, {
            scale: 0.4,
            opacity: 0,
            transformOrigin: '50% 50%',
        });
        gsap.set(sparks, { opacity: 0, scale: 0.3, x: 0, y: 0 });
        if (glow) {
            gsap.set(glow, { opacity: 0, scale: 0.85, transformOrigin: '21% 50%' });
        }

        var tl = gsap.timeline({
            defaults: { ease: 'power3.out' },
            onComplete: startIdle,
        });

        tl.to(root, { opacity: 1, duration: 0.3 }, 0);

        if (glow) {
            tl.to(glow, { opacity: 0.38, scale: 1, duration: 1.2, ease: 'power2.out' }, 0.05);
        }

        tl.to(
            svg,
            {
                opacity: 1,
                scale: 1,
                duration: 1.05,
                ease: 'back.out(1.4)',
            },
            0.1
        );

        tl.to(
            rings,
            {
                scale: 1.35,
                opacity: 0,
                duration: 1.2,
                stagger: 0.14,
                ease: 'power2.out',
            },
            0.15
        );

        tl.to(
            sparks,
            {
                opacity: 0.85,
                scale: 1,
                x: function (i) { return [44, -32, 24][i] || 0; },
                y: function (i) { return [-28, -18, 30][i] || 0; },
                duration: 0.7,
                stagger: 0.1,
                ease: 'power2.out',
            },
            0.35
        );

        tl.to(sparks, { opacity: 0, duration: 0.3, stagger: 0.04 }, 0.95);

        if (wordmark) {
            tl.to(
                wordmark,
                {
                    opacity: 1,
                    x: 0,
                    duration: 0.9,
                    ease: 'power4.out',
                },
                0.52
            );
        }
    }

    root.classList.add('auth-logo-anim--loading');

    fetch(src, { credentials: 'same-origin' })
        .then(function (res) {
            if (!res.ok) throw new Error('logo fetch failed');
            return res.text();
        })
        .then(function (svgText) {
            stage.innerHTML = svgText;
            var svg = stage.querySelector('svg');
            if (!svg) throw new Error('invalid svg');

            svg.classList.add('auth-logo-anim__svg');
            svg.setAttribute('role', 'img');
            svg.setAttribute('aria-label', label);
            svg.removeAttribute('width');
            svg.removeAttribute('height');
            svg.style.width = '100%';
            svg.style.height = 'auto';
            svg.style.display = 'block';
            svg.style.overflow = 'visible';

            var assetBase = src.replace(/[^/]+$/, '');
            svg.querySelectorAll('[href], [xlink\\:href]').forEach(function (node) {
                var attr = node.hasAttribute('href') ? 'href' : 'xlink:href';
                var val = node.getAttribute(attr);
                if (!val || /^(https?:|data:|#|\/)/i.test(val)) {
                    return;
                }
                node.setAttribute(attr, assetBase + val.replace(/^\.\//, ''));
            });

            markReady();
            animateSvg(svg);
        })
        .catch(function () {
            stage.innerHTML =
                '<img src="' + src + '" alt="' + label.replace(/"/g, '&quot;') + '" class="auth-logo-anim__img" width="945" height="442" decoding="async">';
            markReady();
        });

    window.addEventListener('pagehide', function () {
        idleTweens.forEach(function (t) { t.kill(); });
    });
})();
