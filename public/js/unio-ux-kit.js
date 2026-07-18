/**
 * Unio UX Kit — inicialização global de CountUp, Flatpickr, Cleave, AutoAnimate e Notyf.
 * Grid.js e Driver.js são expostos via helpers; páginas específicas chamam init* dedicados.
 */
(function (global, document) {
    'use strict';

    var flatpickrLocaleReady = false;

    function whenReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function parseNum(raw) {
        var n = parseFloat(String(raw).replace(',', '.'));
        return Number.isFinite(n) ? n : null;
    }

    function initCountUp() {
        var Ctor = global.countUp && global.countUp.CountUp;
        if (!Ctor) return;

        document.querySelectorAll('[data-unio-countup]').forEach(function (el) {
            if (el.dataset.unioCountupInit === '1') return;
            var end = parseNum(el.getAttribute('data-unio-countup'));
            if (end === null) return;

            el.dataset.unioCountupInit = '1';
            var suffix = el.getAttribute('data-unio-countup-suffix') || '';
            var prefix = el.getAttribute('data-unio-countup-prefix') || '';
            var decimals = parseInt(el.getAttribute('data-unio-countup-decimals') || '0', 10);
            var duration = parseFloat(el.getAttribute('data-unio-countup-duration') || '1.1');

            var cu = new Ctor(el, end, {
                suffix: suffix,
                prefix: prefix,
                decimalPlaces: decimals,
                duration: duration,
                separator: '.',
                decimal: ',',
                useGrouping: end >= 1000,
            });
            if (!cu.error) {
                cu.start();
            }
        });
    }

    function ensureFlatpickrLocale() {
        if (flatpickrLocaleReady || typeof global.flatpickr === 'undefined') return;
        if (global.flatpickr.l10ns && global.flatpickr.l10ns.pt) {
            flatpickrLocaleReady = true;
        }
    }

    function initFlatpickr() {
        if (typeof global.flatpickr === 'undefined') return;
        ensureFlatpickrLocale();

        var locale = (global.flatpickr.l10ns && global.flatpickr.l10ns.pt) || 'default';

        document.querySelectorAll('[data-unio-datetime]').forEach(function (el) {
            if (el._flatpickr) return;

            var mode = el.getAttribute('data-unio-datetime') || 'datetime';
            var opts = {
                locale: locale,
                allowInput: true,
                time_24hr: true,
                disableMobile: true,
            };

            if (mode === 'datetime') {
                opts.enableTime = true;
                opts.dateFormat = 'Y-m-d H:i';
                opts.altInput = true;
                opts.altFormat = 'd/m/Y H:i';
                opts.altInputClass = 'form-control';
            } else if (mode === 'date') {
                opts.dateFormat = 'Y-m-d';
                opts.altInput = true;
                opts.altFormat = 'd/m/Y';
                opts.altInputClass = 'form-control';
            } else if (mode === 'time') {
                opts.enableTime = true;
                opts.noCalendar = true;
                opts.dateFormat = 'H:i';
            }

            var min = el.getAttribute('data-unio-datetime-min');
            var max = el.getAttribute('data-unio-datetime-max');
            if (min) opts.minDate = min;
            if (max) opts.maxDate = max;

            global.flatpickr(el, opts);
        });
    }

    var cleaveMap = {
        phone: { phone: true, phoneRegionCode: 'BR' },
        cpf: { blocks: [3, 3, 3, 2], delimiters: ['.', '.', '-'], numericOnly: true },
        cnpj: { blocks: [2, 3, 3, 4, 2], delimiters: ['.', '.', '/', '-'], numericOnly: true },
        cep: { blocks: [5, 3], delimiters: ['-'], numericOnly: true },
        creditCard: { creditCard: true },
    };

    function initCleave() {
        if (typeof global.Cleave === 'undefined') return;

        document.querySelectorAll('[data-unio-cleave]').forEach(function (el) {
            if (el.dataset.unioCleaveInit === '1') return;
            var type = el.getAttribute('data-unio-cleave');
            var opts = cleaveMap[type];
            if (!opts) return;
            el.dataset.unioCleaveInit = '1';
            new global.Cleave(el, opts);
        });
    }

    function initAutoAnimate() {
        var targets = document.querySelectorAll('[data-unio-auto-animate]');
        if (!targets.length) return;

        import(global.UNIO_AUTO_ANIMATE_URL || '/vendor/auto-animate/index.mjs')
            .then(function (mod) {
                var fn = mod.autoAnimate || mod.default;
                if (typeof fn !== 'function') return;
                targets.forEach(function (el) {
                    fn(el, { duration: 220 });
                });
            })
            .catch(function () { /* módulo opcional */ });
    }

    function createNotyf() {
        if (typeof global.Notyf === 'undefined') return null;
        return new global.Notyf({
            duration: 4200,
            dismissible: true,
            position: { x: 'right', y: 'top' },
            types: [
                { type: 'success', className: 'notyf__toast--unio-success' },
                { type: 'error', className: 'notyf__toast--unio-error' },
            ],
        });
    }

    function bridgeToast() {
        var notyf = createNotyf();
        if (!notyf) return;

        global.UnioNotyf = notyf;
        if (global.UnioToast && !global.UnioToast._notyfBridged) {
            var orig = global.UnioToast.show.bind(global.UnioToast);
            global.UnioToast.show = function (message, type, duration) {
                if (notyf && (type === 'success' || type === 'error')) {
                    notyf[type](message);
                    return;
                }
                return orig(message, type, duration);
            };
            global.UnioToast._notyfBridged = true;
        }
    }

    function getDriver() {
        return global.driver && global.driver.js && global.driver.js.driver
            ? global.driver.js.driver
            : null;
    }

    function getGrid() {
        return global.gridjs || null;
    }

    function initAll() {
        initCountUp();
        initFlatpickr();
        initCleave();
        initAutoAnimate();
        bridgeToast();
        document.dispatchEvent(new CustomEvent('unio:ux-ready'));
    }

    global.UnioUx = {
        init: initAll,
        initCountUp: initCountUp,
        initFlatpickr: initFlatpickr,
        initCleave: initCleave,
        getDriver: getDriver,
        getGrid: getGrid,
        createNotyf: createNotyf,
    };

    whenReady(initAll);
})(window, document);
