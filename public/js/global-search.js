/**
 * Busca global no header — indexa o menu lateral + membros (JSON do servidor).
 * Atalho Ctrl/⌘+K, navegação por teclado, destaque do termo e histórico recente.
 */
(function () {
    'use strict';

    var TYPE_LABELS = { hub: 'Hub', app: 'App', member: 'Membro' };
    var RECENT_KEY = 'unio-global-search-recent';
    var RECENT_MAX = 8;

    function normalize(str) {
        return (str || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function escapeHtml(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function highlightLabel(label, q) {
        if (!q) {
            return escapeHtml(label);
        }

        var normLabel = normalize(label);
        var parts = [];
        var cursor = 0;
        var searchFrom = 0;

        while (searchFrom < normLabel.length) {
            var idx = normLabel.indexOf(q, searchFrom);
            if (idx === -1) {
                break;
            }

            parts.push(escapeHtml(label.slice(cursor, idx)));
            parts.push('<mark class="global-search-mark">' + escapeHtml(label.slice(idx, idx + q.length)) + '</mark>');
            cursor = idx + q.length;
            searchFrom = idx + q.length;
        }

        parts.push(escapeHtml(label.slice(cursor)));
        return parts.join('');
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

    function readRecents() {
        try {
            var data = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
            return Array.isArray(data) ? data.slice(0, RECENT_MAX) : [];
        } catch (e) {
            return [];
        }
    }

    function writeRecent(item) {
        if (!item || !item.url) {
            return;
        }

        var entry = {
            type: item.type,
            label: item.label,
            subtitle: item.subtitle || '',
            url: item.url,
            icon: item.icon || '',
            initials: item.initials || '',
            email: item.email || '',
        };

        var recents = readRecents().filter(function (r) {
            return r.url !== entry.url;
        });
        recents.unshift(entry);
        localStorage.setItem(RECENT_KEY, JSON.stringify(recents.slice(0, RECENT_MAX)));
    }

    function initRoot(root) {
        var input = root.querySelector('.global-search-input');
        var panel = root.querySelector('.global-search-panel');
        var resultsEl = root.querySelector('[data-search-results]');
        var emptyEl = root.querySelector('[data-search-empty]');
        var recentLabelEl = root.querySelector('[data-search-recent-label]');
        var filters = root.querySelectorAll('.global-search-filter[data-filter]');
        var helixBtn = root.querySelector('[data-helix-open]');

        if (!input || !panel) {
            return;
        }

        var index = buildSidebarIndex().concat(parseMembers(root.getAttribute('data-members')));
        var activeFilter = 'all';
        var debounceTimer;
        var activeIndex = -1;
        var visibleItems = [];

        function positionPanel() {
            if (panel.hidden) {
                return;
            }
            var wrap = root.querySelector('.global-search-input-wrap');
            if (!wrap) {
                return;
            }
            var rect = wrap.getBoundingClientRect();
            panel.style.position = 'fixed';
            panel.style.top = Math.round(rect.bottom + 8) + 'px';
            panel.style.left = Math.round(rect.left) + 'px';
            panel.style.width = Math.round(rect.width) + 'px';
            panel.style.right = 'auto';
            panel.style.zIndex = '1060';
        }

        function resetPanelPosition() {
            panel.style.position = '';
            panel.style.top = '';
            panel.style.left = '';
            panel.style.width = '';
            panel.style.right = '';
            panel.style.zIndex = '';
        }

        function setOpen(open) {
            root.classList.toggle('is-open', open);
            input.setAttribute('aria-expanded', open ? 'true' : 'false');
            panel.hidden = !open;
            document.body.classList.toggle('global-search-open', open);
            if (open) {
                positionPanel();
            } else {
                resetPanelPosition();
                activeIndex = -1;
                input.removeAttribute('aria-activedescendant');
            }
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

        function filterRecents(recents) {
            if (activeFilter === 'all') {
                return recents;
            }
            return recents.filter(function (item) {
                return item.type === activeFilter;
            });
        }

        function setActiveIndex(nextIndex) {
            var links = resultsEl.querySelectorAll('.global-search-result');
            if (!links.length) {
                activeIndex = -1;
                input.removeAttribute('aria-activedescendant');
                return;
            }

            if (nextIndex < 0) {
                nextIndex = links.length - 1;
            } else if (nextIndex >= links.length) {
                nextIndex = 0;
            }

            activeIndex = nextIndex;
            links.forEach(function (link, i) {
                var on = i === activeIndex;
                link.classList.toggle('is-focused', on);
                link.setAttribute('aria-selected', on ? 'true' : 'false');
                if (on) {
                    input.setAttribute('aria-activedescendant', link.id);
                    link.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        function buildResultLink(item, q, index) {
            var li = document.createElement('li');
            li.setAttribute('role', 'presentation');

            var a = document.createElement('a');
            a.className = 'global-search-result';
            a.href = item.url;
            a.id = 'globalSearchOption' + index;
            a.setAttribute('role', 'option');
            a.setAttribute('aria-selected', 'false');
            a.dataset.searchIndex = String(index);

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
            strong.innerHTML = highlightLabel(item.label, q);
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

            a.addEventListener('click', function () {
                writeRecent(item);
            });

            li.appendChild(a);
            return li;
        }

        function render() {
            var q = normalize(input.value.trim());
            var showingRecents = !q;
            var matches;

            if (q) {
                matches = index.filter(function (item) {
                    return matchItem(item, q);
                }).slice(0, 12);
            } else {
                matches = filterRecents(readRecents()).slice(0, RECENT_MAX);
            }

            visibleItems = matches;
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');

            if (matches.length === 0) {
                resultsEl.hidden = true;
                emptyEl.hidden = false;
                if (recentLabelEl) {
                    recentLabelEl.hidden = true;
                }
                if (q) {
                    emptyEl.querySelector('.global-search-empty-title').textContent = 'Nenhum resultado';
                    emptyEl.querySelector('.global-search-empty-text').textContent =
                        'Tente outro termo ou altere o filtro (Hubs, Apps, Membros).';
                } else if (activeFilter !== 'all') {
                    emptyEl.querySelector('.global-search-empty-title').textContent = 'Nada neste filtro';
                    emptyEl.querySelector('.global-search-empty-text').textContent =
                        'Digite para buscar ou volte ao filtro "Tudo".';
                } else {
                    emptyEl.querySelector('.global-search-empty-title').textContent = 'Nada por aqui ainda!';
                    emptyEl.querySelector('.global-search-empty-text').textContent =
                        'Use Ctrl+K (ou ⌘K) e digite para descobrir núcleos, apps e membros.';
                }
                return;
            }

            emptyEl.hidden = true;
            resultsEl.hidden = false;
            resultsEl.innerHTML = '';

            if (recentLabelEl) {
                recentLabelEl.hidden = !showingRecents;
            }

            matches.forEach(function (item, i) {
                resultsEl.appendChild(buildResultLink(item, q, i));
            });
        }

        function openActiveResult() {
            if (activeIndex >= 0 && visibleItems[activeIndex]) {
                writeRecent(visibleItems[activeIndex]);
                window.location.href = visibleItems[activeIndex].url;
                return;
            }

            var first = resultsEl.querySelector('.global-search-result');
            if (first) {
                first.click();
            }
        }

        input.addEventListener('focus', function () {
            setOpen(true);
            render();
        });

        window.addEventListener('resize', positionPanel);
        window.addEventListener('scroll', positionPanel, true);

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                setOpen(true);
                render();
            }, 120);
        });

        input.addEventListener('keydown', function (e) {
            if (!root.classList.contains('is-open')) {
                return;
            }

            var links = resultsEl.querySelectorAll('.global-search-result');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!links.length) {
                    return;
                }
                setActiveIndex(activeIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!links.length) {
                    return;
                }
                setActiveIndex(activeIndex - 1);
            } else if (e.key === 'Enter') {
                if (links.length) {
                    e.preventDefault();
                    openActiveResult();
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                setOpen(false);
                input.blur();
            }
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
        });

        resultsEl.setAttribute('role', 'listbox');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-haspopup', 'listbox');
        input.setAttribute('aria-controls', resultsEl.id || 'globalSearchResults');
    }

    function initKbdHint() {
        var isMac = /Mac|iPhone|iPad|iPod/i.test(navigator.platform || navigator.userAgent || '');
        document.querySelectorAll('.global-search-kbd').forEach(function (kbd) {
            kbd.textContent = isMac ? '⌘ K' : 'Ctrl K';
        });
    }

    function boot() {
        document.querySelectorAll('.global-search-backdrop').forEach(function (el) {
            el.remove();
        });
        initKbdHint();
        document.querySelectorAll('[data-global-search]').forEach(initRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
