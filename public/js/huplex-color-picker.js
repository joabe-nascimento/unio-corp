/**
 * Huplex Color Picker — presets + input nativo type=color
 */
(function () {
    'use strict';

    var DEFAULT = '#4F7FFF';

    function normalize(hex) {
        if (!hex || typeof hex !== 'string') {
            return DEFAULT;
        }
        var h = hex.trim();
        if (h.charAt(0) !== '#') {
            h = '#' + h;
        }
        if (/^#[0-9A-Fa-f]{3}$/.test(h)) {
            h = '#' + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2) + h.charAt(3) + h.charAt(3);
        }
        return /^#[0-9A-Fa-f]{6}$/.test(h) ? h.toUpperCase() : DEFAULT;
    }

    function init(root) {
        if (root.dataset.colorPickerInit === '1') {
            return;
        }
        root.dataset.colorPickerInit = '1';

        var hidden = root.querySelector('[data-color-value]');
        var native = root.querySelector('[data-color-native]');
        var hexEl = root.querySelector('[data-color-hex]');
        var preview = root.querySelector('[data-color-preview]');
        var swatches = root.querySelectorAll('[data-color-swatch]');

        if (!hidden) {
            return;
        }

        function setColor(hex) {
            var value = normalize(hex);
            hidden.value = value;
            if (native) {
                native.value = value;
            }
            if (hexEl) {
                hexEl.textContent = value;
            }
            if (preview) {
                preview.style.background = value;
            }
            swatches.forEach(function (btn) {
                var match = (btn.getAttribute('data-color') || '').toUpperCase() === value;
                btn.classList.toggle('is-selected', match);
                btn.setAttribute('aria-selected', match ? 'true' : 'false');
            });
        }

        swatches.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setColor(btn.getAttribute('data-color'));
            });
        });

        if (native) {
            native.addEventListener('input', function () {
                setColor(native.value);
            });
        }

        setColor(hidden.value || DEFAULT);
    }

    function scan() {
        document.querySelectorAll('[data-huplex-color-picker]').forEach(init);
    }

    window.HuplexColorPicker = { init: init, refresh: scan, normalize: normalize };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }

    document.addEventListener('huplex-offcanvas-opened', scan);
})();
