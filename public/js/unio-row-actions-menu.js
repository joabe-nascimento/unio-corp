/**
 * UnioRowActionsMenu — menu ⋮ teleportado para document.body + position:fixed.
 * Evita ficar atrás de linhas da tabela (transform/overflow/stacking).
 */
(function () {
    'use strict';

    var SELECTOR = '[data-unio-row-actions-menu], [data-unio-toolbar-more-menu], .unio-row-actions-menu, .hub-toolbar-more, .clinic-agenda-row-actions__menu';

    function placeMenu(btn, menu) {
        var rect = btn.getBoundingClientRect();
        var mw = menu.offsetWidth || 168;
        var mh = menu.offsetHeight || 120;
        var top = rect.bottom + 4;
        var left = Math.max(8, rect.right - mw);

        if (left + mw > window.innerWidth - 8) {
            left = Math.max(8, window.innerWidth - mw - 8);
        }
        if (top + mh > window.innerHeight - 8) {
            top = Math.max(8, rect.top - mh - 4);
        }

        menu.style.setProperty('position', 'fixed', 'important');
        menu.style.setProperty('top', top + 'px', 'important');
        menu.style.setProperty('left', left + 'px', 'important');
        menu.style.setProperty('right', 'auto', 'important');
        menu.style.setProperty('bottom', 'auto', 'important');
        menu.style.setProperty('transform', 'none', 'important');
        menu.style.setProperty('margin', '0', 'important');
        menu.style.setProperty('z-index', '2000', 'important');
        menu.style.setProperty('display', 'block', 'important');
    }

    function clearInline(menu) {
        [
            'position', 'top', 'left', 'right', 'bottom',
            'transform', 'margin', 'z-index', 'display',
        ].forEach(function (prop) {
            menu.style.removeProperty(prop);
        });
    }

    function bindWrap(wrap) {
        if (wrap.getAttribute('data-unio-row-menu-bound') === '1') return;
        var btn = wrap.querySelector('[data-toggle="dropdown"]');
        var menu = wrap.querySelector('.dropdown-menu');
        if (!btn || !menu) return;

        wrap.setAttribute('data-unio-row-menu-bound', '1');
        var placeholder = document.createComment('unio-row-menu-slot');

        function detachToBody() {
            if (menu.parentNode === document.body) return;
            if (!placeholder.parentNode) {
                menu.parentNode.insertBefore(placeholder, menu);
            }
            document.body.appendChild(menu);
            menu.classList.add('unio-row-actions-dropdown--portal');
        }

        function restoreToWrap() {
            if (placeholder.parentNode) {
                placeholder.parentNode.insertBefore(menu, placeholder);
                placeholder.parentNode.removeChild(placeholder);
            } else if (menu.parentNode === document.body) {
                wrap.appendChild(menu);
            }
            menu.classList.remove('unio-row-actions-dropdown--portal');
            clearInline(menu);
        }

        function onShown() {
            detachToBody();
            menu.classList.add('show');
            placeMenu(btn, menu);
            requestAnimationFrame(function () {
                placeMenu(btn, menu);
            });
        }

        function onHidden() {
            restoreToWrap();
        }

        if (typeof window.jQuery !== 'undefined') {
            window.jQuery(btn).on('show.bs.dropdown', function () {
                detachToBody();
            });
            window.jQuery(btn).on('shown.bs.dropdown', onShown);
            window.jQuery(btn).on('hidden.bs.dropdown', onHidden);
        } else {
            btn.addEventListener('show.bs.dropdown', function () {
                detachToBody();
            });
            btn.addEventListener('shown.bs.dropdown', onShown);
            btn.addEventListener('hidden.bs.dropdown', onHidden);
        }

        var reposition = function () {
            if (menu.classList.contains('show') && menu.parentNode === document.body) {
                placeMenu(btn, menu);
            }
        };
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);
    }

    function scan(root) {
        (root || document).querySelectorAll(SELECTOR).forEach(bindWrap);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scan(); });
    } else {
        scan();
    }

    // Tabelas/partials injetados depois
    document.addEventListener('shown.bs.offcanvas', function () { scan(); });

    window.UnioRowActionsMenu = { scan: scan };
})();
