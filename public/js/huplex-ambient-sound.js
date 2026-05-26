/**
 * Som ambiente — toggle simples (ícone), trilha padrão "foco profundo".
 */
(function () {
    'use strict';

    var root = document.querySelector('[data-ambient-sound]');
    if (!root) {
        return;
    }

    var btn = root.querySelector('[data-ambient-toggle]');
    var iconEl = root.querySelector('[data-ambient-icon]');
    var userId = root.getAttribute('data-user-id') || '0';
    var preset = root.getAttribute('data-default-preset') || 'focus';
    var storageKey = 'unio-ambient-sound-' + userId;
    var DEFAULT_VOLUME = 0.32;

    var prefs = { enabled: false };
    var engine = null;

    try {
        var raw = localStorage.getItem(storageKey);
        if (raw) {
            prefs = JSON.parse(raw);
        }
    } catch (e) { /* ignore */ }

    function savePrefs() {
        try {
            localStorage.setItem(storageKey, JSON.stringify(prefs));
        } catch (e) { /* quota */ }
    }

    function buildBrownBuffer(ctx) {
        var bufferSize = 2 * ctx.sampleRate;
        var buffer = ctx.createBuffer(1, bufferSize, ctx.sampleRate);
        var data = buffer.getChannelData(0);
        var last = 0;
        for (var i = 0; i < bufferSize; i++) {
            var white = Math.random() * 2 - 1;
            last = (last + 0.02 * white) / 1.02;
            data[i] = last * 2.2;
        }
        return buffer;
    }

    function createEngine() {
        var AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            return null;
        }

        var ctx = null;
        var master = null;
        var sources = [];

        function teardown() {
            sources.forEach(function (s) {
                try {
                    s.stop();
                } catch (e) { /* ignore */ }
            });
            sources = [];
            if (ctx) {
                ctx.close();
                ctx = null;
                master = null;
            }
        }

        return {
            play: function () {
                teardown();
                ctx = new AudioCtx();
                master = ctx.createGain();
                master.gain.value = DEFAULT_VOLUME;
                master.connect(ctx.destination);

                var noise = ctx.createBufferSource();
                noise.buffer = buildBrownBuffer(ctx);
                noise.loop = true;
                var filter = ctx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.value = 320;
                filter.Q.value = 0.6;
                noise.connect(filter);
                filter.connect(master);
                noise.start(0);
                sources.push(noise);

                var pad = ctx.createOscillator();
                var padGain = ctx.createGain();
                pad.type = 'sine';
                pad.frequency.value = 110;
                padGain.gain.value = 0.04;
                pad.connect(padGain);
                padGain.connect(master);
                pad.start(0);
                sources.push(pad);
            },
            stop: teardown,
            pause: function () {
                if (ctx && ctx.state === 'running') {
                    ctx.suspend();
                }
            },
            resume: function () {
                if (ctx && ctx.state === 'suspended') {
                    ctx.resume();
                }
            }
        };
    }

    function setUi(on) {
        root.classList.toggle('is-playing', on);
        if (btn) {
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.setAttribute('title', on ? 'Desativar som ambiente' : 'Ativar som ambiente');
            btn.setAttribute('aria-label', on ? 'Desativar som ambiente' : 'Ativar som ambiente');
        }
        if (iconEl) {
            iconEl.className = on ? 'fas fa-volume-high' : 'fas fa-volume-off';
        }
    }

    function play() {
        if (!engine) {
            engine = createEngine();
        }
        if (!engine) {
            if (btn) {
                btn.disabled = true;
            }
            return;
        }
        engine.play();
        prefs.enabled = true;
        savePrefs();
        setUi(true);
    }

    function stop() {
        if (engine) {
            engine.stop();
            engine = createEngine();
        }
        prefs.enabled = false;
        savePrefs();
        setUi(false);
    }

    if (btn) {
        btn.addEventListener('click', function () {
            if (prefs.enabled) {
                stop();
            } else {
                play();
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!engine || !prefs.enabled) {
            return;
        }
        if (document.hidden) {
            engine.pause();
        } else {
            engine.resume();
        }
    });

    if (prefs.enabled) {
        play();
    } else {
        setUi(false);
    }
})();
