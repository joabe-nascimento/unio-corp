/**
 * Tour contextual do shell — acionado sob demanda via hub de Ajuda.
 */
(function () {
    'use strict';

    var activeTargets = [];

    function parseConfig() {
        var el = document.getElementById('shellTourConfig');
        if (!el) {
            return null;
        }
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function storageKey(userId) {
        return 'unio-shell-tour-done-' + (userId || '0');
    }

    function markDone(userId) {
        try {
            localStorage.setItem(storageKey(userId), '1');
        } catch (e) { /* quota */ }
    }

    function clearDone(userId) {
        try {
            localStorage.removeItem(storageKey(userId));
        } catch (e) { /* ignore */ }
    }

    function isVisible(node) {
        if (!node) {
            return false;
        }
        var rect = node.getBoundingClientRect();
        if (rect.width < 1 || rect.height < 1) {
            return false;
        }
        var style = window.getComputedStyle(node);
        return style.display !== 'none' && style.visibility !== 'hidden' && style.opacity !== '0';
    }

    function mergeRects(a, b) {
        var top = Math.min(a.top, b.top);
        var left = Math.min(a.left, b.left);
        var right = Math.max(a.right, b.right);
        var bottom = Math.max(a.bottom, b.bottom);
        return {
            top: top,
            left: left,
            right: right,
            bottom: bottom,
            width: right - left,
            height: bottom - top,
        };
    }

    function unionHubPickerRect() {
        var groups = document.querySelectorAll('.sidebar-hub-group:not(.is-hidden)');
        if (!groups.length) {
            return null;
        }

        var rect = groups[0].getBoundingClientRect();
        for (var i = 1; i < groups.length; i++) {
            if (!isVisible(groups[i])) {
                continue;
            }
            rect = mergeRects(rect, groups[i].getBoundingClientRect());
        }

        return { node: groups[0], rect: rect, extraNodes: Array.prototype.slice.call(groups, 1) };
    }

    function clearActiveTarget() {
        activeTargets.forEach(function (node) {
            node.classList.remove('shell-tour-target-active');
        });
        activeTargets = [];
    }

    function roundedRectPath(x, y, w, h, r) {
        r = Math.max(0, Math.min(r, w / 2, h / 2));
        if (r <= 0) {
            return 'M' + x + ' ' + y + ' H' + (x + w) + ' V' + (y + h) + ' H' + x + ' Z';
        }

        return [
            'M', (x + r), y,
            'H', (x + w - r),
            'A', r, r, '0 0 1', (x + w), (y + r),
            'V', (y + h - r),
            'A', r, r, '0 0 1', (x + w - r), (y + h),
            'H', (x + r),
            'A', r, r, '0 0 1', x, (y + h - r),
            'V', (y + r),
            'A', r, r, '0 0 1', (x + r), y,
            'Z',
        ].join(' ');
    }

    function resolveHighlightRadius(step, width, height) {
        if (step.highlight === 'circle') {
            return Math.min(width, height) / 2;
        }
        if (typeof step.radius === 'number') {
            return step.radius;
        }
        return 12;
    }

    function applyBackdropHole(backdrop, spotlight, rect, pad, radius) {
        var bleed = 1;
        var x = Math.round(rect.left - pad - bleed);
        var y = Math.round(rect.top - pad - bleed);
        var w = Math.round(rect.width + pad * 2 + bleed * 2);
        var h = Math.round(rect.height + pad * 2 + bleed * 2);
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var r = Math.round(radius);
        var d = 'M0 0 H' + vw + ' V' + vh + ' H0 Z ' + roundedRectPath(x, y, w, h, r);

        backdrop.style.clipPath = "path(evenodd, '" + d + "')";

        if (spotlight) {
            spotlight.hidden = false;
            spotlight.style.top = y + 'px';
            spotlight.style.left = x + 'px';
            spotlight.style.width = w + 'px';
            spotlight.style.height = h + 'px';
            spotlight.style.borderRadius = r + 'px';
        }
    }

    function clearBackdropHole(backdrop, spotlight) {
        backdrop.style.clipPath = '';
        if (spotlight) {
            spotlight.hidden = true;
        }
    }

    function setActiveTarget(node, extraNodes) {
        clearActiveTarget();
        if (!node) {
            return;
        }
        var nodes = [node].concat(extraNodes || []);
        nodes.forEach(function (item) {
            if (!item) {
                return;
            }
            item.classList.add('shell-tour-target-active');
            activeTargets.push(item);
        });
    }

    function prepareStep(step) {
        if (step.prepare !== 'show-hub-picker') {
            return;
        }

        if (window.unioSidebarHubs && typeof window.unioSidebarHubs.showPicker === 'function') {
            window.unioSidebarHubs.showPicker();
            return;
        }

        document.querySelectorAll('[data-sidebar-hub-back]').forEach(function (backBtn) {
            if (!backBtn.classList.contains('is-hidden')) {
                backBtn.click();
            }
        });
    }

    function findTarget(step) {
        if (step.zone === 'hub-picker') {
            var zoneHit = unionHubPickerRect();
            if (zoneHit) {
                return zoneHit;
            }
        }

        var selectors = step.targets && step.targets.length ? step.targets : [step.target];
        for (var i = 0; i < selectors.length; i++) {
            var node = document.querySelector(selectors[i]);
            if (isVisible(node)) {
                return { node: node, rect: node.getBoundingClientRect() };
            }
        }

        return null;
    }

    function persistTourComplete(mount, userId) {
        markDone(userId);

        var url = mount.getAttribute('data-tour-complete-url');
        var token = mount.getAttribute('data-csrf-token');
        if (!url || !token) {
            document.dispatchEvent(new CustomEvent('unio:shell-tour-complete'));
            return;
        }

        var body = new URLSearchParams();
        body.set('_token', token);

        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.json();
            })
            .then(function () {
                document.dispatchEvent(new CustomEvent('unio:shell-tour-complete'));
            })
            .catch(function () {
                document.dispatchEvent(new CustomEvent('unio:shell-tour-complete'));
            });
    }

    function initTour(config, userId, mount) {
        if (!config || !config.enabled || !config.steps || !config.steps.length) {
            return null;
        }

        var root = document.getElementById('shellTour');
        if (!root) {
            return null;
        }

        var backdrop = root.querySelector('[data-shell-tour-backdrop]');
        var spotlight = root.querySelector('[data-shell-tour-spotlight]');
        var card = root.querySelector('[data-shell-tour-card]');
        var titleEl = root.querySelector('[data-shell-tour-title]');
        var bodyEl = root.querySelector('[data-shell-tour-body]');
        var stepEl = root.querySelector('[data-shell-tour-step]');
        var prevBtn = root.querySelector('[data-shell-tour-prev]');
        var nextBtn = root.querySelector('[data-shell-tour-next]');
        var skipBtn = root.querySelector('[data-shell-tour-skip]');
        var closeBtn = root.querySelector('[data-shell-tour-close]');
        var arrowEl = root.querySelector('[data-shell-tour-arrow]');

        var allSteps = config.steps.slice();
        var currentSteps = allSteps.slice();
        var flowsById = {};
        (config.flows || []).forEach(function (flow) {
            flowsById[flow.id] = flow;
        });

        var index = 0;
        var active = false;
        var activeFlowId = null;
        var resizeTimer;

        function getFlow(flowId) {
            return flowsById[flowId] || null;
        }

        function setStepsForFlow(flow) {
            if (!flow || !flow.step_ids || !flow.step_ids.length) {
                currentSteps = allSteps.slice();
                return;
            }

            currentSteps = allSteps.filter(function (step) {
                return flow.step_ids.indexOf(step.id) !== -1;
            });
        }

        function placeCard(rect, placement) {
            var gap = 16;
            var arrowSize = 12;
            var cardW = card.offsetWidth || 360;
            var cardH = card.offsetHeight || 180;
            var top;
            var left;
            var resolved = placement || 'right';

            if (resolved === 'bottom') {
                top = rect.bottom + gap;
                left = rect.left + rect.width / 2 - cardW / 2;
            } else if (resolved === 'top') {
                top = rect.top - gap - cardH;
                left = rect.left + rect.width / 2 - cardW / 2;
            } else if (resolved === 'left') {
                top = rect.top + rect.height / 2 - cardH / 2;
                left = rect.left - gap - cardW;
            } else {
                top = rect.top + rect.height / 2 - cardH / 2;
                left = rect.right + gap;
            }

            left = Math.max(12, Math.min(left, window.innerWidth - cardW - 12));
            top = Math.max(12, Math.min(top, window.innerHeight - cardH - 12));

            card.style.top = Math.round(top) + 'px';
            card.style.left = Math.round(left) + 'px';

            var targetCx = rect.left + rect.width / 2;
            var targetCy = rect.top + rect.height / 2;
            var cardCx = left + cardW / 2;
            var cardCy = top + cardH / 2;
            var arrowSide;

            if (resolved === 'bottom' || (resolved !== 'top' && cardCy > rect.bottom)) {
                arrowSide = 'top';
            } else if (resolved === 'top' || cardCy + cardH < rect.top) {
                arrowSide = 'bottom';
            } else if (resolved === 'left' || cardCx + cardW < rect.left) {
                arrowSide = 'right';
            } else {
                arrowSide = 'left';
            }

            card.classList.remove(
                'shell-tour__card--arrow-top',
                'shell-tour__card--arrow-bottom',
                'shell-tour__card--arrow-left',
                'shell-tour__card--arrow-right',
            );
            card.classList.add('shell-tour__card--arrow-' + arrowSide);

            if (!arrowEl) {
                return;
            }

            arrowEl.style.top = '';
            arrowEl.style.left = '';
            arrowEl.style.right = '';
            arrowEl.style.bottom = '';

            if (arrowSide === 'left' || arrowSide === 'right') {
                var arrowTop = targetCy - top - arrowSize;
                arrowTop = Math.max(20, Math.min(arrowTop, cardH - 20 - arrowSize * 2));
                arrowEl.style.top = Math.round(arrowTop) + 'px';
            } else {
                var arrowLeft = targetCx - left - arrowSize;
                arrowLeft = Math.max(24, Math.min(arrowLeft, cardW - 24 - arrowSize * 2));
                arrowEl.style.left = Math.round(arrowLeft) + 'px';
            }
        }

        function renderStep() {
            var step = currentSteps[index];
            if (!step) {
                finish(true);
                return;
            }

            prepareStep(step);

            var hit = findTarget(step);
            if (!hit) {
                if (index < currentSteps.length - 1) {
                    index += 1;
                    renderStep();
                    return;
                }
                finish(true);
                return;
            }

            var pad = step.zone === 'hub-picker' ? 6 : 10;
            var rect = hit.rect;
            var holeW = rect.width + pad * 2;
            var holeH = rect.height + pad * 2;
            var radius = resolveHighlightRadius(step, holeW, holeH);
            applyBackdropHole(backdrop, spotlight, rect, pad, radius);

            titleEl.textContent = step.title;
            bodyEl.textContent = step.body;
            stepEl.textContent = (index + 1) + ' / ' + currentSteps.length;

            prevBtn.disabled = index === 0;
            nextBtn.textContent = index === currentSteps.length - 1 ? 'Concluir' : 'Próximo';

            card.hidden = false;
            requestAnimationFrame(function () {
                placeCard(rect, step.placement || 'right');
            });

            if (typeof hit.node.scrollIntoView === 'function') {
                hit.node.scrollIntoView({ block: 'nearest', inline: 'nearest' });
            }
        }

        function openFlow(flowId) {
            var flow = getFlow(flowId);
            if (!flow) {
                return;
            }

            if (flow.action) {
                if (window.unioShellHelp && typeof window.unioShellHelp.close === 'function') {
                    window.unioShellHelp.close();
                }
                if (flow.action === 'scroll-checklist') {
                    var checklist = document.querySelector('[data-onboarding-checklist]');
                    if (checklist) {
                        checklist.hidden = false;
                        checklist.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else if (flow.action === 'focus-search') {
                    var input = document.querySelector('.global-search-input');
                    if (input) {
                        input.focus();
                    }
                }
                return;
            }

            activeFlowId = flowId;
            setStepsForFlow(flow);
            if (!currentSteps.length) {
                return;
            }

            if (flowId === 'full') {
                clearDone(userId);
            }

            index = 0;
            active = true;
            root.hidden = false;
            document.body.classList.add('shell-tour-open');

            if (window.unioShellHelp && typeof window.unioShellHelp.close === 'function') {
                window.unioShellHelp.close();
            }

            window.setTimeout(renderStep, flow.prepare === 'show-hub-picker' ? 180 : 60);
        }

        function open(atIndex) {
            openFlow('full');
            if (typeof atIndex === 'number' && atIndex > 0) {
                index = atIndex;
                renderStep();
            }
        }

        function finish(completed) {
            active = false;
            root.hidden = true;
            document.body.classList.remove('shell-tour-open');
            clearBackdropHole(backdrop, spotlight);
            clearActiveTarget();

            if (completed) {
                var flow = getFlow(activeFlowId);
                if (flow && flow.marks_complete) {
                    persistTourComplete(mount, userId);
                }
            }

            activeFlowId = null;
            currentSteps = allSteps.slice();
            index = 0;
        }

        function onResize() {
            if (!active) {
                return;
            }
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(renderStep, 120);
        }

        prevBtn.addEventListener('click', function () {
            if (index > 0) {
                index -= 1;
                renderStep();
            }
        });

        nextBtn.addEventListener('click', function () {
            if (index < currentSteps.length - 1) {
                index += 1;
                renderStep();
            } else {
                finish(true);
            }
        });

        skipBtn.addEventListener('click', function () {
            finish(false);
        });
        closeBtn.addEventListener('click', function () {
            finish(false);
        });
        backdrop.addEventListener('click', function () {
            finish(false);
        });

        document.addEventListener('keydown', function (e) {
            if (!active) {
                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                finish(false);
            } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
                if (document.activeElement === prevBtn || document.activeElement === nextBtn) {
                    return;
                }
                e.preventDefault();
                if (index < currentSteps.length - 1) {
                    index += 1;
                    renderStep();
                } else {
                    finish(true);
                }
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                if (index > 0) {
                    index -= 1;
                    renderStep();
                }
            }
        });

        window.addEventListener('resize', onResize);
        window.addEventListener('scroll', onResize, true);

        return {
            open: open,
            openFlow: openFlow,
            restart: function () {
                openFlow('full');
            },
        };
    }

    function boot() {
        var mount = document.getElementById('shellTourMount');
        if (!mount) {
            return;
        }

        var userId = mount.getAttribute('data-user-id') || '0';
        var config = parseConfig();
        var tour = initTour(config, userId, mount);
        if (!tour) {
            return;
        }

        if (config.checklist && config.checklist.shell_tour_done) {
            markDone(userId);
        }

        window.unioShellTour = tour;

        document.querySelectorAll('[data-shell-tour-restart]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                tour.openFlow('full');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
