/**
 * Busca global no header — indexa o menu lateral + membros (JSON do servidor).
 */
(function () {
    'use strict';

    var TYPE_LABELS = { hub: 'Hub', app: 'App', member: 'Membro' };

    function normalize(str) {
        return (str || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function labelFromLink(a) {
        var p = a.querySelector('p');
        if (!p) {
            return (a.textContent || '').replace(/\s+/g, ' ').trim();
        }
        var text = '';
        p.childNodes.forEach(function (node) {
            if (node.nodeType === Node.TEXT_NODE) {
                text += node.textContent;
            }
        });
        return text.replace(/\s+/g, ' ').trim() || p.textContent.replace(/\s+/g, ' ').trim();
    }

    function iconFromLink(a) {
        var icon = a.querySelector('.nav-icon');
        return icon ? icon.className.replace(/\s+/g, ' ').trim() : 'fas fa-circle';
    }

    function buildSidebarIndex() {
        var items = [];
        var seen = {};
        var sidebar = document.querySelector('.main-sidebar');
        if (!sidebar) {
            return items;
        }

        sidebar.querySelectorAll('a.nav-link[href]').forEach(function (a) {
            var href = a.getAttribute('href');
            if (!href || href === '#' || href.indexOf('javascript:') === 0) {
                return;
            }

            var label = labelFromLink(a);
            if (!label || seen[href + '|' + label]) {
                return;
            }
            seen[href + '|' + label] = true;

            var type = 'app';
            var hubItem = a.closest('li.nav-item.nav-hub');
            if (hubItem && hubItem.querySelector(':scope > a.nav-link') === a) {
                type = 'hub';
            }

            items.push({
                type: type,
                label: label,
                subtitle: type === 'hub' ? 'Hub' : 'App',
                url: href,
                icon: iconFromLink(a),
                initials: '',
            });
        });

        return items;
    }

    function parseMembers(raw) {
        try {
            var data = JSON.parse(raw || '[]');
            return Array.isArray(data) ? data : [];
        } catch (e) {
            return [];
        }
    }

    function initRoot(root) {
        var input = root.querySelector('.global-search-input');
        var panel = root.querySelector('.global-search-panel');
        var resultsEl = root.querySelector('[data-search-results]');
        var emptyEl = root.querySelector('[data-search-empty]');
        var filters = root.querySelectorAll('.global-search-filter[data-filter]');
        var helixBtn = root.querySelector('[data-helix-open]');

        if (!input || !panel) {
            return;
        }

        var index = buildSidebarIndex().concat(parseMembers(root.getAttribute('data-members')));
        var activeFilter = 'all';
        var debounceTimer;

        function setOpen(open) {
            root.classList.toggle('is-open', open);
            input.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.hidden = !open;
        }

        function setFilter(filter) {
            activeFilter = filter;
            filters.forEach(function (btn) {
                var on = btn.getAttribute('data-filter') === filter;
                btn.classList.toggle('is-active', on);
                btn.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            render();
        }

        function matchItem(item, q) {
            if (activeFilter !== 'all' && item.type !== activeFilter) {
                return false;
            }
            if (!q) {
                return false;
            }
            var hay = normalize(item.label + ' ' + (item.subtitle || '') + ' ' + (item.email || ''));
            return hay.indexOf(q) !== -1;
        }

        function render() {
            var q = normalize(input.value.trim());
            var matches = q ? index.filter(function (item) { return matchItem(item, q); }) : [];

            if (matches.length === 0) {
                resultsEl.hidden = true;
                emptyEl.hidden = false;
                if (q) {
                    emptyEl.querySelector('.global-search-empty-title').textContent = 'Nenhum resultado';
                    emptyEl.querySelector('.global-search-empty-text').textContent =
                        'Tente outro termo ou altere o filtro (Hubs, Apps, Membros).';
                } else {
                    emptyEl.querySelector('.global-search-empty-title').textContent = 'Nada por aqui ainda!';
                    emptyEl.querySelector('.global-search-empty-text').textContent =
                        'Comece digitando no campo de busca para descobrir hubs, apps e membros.';
                }
                return;
            }

            emptyEl.hidden = true;
            resultsEl.hidden = false;
            resultsEl.innerHTML = '';

            matches.slice(0, 12).forEach(function (item) {
                var li = document.createElement('li');
                var a = document.createElement('a');
                a.className = 'global-search-result';
                a.href = item.url;

                if (item.type === 'member' && item.initials) {
                    var av = document.createElement('span');
                    av.className = 'global-search-result-avatar';
                    av.textContent = item.initials;
                    a.appendChild(av);
                } else {
                    var ic = document.createElement('span');
                    ic.className = 'global-search-result-icon';
                    var i = document.createElement('i');
                    i.className = item.icon || 'fas fa-circle';
                    ic.appendChild(i);
                    a.appendChild(ic);
                }

                var body = document.createElement('span');
                body.className = 'global-search-result-body';
                var strong = document.createElement('strong');
                strong.textContent = item.label;
                body.appendChild(strong);
                if (item.subtitle) {
                    var small = document.createElement('small');
                    small.textContent = item.subtitle;
                    body.appendChild(small);
                }
                a.appendChild(body);

                var tag = document.createElement('span');
                tag.className = 'global-search-result-type global-search-result-type--' + item.type;
                tag.textContent = TYPE_LABELS[item.type] || item.type;
                a.appendChild(tag);

                li.appendChild(a);
                resultsEl.appendChild(li);
            });
        }

        input.addEventListener('focus', function () {
            setOpen(true);
            render();
        });

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                setOpen(true);
                render();
            }, 120);
        });

        filters.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setFilter(btn.getAttribute('data-filter'));
            });
        });

        if (helixBtn) {
            helixBtn.addEventListener('click', function () {
                setOpen(false);
                var helixBtnEl = document.getElementById('helixOpenBtn');
                if (helixBtnEl) {
                    helixBtnEl.click();
                }
            });
        }

        document.addEventListener('click', function (e) {
            if (!root.contains(e.target)) {
                setOpen(false);
            }
        });

        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                input.focus();
                setOpen(true);
                render();
            }
            if (e.key === 'Escape' && root.classList.contains('is-open')) {
                setOpen(false);
                input.blur();
            }
        });
    }

    function boot() {
        document.querySelectorAll('[data-global-search]').forEach(initRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
