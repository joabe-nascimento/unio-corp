/**
 * filter_group — ajusta largura do select ao texto selecionado (.filter-select-wrap)
 */
(function () {
    'use strict';

    function syncSizer(select) {
        var wrap = select.closest('.filter-select-wrap');
        if (!wrap) return;
        var sizer = wrap.querySelector('.filter-select-sizer');
        if (!sizer) return;
        var opt = select.options[select.selectedIndex];
        sizer.textContent = opt ? opt.textContent.trim() : '';
    }

    function initAll(root) {
        (root || document).querySelectorAll('[data-filter-select]').forEach(syncSizer);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initAll();
    });

    document.addEventListener('change', function (e) {
        if (e.target && e.target.matches('[data-filter-select]')) {
            syncSizer(e.target);
        }
    });

    window.UnioFilterSelect = { sync: syncSizer, initAll: initAll };
}());
