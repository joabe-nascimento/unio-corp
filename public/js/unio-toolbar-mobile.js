/**
 * Toolbar mobile — FAB + offcanvas para filtros/busca em toda a plataforma.
 * Controles inline permanecem visíveis; FAB abre painel complementar no mobile.
 */
(function () {
    'use strict';

    var autoWrapSeq = 0;

    var AUTO_ROW_SELECTORS = [
        '[data-toolbar-mobile-host]',
        '.ti-ticket-table-filters-row',
        '.admin-filter-group',
        '.carreiras-filters-row'
    ];

    var syncingFields = false;

    function fieldValue(field) {
        if (!field) return '';
        if (field.type === 'checkbox' || field.type === 'radio') {
            return field.checked ? '1' : '0';
        }
        return String(field.value || '');
    }

    function copyValues(fromEl, toEl, silent) {
        if (!fromEl || !toEl || fromEl === toEl || syncingFields) return;

        var changed = false;
        if (fromEl.type === 'checkbox' || fromEl.type === 'radio') {
            changed = toEl.checked !== fromEl.checked;
            if (changed) toEl.checked = fromEl.checked;
        } else {
            changed = fieldValue(toEl) !== fieldValue(fromEl);
            if (changed) toEl.value = fromEl.value;
        }

        if (silent || !changed) return;

        syncingFields = true;
        try {
            if (toEl.tagName === 'SELECT') {
                syncFilterSelectSizer(toEl);
            }
            toEl.dispatchEvent(new Event('input', { bubbles: true }));
            toEl.dispatchEvent(new Event('change', { bubbles: true }));
            var host = toEl.closest('[data-toolbar-controls-dual]');
            if (host) {
                document.dispatchEvent(new CustomEvent('unio-toolbar-mobile-synced', {
                    bubbles: true,
                    detail: { host: host }
                }));
            }
        } finally {
            syncingFields = false;
        }
    }

    function findSourceField(inline, cloneField) {
        if (!inline || !cloneField) return null;
        if (cloneField.name) {
            var byName = inline.querySelector('[name="' + CSS.escape(cloneField.name) + '"]');
            if (byName) return byName;
        }
        if (cloneField.id) {
            var byId = inline.querySelector('#' + CSS.escape(cloneField.id));
            if (byId) return byId;
        }
        if (cloneField.hasAttribute('data-ti-filter')) {
            return inline.querySelector('[data-ti-filter="' + cloneField.getAttribute('data-ti-filter') + '"]');
        }
        if (cloneField.hasAttribute('data-ti-filter-search')) {
            return inline.querySelector('[data-ti-filter-search]');
        }
        return null;
    }

    function findCloneField(mount, sourceField) {
        if (!mount || !sourceField) return null;
        if (sourceField.name) {
            var byName = mount.querySelector('[name="' + CSS.escape(sourceField.name) + '"]');
            if (byName) return byName;
        }
        if (sourceField.id) {
            var byId = mount.querySelector('#' + CSS.escape(sourceField.id));
            if (byId) return byId;
        }
        if (sourceField.hasAttribute('data-ti-filter')) {
            return mount.querySelector('[data-ti-filter="' + sourceField.getAttribute('data-ti-filter') + '"]');
        }
        if (sourceField.hasAttribute('data-ti-filter-search')) {
            return mount.querySelector('[data-ti-filter-search]');
        }
        return null;
    }

    function syncAllToSource(host, mount) {
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!inline || !mount) return;

        mount.querySelectorAll('input, select, textarea').forEach(function (cloneField) {
            var sourceField = findSourceField(inline, cloneField);
            if (sourceField) {
                copyValues(cloneField, sourceField, false);
            }
        });
    }

    function syncAllToClone(host, mount) {
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!inline || !mount) return;

        inline.querySelectorAll('input, select, textarea').forEach(function (sourceField) {
            var cloneField = findCloneField(mount, sourceField);
            if (cloneField) {
                copyValues(sourceField, cloneField, true);
            }
        });
    }

    function bindCloneFields(host, mount) {
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!inline || !mount) return;

        mount.querySelectorAll('input, select, textarea').forEach(function (cloneField) {
            var sourceField = findSourceField(inline, cloneField);
            if (!sourceField || sourceField === cloneField) return;

            cloneField.setAttribute('data-toolbar-mobile-clone', '1');
            if (cloneField.id) {
                cloneField.setAttribute('data-toolbar-mobile-source-id', cloneField.id);
                cloneField.removeAttribute('id');
            }

            cloneField.addEventListener('input', function () {
                copyValues(cloneField, sourceField, false);
                syncFilterSelectSizer(sourceField);
            });
            cloneField.addEventListener('change', function () {
                copyValues(cloneField, sourceField, false);
                syncFilterSelectSizer(sourceField);
                syncFilterSelectSizer(cloneField);
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

    function syncFilterSelectSizer(field) {
        if (window.UnioFilterSelect && typeof window.UnioFilterSelect.sync === 'function' && field) {
            window.UnioFilterSelect.sync(field);
        }
    }

    function reorderSearchFirst(container) {
        if (!container) return;

        var search = container.querySelector(
            ':scope > .filter-group--search, :scope > .ti-ticket-table-search'
        );
        if (!search) {
            var nested = container.querySelector(
                ':scope > .ti-ticket-table-filters-row, :scope > .admin-filter-group, :scope > .carreiras-filters-row'
            );
            if (nested) {
                search = nested.querySelector('.filter-group--search, .ti-ticket-table-search, [data-ti-filter-search]');
                if (search && search.parentElement === nested) {
                    nested.insertBefore(search, nested.firstChild);
                }
            }
            return;
        }
        container.insertBefore(search, container.firstChild);
    }

    function bindSourceFields(host, mount) {
        var inline = host.querySelector('.toolbar-inline-controls');
        if (!inline || !mount) return;

        inline.querySelectorAll('input, select, textarea').forEach(function (sourceField) {
            var cloneField = findCloneField(mount, sourceField);
            if (!cloneField) return;

            function syncToClone() {
                copyValues(sourceField, cloneField, true);
                syncFilterSelectSizer(cloneField);
            }

            sourceField.addEventListener('input', syncToClone);
            sourceField.addEventListener('change', syncToClone);
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

        reorderSearchFirst(wrapper);
        prepareSearchClone(mount);
        syncAllToClone(host, mount);
        bindCloneFields(host, mount);
        bindSourceFields(host, mount);

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

    function hasFilterControls(root) {
        return root.querySelectorAll(
            '.filter-group, .toolbar-filter-actions, [data-ti-filter], [data-ti-filter-search], [data-filter-select]'
        ).length > 0;
    }

    function hideEmptyDuals() {
        document.querySelectorAll('[data-toolbar-controls-dual]').forEach(function (host) {
            var inline = host.querySelector('.toolbar-inline-controls');
            var fab = host.querySelector('.toolbar-mobile-fab');
            if (!inline || !fab) return;
            var empty = !hasFilterControls(inline);
            fab.hidden = empty;
            host.classList.toggle('toolbar-controls-dual--empty', empty);
        });
    }

    function ensureOffcanvas(id, title) {
        if (document.querySelector('[data-unio-offcanvas="' + id + '"]')) return;

        var root = document.createElement('div');
        root.className = 'unio-offcanvas unio-offcanvas--bottom unio-offcanvas--toolbar-mobile unio-offcanvas--md';
        root.setAttribute('data-unio-offcanvas', id);
        root.setAttribute('data-toolbar-mobile-offcanvas', '');
        root.setAttribute('aria-hidden', 'true');

        var backdrop = document.createElement('div');
        backdrop.className = 'unio-offcanvas-backdrop';
        backdrop.setAttribute('data-unio-offcanvas-close', '');
        backdrop.setAttribute('tabindex', '-1');
        backdrop.setAttribute('aria-hidden', 'true');

        var drawer = document.createElement('aside');
        drawer.className = 'unio-offcanvas-drawer';
        drawer.setAttribute('role', 'dialog');
        drawer.setAttribute('aria-modal', 'true');

        var header = document.createElement('header');
        header.className = 'unio-offcanvas-header';
        header.innerHTML =
            '<div class="unio-offcanvas-header-main">' +
                '<div class="unio-offcanvas-icon" aria-hidden="true"><i class="fas fa-sliders"></i></div>' +
                '<div class="unio-offcanvas-heading">' +
                    '<h2 class="unio-offcanvas-title">' + (title || 'Filtros e busca') + '</h2>' +
                '</div>' +
            '</div>' +
            '<button type="button" class="unio-offcanvas-close" data-unio-offcanvas-close aria-label="Fechar">' +
                '<i class="fas fa-times" aria-hidden="true"></i>' +
            '</button>';

        var body = document.createElement('div');
        body.className = 'unio-offcanvas-body';
        var mount = document.createElement('div');
        mount.className = 'toolbar-mobile-offcanvas-mount';
        mount.setAttribute('data-toolbar-mobile-mount', id);
        body.appendChild(mount);
        drawer.appendChild(header);
        drawer.appendChild(body);
        root.appendChild(backdrop);
        root.appendChild(drawer);
        document.body.appendChild(root);

        if (window.UnioOffcanvas && typeof window.UnioOffcanvas.refresh === 'function') {
            window.UnioOffcanvas.refresh();
        }
    }

    function autoWrapRow(row) {
        if (!row || row.dataset.toolbarMobileWrapped === '1') return;
        if (row.closest('[data-toolbar-controls-dual]')) return;
        if (row.closest('.toolbar-mobile-offcanvas-controls')) return;
        if (row.closest('.apps-launcher-panel')) return;
        if (!hasFilterControls(row)) return;

        var id = row.getAttribute('data-toolbar-mobile-host')
            || row.getAttribute('data-toolbar-offcanvas-id')
            || ('toolbar-auto-' + (++autoWrapSeq));

        var dual = document.createElement('div');
        dual.className = 'toolbar-controls-dual';
        dual.setAttribute('data-toolbar-controls-dual', '');
        dual.setAttribute('data-toolbar-offcanvas-id', id);

        var inline = document.createElement('div');
        inline.className = 'toolbar-inline-controls';

        while (row.firstChild) {
            inline.appendChild(row.firstChild);
        }
        dual.appendChild(inline);

        var fab = document.createElement('button');
        fab.type = 'button';
        fab.className = 'toolbar-mobile-fab';
        fab.setAttribute('data-unio-offcanvas-open', id);
        fab.setAttribute('aria-label', 'Filtros e busca');
        fab.setAttribute('title', 'Filtros e busca');
        fab.innerHTML = '<i class="fas fa-sliders" aria-hidden="true"></i>';
        dual.appendChild(fab);

        row.appendChild(dual);
        ensureOffcanvas(id, row.getAttribute('data-toolbar-mobile-label') || 'Filtros e busca');
        row.dataset.toolbarMobileWrapped = '1';
    }

    function discoverFilterRows() {
        var found = new Set();

        AUTO_ROW_SELECTORS.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (el) {
                if (!el.closest('[data-toolbar-controls-dual]')) {
                    found.add(el);
                }
            });
        });

        document.querySelectorAll('.filter-group').forEach(function (fg) {
            var parent = fg.parentElement;
            if (!parent) return;
            if (parent.closest('[data-toolbar-controls-dual], .toolbar-mobile-offcanvas-controls, .toolbar-inline-controls, .apps-launcher-panel, .global-search-panel')) {
                return;
            }
            var count = parent.querySelectorAll(':scope > .filter-group, :scope > .toolbar-filter-actions').length;
            var hasSearch = parent.querySelector(
                '.filter-group--search, [type="search"], [data-ti-filter-search]'
            );
            if (count >= 2 || (count >= 1 && hasSearch)) {
                found.add(parent);
            }
        });

        return Array.from(found);
    }

    function initAutoWrap() {
        discoverFilterRows().forEach(autoWrapRow);
        hideEmptyDuals();
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
        document.addEventListener('DOMContentLoaded', initAutoWrap);
    } else {
        initAutoWrap();
    }
})();
