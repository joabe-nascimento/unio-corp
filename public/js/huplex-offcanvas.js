/**
 * Huplex Offcanvas — painel lateral reutilizável
 *
 * HTML:  [data-huplex-offcanvas="id"]
 * Abrir: [data-huplex-offcanvas-open="id"]  ou legado [data-{id}-open]
 * Fechar:[data-huplex-offcanvas-close] dentro do painel, ou legado [data-{id}-close]
 */
(function () {
    'use strict';

    var BODY_CLASS = 'huplex-offcanvas-open';
    var LEGACY_OPEN_RE = /^data-([a-z0-9-]+)-open$/;
    var LEGACY_CLOSE_RE = /^data-([a-z0-9-]+)-close$/;

    var panels = {};

    function register(root) {
        var id = root.getAttribute('data-huplex-offcanvas');
        if (!id || panels[id]) {
            return;
        }
        panels[id] = root;
        if (root.classList.contains('is-open')) {
            document.body.classList.add(BODY_CLASS);
        }
    }

    function scan() {
        document.querySelectorAll('[data-huplex-offcanvas]').forEach(register);
    }

    function resolveOpenId(el) {
        var trigger = el.closest('[data-huplex-offcanvas-open]');
        if (trigger) {
            return trigger.getAttribute('data-huplex-offcanvas-open');
        }
        var node = el.closest('[data-huplex-offcanvas-open], button, a');
        if (!node || !node.attributes) {
            return null;
        }
        for (var i = 0; i < node.attributes.length; i++) {
            var name = node.attributes[i].name;
            var match = name.match(LEGACY_OPEN_RE);
            if (match && name !== 'data-huplex-offcanvas-open') {
                return match[1];
            }
        }
        return null;
    }

    function resolveCloseRoot(el) {
        var scoped = el.closest('[data-huplex-offcanvas-close]');
        if (scoped) {
            return scoped.closest('[data-huplex-offcanvas]');
        }
        var node = el.closest('button, a');
        if (!node || !node.attributes) {
            return null;
        }
        for (var j = 0; j < node.attributes.length; j++) {
            var n = node.attributes[j].name;
            var m = n.match(LEGACY_CLOSE_RE);
            if (m && n !== 'data-huplex-offcanvas-close') {
                return document.querySelector('[data-huplex-offcanvas="' + m[1] + '"]');
            }
        }
        return null;
    }

    function focusFirst(root) {
        var first = root.querySelector(
            'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])'
        );
        if (first) {
            window.setTimeout(function () {
                first.focus();
            }, 280);
        }
    }

    function open(id) {
        var root = panels[id] || document.querySelector('[data-huplex-offcanvas="' + id + '"]');
        if (!root) {
            return;
        }
        register(root);
        Object.keys(panels).forEach(function (otherId) {
            if (otherId !== id) {
                close(otherId, false);
            }
        });
        root.classList.add('is-open');
        root.setAttribute('aria-hidden', 'false');
        document.body.classList.add(BODY_CLASS);
        if (window.HuplexColorPicker && typeof window.HuplexColorPicker.refresh === 'function') {
            window.HuplexColorPicker.refresh();
        }
        focusFirst(root);
    }

    function close(id, removeBodyClass) {
        var root = panels[id] || document.querySelector('[data-huplex-offcanvas="' + id + '"]');
        if (!root) {
            return;
        }
        root.classList.remove('is-open');
        root.setAttribute('aria-hidden', 'true');
        if (removeBodyClass !== false) {
            var anyOpen = document.querySelector('[data-huplex-offcanvas].is-open');
            if (!anyOpen) {
                document.body.classList.remove(BODY_CLASS);
            }
        }
    }

    function closeAll() {
        Object.keys(panels).forEach(function (id) {
            close(id, false);
        });
        document.body.classList.remove(BODY_CLASS);
    }

    function onClick(e) {
        var openId = resolveOpenId(e.target);
        if (openId) {
            e.preventDefault();
            open(openId);
            return;
        }
        var closeRoot = resolveCloseRoot(e.target);
        if (closeRoot) {
            e.preventDefault();
            var cid = closeRoot.getAttribute('data-huplex-offcanvas');
            if (cid) {
                close(cid);
            }
        }
    }

    function onKeydown(e) {
        if (e.key !== 'Escape') {
            return;
        }
        var opened = document.querySelector('[data-huplex-offcanvas].is-open');
        if (!opened) {
            return;
        }
        var cid = opened.getAttribute('data-huplex-offcanvas');
        if (cid) {
            close(cid);
        }
    }

    function init() {
        scan();
        document.addEventListener('click', onClick);
        document.addEventListener('keydown', onKeydown);
    }

    window.HuplexOffcanvas = {
        open: open,
        close: close,
        closeAll: closeAll,
        refresh: scan,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
