/**
 * Tela de boas-vindas — relógio ao vivo e personalização (localStorage por usuário).
 */
(function () {
    'use strict';

    var page = document.getElementById('welcomePage');
    if (!page) return;

    var userId = page.getAttribute('data-user-id') || '0';
    var storageKey = 'unio-welcome-prefs-' + userId;

    var defaults = {
        layout: 'comfortable',
        sections: {
            metrics: true,
            highlights: true,
            journey: true,
            novidades: true,
            hubs: true,
            graficos: true
        },
        pinnedHubs: [],
        hiddenNovidades: []
    };

    function loadPrefs() {
        try {
            var raw = localStorage.getItem(storageKey);
            if (!raw) return JSON.parse(JSON.stringify(defaults));
            var parsed = JSON.parse(raw);
            return {
                layout: parsed.layout || defaults.layout,
                sections: Object.assign({}, defaults.sections, parsed.sections || {}),
                pinnedHubs: Array.isArray(parsed.pinnedHubs) ? parsed.pinnedHubs : [],
                hiddenNovidades: Array.isArray(parsed.hiddenNovidades) ? parsed.hiddenNovidades : []
            };
        } catch (e) {
            return JSON.parse(JSON.stringify(defaults));
        }
    }

    function savePrefs(prefs) {
        try {
            localStorage.setItem(storageKey, JSON.stringify(prefs));
        } catch (e) { /* quota */ }
    }

    var prefs = loadPrefs();

    function applyLayout() {
        page.setAttribute('data-layout', prefs.layout);
        document.querySelectorAll('input[name="welcome_layout"]').forEach(function (radio) {
            radio.checked = radio.value === prefs.layout;
        });
    }

    function applySections() {
        Object.keys(defaults.sections).forEach(function (key) {
            if (prefs.sections[key] === undefined) {
                prefs.sections[key] = defaults.sections[key];
            }
        });
        Object.keys(prefs.sections).forEach(function (key) {
            var section = document.querySelector('[data-welcome-section="' + key + '"]');
            if (section) {
                section.hidden = !prefs.sections[key];
            }
            var toggle = document.querySelector('[data-welcome-pref-section="' + key + '"]');
            if (toggle) toggle.checked = !!prefs.sections[key];
        });
    }

    function applyHiddenNovidades() {
        prefs.hiddenNovidades.forEach(function (id) {
            var card = document.querySelector('[data-novidade-id="' + id + '"]');
            if (card) card.classList.add('is-hidden');
        });
        var track = document.querySelector('.welcome-novidades-track');
        if (track && track.querySelectorAll('.welcome-novidade-card:not(.is-hidden)').length === 0) {
            var section = document.querySelector('[data-welcome-section="novidades"]');
            if (section) section.hidden = true;
        }
    }

    function sortHubs() {
        var grid = document.getElementById('welcomeHubsGrid');
        if (!grid) return;

        var tiles = Array.prototype.slice.call(grid.querySelectorAll('.welcome-hub-tile'));
        tiles.sort(function (a, b) {
            var aId = a.getAttribute('data-hub-id');
            var bId = b.getAttribute('data-hub-id');
            var aPin = prefs.pinnedHubs.indexOf(aId) !== -1;
            var bPin = prefs.pinnedHubs.indexOf(bId) !== -1;
            if (aPin && !bPin) return -1;
            if (!aPin && bPin) return 1;
            if (aPin && bPin) {
                return prefs.pinnedHubs.indexOf(aId) - prefs.pinnedHubs.indexOf(bId);
            }
            return 0;
        });

        tiles.forEach(function (tile) {
            grid.appendChild(tile);
        });
    }

    function applyPins() {
        document.querySelectorAll('[data-welcome-pin-hub]').forEach(function (btn) {
            var hubId = btn.getAttribute('data-welcome-pin-hub');
            var pinned = prefs.pinnedHubs.indexOf(hubId) !== -1;
            var tile = btn.closest('.welcome-hub-tile');
            if (tile) tile.classList.toggle('is-pinned', pinned);
            var icon = btn.querySelector('i');
            if (icon) {
                icon.className = pinned ? 'fas fa-star' : 'far fa-star';
            }
            btn.setAttribute('aria-pressed', pinned ? 'true' : 'false');
            btn.title = pinned ? 'Desafixar hub' : 'Fixar hub';
        });
        sortHubs();
    }

    function updateClock() {
        var timeEls = document.querySelectorAll('[data-welcome-time]');
        if (!timeEls.length) return;
        try {
            var now = new Date();
            var fmt = new Intl.DateTimeFormat('pt-BR', {
                timeZone: 'America/Sao_Paulo',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            var label = fmt.format(now);
            timeEls.forEach(function (el) { el.textContent = label; });
        } catch (e) { /* ignore */ }
    }

    applyLayout();
    applySections();
    applyHiddenNovidades();
    applyPins();
    updateClock();
    setInterval(updateClock, 30000);

    document.querySelectorAll('input[name="welcome_layout"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!radio.checked) return;
            prefs.layout = radio.value;
            savePrefs(prefs);
            applyLayout();
        });
    });

    document.querySelectorAll('[data-welcome-pref-section]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            var key = checkbox.getAttribute('data-welcome-pref-section');
            prefs.sections[key] = checkbox.checked;
            savePrefs(prefs);
            applySections();
        });
    });

    document.querySelectorAll('[data-welcome-pin-hub]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var hubId = btn.getAttribute('data-welcome-pin-hub');
            var idx = prefs.pinnedHubs.indexOf(hubId);
            if (idx === -1) {
                prefs.pinnedHubs.push(hubId);
            } else {
                prefs.pinnedHubs.splice(idx, 1);
            }
            savePrefs(prefs);
            applyPins();
        });
    });

    document.querySelectorAll('[data-welcome-dismiss-novidade]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var id = btn.getAttribute('data-welcome-dismiss-novidade');
            if (prefs.hiddenNovidades.indexOf(id) === -1) {
                prefs.hiddenNovidades.push(id);
            }
            savePrefs(prefs);
            var card = document.querySelector('[data-novidade-id="' + id + '"]');
            if (card) {
                card.classList.add('is-dismissed');
                setTimeout(function () {
                    card.classList.add('is-hidden');
                    applyHiddenNovidades();
                }, 220);
            }
        });
    });

    document.querySelectorAll('[data-welcome-journey-anchor]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            var hash = link.getAttribute('href');
            if (!hash || hash.charAt(0) !== '#') return;
            var target = document.querySelector(hash);
            if (!target) return;
            e.preventDefault();
            if (hash === '#welcome-personalize') {
                var openBtn = document.querySelector('[data-huplex-offcanvas-open="welcome-personalize"]');
                if (openBtn) openBtn.click();
                return;
            }
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    var resetBtn = document.getElementById('welcomePrefsReset');
    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            prefs = JSON.parse(JSON.stringify(defaults));
            savePrefs(prefs);
            document.querySelectorAll('.welcome-novidade-card').forEach(function (c) {
                c.classList.remove('is-hidden', 'is-dismissed');
            });
            applyLayout();
            applySections();
            applyPins();
        });
    }
})();
