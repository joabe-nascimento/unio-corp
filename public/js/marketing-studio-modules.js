(function () {
    'use strict';

    var detail = document.getElementById('mkt-studio-module-detail');
    var slot = detail ? detail.querySelector('[data-studio-module-slot]') : null;
    var grid = document.querySelector('[data-studio-modules-grid]');
    if (!detail || !slot || !grid) {
        return;
    }

    var triggers = grid.querySelectorAll('[data-studio-module-trigger]');
    var activeId = null;
    var rotateTimer = null;

    function getTemplate(id) {
        return document.getElementById('studio-module-tpl-' + id);
    }

    function setExpanded(id) {
        triggers.forEach(function (btn) {
            var on = btn.getAttribute('data-studio-module-trigger') === id;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-expanded', on ? 'true' : 'false');
        });
    }

    function bindPanel(panel) {
        var closeBtn = panel.querySelector('[data-studio-module-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', closePanel);
        }
        startActivityRotation(panel);
    }

    function closePanel() {
        if (rotateTimer) {
            clearInterval(rotateTimer);
            rotateTimer = null;
        }
        detail.hidden = true;
        detail.classList.remove('is-open');
        slot.innerHTML = '';
        activeId = null;
        setExpanded(null);
    }

    function openPanel(id) {
        if (activeId === id && detail.classList.contains('is-open')) {
            closePanel();
            return;
        }

        var tpl = getTemplate(id);
        if (!tpl || !tpl.content || !tpl.content.firstElementChild) {
            return;
        }

        if (rotateTimer) {
            clearInterval(rotateTimer);
            rotateTimer = null;
        }

        slot.innerHTML = '';
        slot.appendChild(tpl.content.firstElementChild.cloneNode(true));
        detail.hidden = false;
        detail.classList.add('is-open');
        activeId = id;
        setExpanded(id);
        bindPanel(slot);

        requestAnimationFrame(function () {
            detail.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    }

    function startActivityRotation(panel) {
        var list = panel.querySelector('[data-studio-activities]');
        if (!list) {
            return;
        }

        var items = Array.prototype.slice.call(list.querySelectorAll('.mkt-studio-info__activity'));
        if (items.length < 4) {
            return;
        }

        function rotateOnce() {
            var first = items.shift();
            if (!first) {
                return;
            }
            first.classList.add('is-leaving');
            window.setTimeout(function () {
                first.classList.remove('is-leaving');
                list.appendChild(first);
                items.push(first);
                first.classList.add('is-entering');
                window.setTimeout(function () {
                    first.classList.remove('is-entering');
                }, 320);
            }, 280);
        }

        rotateTimer = window.setInterval(rotateOnce, 7000);
    }

    triggers.forEach(function (btn) {
        btn.addEventListener('click', function () {
            openPanel(btn.getAttribute('data-studio-module-trigger'));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && detail.classList.contains('is-open')) {
            closePanel();
        }
    });

    document.addEventListener('click', function (e) {
        if (!detail.classList.contains('is-open')) {
            return;
        }
        var inside = detail.contains(e.target) || Array.prototype.some.call(triggers, function (btn) {
            return btn.contains(e.target);
        });
        if (!inside) {
            closePanel();
        }
    });
})();
