/**
 * Toolbar mobile — clona filtros/busca do desktop para offcanvas no mobile.
 * Desktop permanece inalterado; controles inline ficam ocultos visualmente no mobile.
 */
(function () {
    'use strict';

    function copyValues(fromEl, toEl) {
        if (!fromEl || !toEl) return;
        if (fromEl.type === 'checkbox' || fromEl.type === 'radio') {
            toEl.checked = fromEl.checked;
        } else {
            toEl.value = fromEl.value;
        }
    }

    function syncAllToSource(host, mount) {
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!inline || !mount) return;

        mount.querySelectorAll('input, select, textarea').forEach(function (cloneField) {
            if (!cloneField.name) return;
            var sourceField = inline.querySelector('[name="' + CSS.escape(cloneField.name) + '"]');
            if (sourceField) {
                copyValues(cloneField, sourceField);
            }
        });
    }

    function syncAllToClone(host, mount) {
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!inline || !mount) return;

        inline.querySelectorAll('input, select, textarea').forEach(function (sourceField) {
            if (!sourceField.name) return;
            var cloneField = mount.querySelector('[name="' + CSS.escape(sourceField.name) + '"]');
            if (cloneField) {
                copyValues(sourceField, cloneField);
            }
        });
    }

    function bindCloneFields(host, mount) {
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!inline || !mount) return;

        mount.querySelectorAll('input, select, textarea').forEach(function (cloneField) {
            if (!cloneField.name) return;
            var sourceField = inline.querySelector('[name="' + CSS.escape(cloneField.name) + '"]');
            if (!sourceField) return;

            cloneField.addEventListener('input', function () {
                copyValues(cloneField, sourceField);
            });
            cloneField.addEventListener('change', function () {
                copyValues(cloneField, sourceField);
                if (window.UnioFilterSelect && typeof window.UnioFilterSelect.sync === 'function') {
                    window.UnioFilterSelect.sync(sourceField);
                }
            });
        });

        mount.querySelectorAll('button[type="submit"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                syncAllToSource(host, mount);
                var form = host.closest('form');
                if (!form) return;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
    }

    function prepareSearchClone(mount) {
        mount.querySelectorAll('[data-search-expand]').forEach(function (wrap) {
            wrap.classList.add('is-expanded');
            var toggle = wrap.querySelector('.list-search-toggle');
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'true');
            }
        });
    }

    function populate(host) {
        var offcanvasId = host.getAttribute('data-toolbar-offcanvas-id');
        var mount = document.querySelector('[data-toolbar-mobile-mount="' + offcanvasId + '"]');
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!mount || !inline || mount.dataset.populated === '1') return;

        mount.innerHTML = '';
        var wrapper = document.createElement('div');
        wrapper.className = 'toolbar-mobile-offcanvas-controls';

        Array.prototype.forEach.call(inline.children, function (child) {
            wrapper.appendChild(child.cloneNode(true));
        });

        mount.appendChild(wrapper);
        mount.dataset.populated = '1';

        prepareSearchClone(mount);
        syncAllToClone(host, mount);
        bindCloneFields(host, mount);

        if (window.UnioFilterSelect && typeof window.UnioFilterSelect.initAll === 'function') {
            window.UnioFilterSelect.initAll(mount);
        }
    }

    function refreshValues(host) {
        var offcanvasId = host.getAttribute('data-toolbar-offcanvas-id');
        var mount = document.querySelector('[data-toolbar-mobile-mount="' + offcanvasId + '"]');
        if (!mount || mount.dataset.populated !== '1') return;
        syncAllToClone(host, mount);
        if (window.UnioFilterSelect && typeof window.UnioFilterSelect.initAll === 'function') {
            window.UnioFilterSelect.initAll(mount);
        }
    }

    function hideEmptyDuals() {
        document.querySelectorAll('[data-toolbar-controls-dual]').forEach(function (host) {
            var inline = host.querySelector('.toolbar-inline-controls');
            var fab = host.querySelector('.toolbar-mobile-fab');
            if (!inline || !fab) return;
            var empty = inline.children.length === 0;
            fab.hidden = empty;
            host.classList.toggle('toolbar-controls-dual--empty', empty);
        });
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-unio-offcanvas-open]');
        if (!openBtn) return;

        var host = openBtn.closest('[data-toolbar-controls-dual]');
        if (!host) return;

        var offcanvasId = host.getAttribute('data-toolbar-offcanvas-id');
        var mountEl = document.querySelector('[data-toolbar-mobile-mount="' + offcanvasId + '"]');
        if (mountEl && mountEl.dataset.populated === '1') {
            refreshValues(host);
        } else {
            populate(host);
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideEmptyDuals);
    } else {
        hideEmptyDuals();
    }
})();
