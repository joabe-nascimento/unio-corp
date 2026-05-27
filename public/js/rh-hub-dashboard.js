/**
 * rh-hub-dashboard — animações e ticker ao vivo do hub RH
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

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function slideFingerprint(slides) {
        return slides.map(function (slide) {
            return [slide.tag, slide.title, slide.text, slide.url || ''].join('|');
        }).join('::');
    }

    function slidesFromDom(ticker) {
        return Array.prototype.map.call(
            ticker.querySelectorAll('[data-rh-ticker-slide]'),
            function (slide) {
                var link = slide.querySelector('.rh-hub-ticker-link');
                var tagEl = slide.querySelector('.rh-hub-ticker-tag');
                var titleEl = slide.querySelector('.rh-hub-ticker-title');
                var textEl = slide.querySelector('.rh-hub-ticker-text');
                var iconEl = slide.querySelector('.rh-hub-ticker-tag i');
                return {
                    tag: tagEl ? tagEl.textContent.trim() : 'RH',
                    title: titleEl ? titleEl.textContent.trim() : '',
                    text: textEl ? textEl.textContent.trim() : '',
                    icon: iconEl ? iconEl.className.replace(/^fas\s+/, '') : 'fa-circle-info',
                    tone: slide.getAttribute('data-tone') || 'blue',
                    url: link ? link.getAttribute('href') : null,
                    route_label: link ? link.textContent.replace(/\s*→?\s*$/, '').trim() : null,
                };
            }
        );
    }

    function buildSlideNode(slide, active) {
        var article = document.createElement('article');
        article.className = 'rh-hub-ticker-slide' + (active ? ' is-active' : '');
        article.setAttribute('data-rh-ticker-slide', '');
        article.setAttribute('data-tone', slide.tone || 'blue');
        article.setAttribute('data-slide-title', slide.title || '');
        if (!active) article.setAttribute('hidden', '');

        var head = document.createElement('div');
        head.className = 'rh-hub-ticker-slide-head';

        var tag = document.createElement('span');
        tag.className = 'rh-hub-ticker-tag rh-hub-ticker-tag--' + (slide.tone || 'blue');
        tag.innerHTML = '<i class="fas ' + escapeHtml(slide.icon || 'fa-circle-info') + '" aria-hidden="true"></i> ' + escapeHtml(slide.tag || 'RH');
        head.appendChild(tag);

        if (slide.url) {
            var link = document.createElement('a');
            link.className = 'rh-hub-ticker-link';
            link.href = slide.url;
            link.innerHTML = escapeHtml(slide.route_label || 'Ver mais') + ' <i class="fas fa-arrow-right" aria-hidden="true"></i>';
            head.appendChild(link);
        }

        var title = document.createElement('h3');
        title.className = 'rh-hub-ticker-title';
        title.textContent = slide.title || '';

        var text = document.createElement('p');
        text.className = 'rh-hub-ticker-text';
        text.textContent = slide.text || '';

        article.appendChild(head);
        article.appendChild(title);
        article.appendChild(text);

        return article;
    }

    function initTicker(ticker) {
        var viewport = ticker.querySelector('[data-rh-ticker-viewport]');
        var foot = ticker.querySelector('[data-rh-ticker-foot]');
        var dotsWrap = ticker.querySelector('[data-rh-ticker-dots]');
        var progressBar = ticker.querySelector('[data-rh-ticker-progress]');
        var liveBadge = ticker.querySelector('[data-rh-ticker-live]');
        var pollUrl = ticker.getAttribute('data-rh-ticker-url');
        var pollMs = parseInt(ticker.getAttribute('data-rh-ticker-poll'), 10);
        var interval = parseInt(ticker.getAttribute('data-rh-ticker-interval'), 10);

        if (!viewport) return;
        if (isNaN(interval) || interval < 3000) interval = 6000;
        if (isNaN(pollMs) || pollMs < 15000) pollMs = 45000;

        var slides = slidesFromDom(ticker);
        var fingerprint = slideFingerprint(slides);
        var index = 0;
        var rotateTimer = null;
        var pollTimer = null;
        var paused = false;
        var fetching = false;
        var lastFetch = Date.now();
        var liveHideTimer = null;

        function slidesEls() {
            return ticker.querySelectorAll('[data-rh-ticker-slide]');
        }

        function dotsEls() {
            return ticker.querySelectorAll('[data-rh-ticker-dot]');
        }

        function setActive(nextIndex) {
            var slideNodes = slidesEls();
            if (!slideNodes.length) return;

            index = (nextIndex + slideNodes.length) % slideNodes.length;

            slideNodes.forEach(function (slide, i) {
                var active = i === index;
                slide.classList.toggle('is-active', active);
                if (active) slide.removeAttribute('hidden');
                else slide.setAttribute('hidden', '');
            });

            dotsEls().forEach(function (dot, i) {
                var active = i === index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            if (progressBar) {
                progressBar.classList.remove('is-running');
                progressBar.style.animation = 'none';
                void progressBar.offsetWidth;
                progressBar.style.removeProperty('animation');
                if (!reducedMotion && !paused && slideNodes.length > 1) {
                    ticker.style.setProperty('--ticker-duration', interval + 'ms');
                    progressBar.classList.add('is-running');
                }
            }
        }

        function clearRotateTimer() {
            if (rotateTimer) {
                clearInterval(rotateTimer);
                rotateTimer = null;
            }
        }

        function startRotateTimer() {
            clearRotateTimer();
            if (paused || slidesEls().length <= 1) return;
            if (reducedMotion) {
                rotateTimer = setInterval(function () { setActive(index + 1); }, interval * 1.5);
                return;
            }
            rotateTimer = setInterval(function () { setActive(index + 1); }, interval);
        }

        function restartRotation() {
            if (progressBar && !reducedMotion && !paused && slidesEls().length > 1) {
                progressBar.classList.remove('is-running');
                void progressBar.offsetWidth;
                ticker.style.setProperty('--ticker-duration', interval + 'ms');
                progressBar.classList.add('is-running');
            }
            startRotateTimer();
        }

        function bindDotEvents() {
            dotsEls().forEach(function (dot, i) {
                dot.addEventListener('click', function () {
                    setActive(i);
                    restartRotation();
                });
            });
        }

        function toggleFoot(show) {
            if (!foot) return;
            if (show) foot.removeAttribute('hidden');
            else foot.setAttribute('hidden', '');
        }

        function showLiveBadge() {
            if (!liveBadge) return;
            liveBadge.classList.add('is-visible');
            liveBadge.removeAttribute('aria-hidden');
            clearTimeout(liveHideTimer);
            liveHideTimer = setTimeout(function () {
                liveBadge.classList.remove('is-visible');
                liveBadge.setAttribute('aria-hidden', 'true');
            }, 2200);
        }

        function renderSlides(newSlides, preferTitle) {
            if (!newSlides.length) return;

            var previousTitle = preferTitle || (slides[index] ? slides[index].title : '');
            slides = newSlides;

            var nextIndex = 0;
            if (previousTitle) {
                var found = newSlides.findIndex(function (slide) { return slide.title === previousTitle; });
                if (found >= 0) nextIndex = found;
            }

            viewport.innerHTML = '';
            newSlides.forEach(function (slide, i) {
                viewport.appendChild(buildSlideNode(slide, i === nextIndex));
            });

            if (dotsWrap) {
                dotsWrap.innerHTML = '';
                newSlides.forEach(function (slide, i) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'rh-hub-ticker-dot' + (i === nextIndex ? ' is-active' : '');
                    dot.setAttribute('data-rh-ticker-dot', '');
                    dot.setAttribute('role', 'tab');
                    dot.setAttribute('aria-selected', i === nextIndex ? 'true' : 'false');
                    dot.setAttribute('aria-label', 'Destaque ' + (i + 1) + ': ' + slide.title);
                    dotsWrap.appendChild(dot);
                });
            }

            toggleFoot(newSlides.length > 1);
            index = nextIndex;
            bindDotEvents();
            restartRotation();
        }

        function fetchSlides(reason) {
            if (!pollUrl || fetching) return;
            if (document.hidden && reason === 'poll') return;

            var now = Date.now();
            if (reason === 'visibility' && now - lastFetch < 5000) return;

            fetching = true;
            fetch(pollUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('ticker fetch failed');
                    return response.json();
                })
                .then(function (payload) {
                    lastFetch = Date.now();
                    var incoming = Array.isArray(payload.slides) ? payload.slides : [];
                    if (!incoming.length) return;

                    var nextFingerprint = slideFingerprint(incoming);
                    if (nextFingerprint === fingerprint) return;

                    var keepTitle = slides[index] ? slides[index].title : '';
                    fingerprint = nextFingerprint;
                    renderSlides(incoming, keepTitle);
                    showLiveBadge();
                })
                .catch(function () { /* silencioso — mantém snapshot atual */ })
                .finally(function () { fetching = false; });
        }

        bindDotEvents();
        setActive(0);
        restartRotation();

        ticker.addEventListener('mouseenter', function () {
            paused = true;
            clearRotateTimer();
            if (progressBar) progressBar.classList.remove('is-running');
        });

        ticker.addEventListener('mouseleave', function () {
            paused = false;
            restartRotation();
        });

        ticker.addEventListener('focusin', function () {
            paused = true;
            clearRotateTimer();
            if (progressBar) progressBar.classList.remove('is-running');
        });

        ticker.addEventListener('focusout', function () {
            if (ticker.contains(document.activeElement)) return;
            paused = false;
            restartRotation();
        });

        if (pollUrl) {
            pollTimer = setInterval(function () { fetchSlides('poll'); }, pollMs);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) fetchSlides('visibility');
            });
        }
    }

    bootPulseAnimations();
    root.querySelectorAll('[data-rh-ticker]').forEach(initTicker);
})();
