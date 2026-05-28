(function () {

    'use strict';



    function markRead(url) {

        if (!url) {

            return;

        }

        fetch(url, {

            method: 'POST',

            headers: {

                'X-Requested-With': 'XMLHttpRequest',

                'Content-Type': 'application/json'

            },

            credentials: 'same-origin'

        }).catch(function () {});

    }



    function escapeHtml(str) {

        return String(str)

            .replace(/&/g, '&amp;')

            .replace(/</g, '&lt;')

            .replace(/>/g, '&gt;')

            .replace(/"/g, '&quot;');

    }



    function badgeHtml(article) {

        if (article.is_read) {

            return '<span class="welcome-badge-read">Lido</span><span class="welcome-pro-news-meta-sep" aria-hidden="true">·</span>';

        }

        if (article.is_live) {

            return '<span class="welcome-badge-new">Ao vivo</span><span class="welcome-pro-news-meta-sep" aria-hidden="true">·</span>';

        }

        if (article.is_insight) {

            return '<span class="welcome-badge-insight">Sua área</span><span class="welcome-pro-news-meta-sep" aria-hidden="true">·</span>';

        }

        if (article.is_new) {

            return '<span class="welcome-badge-new">Novo</span><span class="welcome-pro-news-meta-sep" aria-hidden="true">·</span>';

        }

        return '';

    }



    function renderFeedItem(article) {

        var readClass = article.is_read ? ' is-read' : '';

        var insightClass = article.is_insight ? ' is-insight' : '';

        var showUrl = '/bem-vindo/noticias/' + encodeURIComponent(article.slug);



        return '<li data-article-slug="' + escapeHtml(article.slug) + '">' +

            '<article class="welcome-pro-news-item' + readClass + insightClass + '">' +

            '<a href="' + showUrl + '" class="welcome-pro-news-link">' +

            '<div class="welcome-pro-news-icon" aria-hidden="true"><i class="fas ' + escapeHtml(article.icon) + '"></i></div>' +

            '<div class="welcome-pro-news-body">' +

            '<span class="welcome-pro-news-category">' + escapeHtml(article.category) + '</span>' +

            '<h3 class="welcome-pro-news-title">' + escapeHtml(article.title) + '</h3>' +

            '<p class="welcome-pro-news-summary">' + escapeHtml(article.summary) + '</p>' +

            '<div class="welcome-pro-news-meta">' +

            badgeHtml(article) +

            '<time datetime="' + escapeHtml(article.published_at) + '">' + escapeHtml(article.date_label) + '</time>' +

            '<span class="welcome-pro-news-meta-sep" aria-hidden="true">·</span>' +

            '<span>' + escapeHtml(String(article.read_min)) + ' min · Ler artigo</span>' +

            '</div></div>' +

            '<i class="fas fa-arrow-right welcome-pro-news-arrow" aria-hidden="true"></i>' +

            '</a></article></li>';

    }



    function renderEmptyState(filter, refreshed) {

        var message = filter === 'read'

            ? 'Nenhuma notícia lida ainda. Abra um artigo para registrá-lo aqui.'

            : (refreshed

                ? 'Radar atualizado — novas leituras aparecerão aqui conforme a plataforma gerar insights.'

                : 'Tudo lido por aqui. Buscando novas leituras na plataforma…');



        return '<li class="welcome-pro-news-empty" data-welcome-news-empty>' +

            '<p data-welcome-news-empty-text>' + escapeHtml(message) + '</p></li>';

    }



    function renderUpdateItem(update) {

        var dynamicClass = update.is_dynamic ? ' welcome-pro-update--dynamic' : '';

        var itemsHtml = '';

        if (Array.isArray(update.items) && update.items.length > 0) {

            itemsHtml = '<ul class="welcome-pro-update-items">' +

                update.items.map(function (point) {

                    return '<li>' + escapeHtml(point) + '</li>';

                }).join('') +

                '</ul>';

        }



        return '<li class="welcome-pro-update' + dynamicClass + '" data-update-id="' + escapeHtml(update.id) + '">' +

            '<div class="welcome-pro-update-marker" aria-hidden="true"></div>' +

            '<div class="welcome-pro-update-body">' +

            '<div class="welcome-pro-update-head">' +

            '<span class="welcome-pro-update-tag welcome-pro-update-tag--' + escapeHtml(update.type) + '">' + escapeHtml(update.tag) + '</span>' +

            '<time class="welcome-pro-update-date" datetime="' + escapeHtml(update.date) + '">' + escapeHtml(update.date_label) + '</time>' +

            '</div>' +

            '<h3 class="welcome-pro-update-title">' + escapeHtml(update.title) + '</h3>' +

            '<p class="welcome-pro-update-summary">' + escapeHtml(update.summary) + '</p>' +

            itemsHtml +

            '</div></li>';

    }



    function setFeedLoading(section, loading) {

        var list = section.querySelector('[data-welcome-news-list]');

        if (!list) {

            return;

        }

        section.classList.toggle('is-feed-loading', loading);

        var status = section.querySelector('[data-welcome-news-loading-status]');
        if (status) {
            status.hidden = !loading;
        }

        if (loading) {

            list.setAttribute('aria-busy', 'true');

        } else {

            list.removeAttribute('aria-busy');

        }

    }



    function getActiveFilter(section) {

        return section.getAttribute('data-news-filter') || 'unread';

    }



    function setActiveFilter(section, filter) {

        section.setAttribute('data-news-filter', filter);

        section.querySelectorAll('[data-welcome-news-filter]').forEach(function (btn) {

            var active = btn.getAttribute('data-welcome-news-filter') === filter;

            btn.classList.toggle('is-active', active);

            btn.setAttribute('aria-selected', active ? 'true' : 'false');

        });

    }



    function updateNewsMeta(section, meta) {

        if (!meta) {

            return;

        }



        var unreadCount = section.querySelector('[data-welcome-news-unread-count]');

        var readCount = section.querySelector('[data-welcome-news-read-count]');

        if (unreadCount) {

            unreadCount.textContent = String(meta.unread_count || 0);

        }

        if (readCount) {

            readCount.textContent = String(meta.read_count || 0);

        }



        var unreadBadge = section.querySelector('[data-welcome-news-unread]');

        if ((meta.unread_count || 0) > 0) {

            var label = meta.unread_count + ' não lida' + (meta.unread_count > 1 ? 's' : '');

            if (unreadBadge) {

                unreadBadge.textContent = label;

            } else {

                var metaWrap = section.querySelector('[data-welcome-news-header-meta]');

                if (metaWrap) {

                    var span = document.createElement('span');

                    span.className = 'welcome-section-count';

                    span.setAttribute('data-welcome-news-unread', '');

                    span.textContent = label;

                    metaWrap.insertBefore(span, metaWrap.firstChild);

                }

            }

        } else if (unreadBadge) {

            unreadBadge.remove();

        }

    }



    function updateUpdatesMeta(section, meta) {

        if (!meta) {

            return;

        }

        var liveBadge = section.querySelector('[data-welcome-updates-live]');

        if ((meta.dynamic_count || 0) > 0) {

            var label = meta.dynamic_count + ' ao vivo';

            if (liveBadge) {

                liveBadge.textContent = label;

            } else {

                var header = section.querySelector('.welcome-section-header');

                if (header) {

                    var span = document.createElement('span');

                    span.className = 'welcome-section-count welcome-section-count--live';

                    span.setAttribute('data-welcome-updates-live', '');

                    span.textContent = label;

                    header.appendChild(span);

                }

            }

        } else if (liveBadge) {

            liveBadge.remove();

        }

    }



    function refreshFeed(section, options) {

        options = options || {};

        var feedUrl = section.getAttribute('data-news-feed-url');

        var list = section.querySelector('[data-welcome-news-list]');

        if (!feedUrl || !list) {

            return Promise.resolve();

        }



        var filter = options.filter || getActiveFilter(section);

        var params = new URLSearchParams();

        params.set('filter', filter);

        params.set('limit', '4');

        params.set('discover', options.discover === false ? '0' : '1');

        if (options.force) {

            params.set('_', String(Date.now()));

        }



        var url = feedUrl + (feedUrl.indexOf('?') === -1 ? '?' : '&') + params.toString();

        setFeedLoading(section, true);



        return fetch(url, {

            headers: { 'X-Requested-With': 'XMLHttpRequest' },

            credentials: 'same-origin',

            cache: options.force ? 'no-store' : 'default'

        })

            .then(function (response) {

                if (!response.ok) {

                    throw new Error('feed');

                }

                return response.json();

            })

            .then(function (data) {

                if (!Array.isArray(data.items)) {

                    return;

                }



                if (data.items.length === 0) {

                    list.innerHTML = renderEmptyState(filter, !!(data.meta && data.meta.refreshed));

                } else {

                    list.innerHTML = data.items.map(renderFeedItem).join('');

                }



                if (data.meta) {

                    updateNewsMeta(section, data.meta);

                    if (filter === 'unread' && data.items.length === 0 && !data.meta.refreshed) {

                        return refreshFeed(section, { filter: 'unread', discover: true, force: true });

                    }

                }



                section.dispatchEvent(new CustomEvent('welcome-news:refreshed', {

                    bubbles: true,

                    detail: { items: data.items, meta: data.meta || {} }

                }));

            })

            .catch(function () {})

            .finally(function () {

                setFeedLoading(section, false);

            });

    }



    function refreshUpdates(section, options) {

        options = options || {};

        var feedUrl = section.getAttribute('data-updates-feed-url');

        var list = section.querySelector('[data-welcome-updates-list]');

        if (!feedUrl || !list) {

            return Promise.resolve();

        }



        var url = feedUrl;

        if (options.force) {

            url += (url.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();

        }



        section.classList.toggle('is-feed-loading', true);



        return fetch(url, {

            headers: { 'X-Requested-With': 'XMLHttpRequest' },

            credentials: 'same-origin',

            cache: options.force ? 'no-store' : 'default'

        })

            .then(function (response) {

                if (!response.ok) {

                    throw new Error('updates');

                }

                return response.json();

            })

            .then(function (data) {

                if (!Array.isArray(data.items)) {

                    return;

                }

                list.innerHTML = data.items.map(renderUpdateItem).join('');

                updateUpdatesMeta(section, data.meta || {});

            })

            .catch(function () {})

            .finally(function () {

                section.classList.toggle('is-feed-loading', false);

            });

    }



    function bindNewsFilters(section) {

        if (section.getAttribute('data-welcome-news-filter-bound') === '1') {

            return;

        }

        section.setAttribute('data-welcome-news-filter-bound', '1');



        section.addEventListener('click', function (event) {

            var btn = event.target.closest('[data-welcome-news-filter]');

            if (!btn) {

                return;

            }

            var filter = btn.getAttribute('data-welcome-news-filter');

            if (!filter || filter === getActiveFilter(section)) {

                return;

            }

            setActiveFilter(section, filter);

            refreshFeed(section, { filter: filter, force: true });

        });

    }



    function shouldForceRefresh() {

        try {

            return new URLSearchParams(window.location.search).get('refresh_news') === '1';

        } catch (e) {

            return false;

        }

    }



    function cleanRefreshParam() {

        try {

            var url = new URL(window.location.href);

            if (url.searchParams.get('refresh_news') !== '1') {

                return;

            }

            url.searchParams.delete('refresh_news');

            window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash);

        } catch (e) {}

    }



    function scrollToNewsSection() {

        var target = document.getElementById('welcome-news-title');

        if (target) {

            target.scrollIntoView({ behavior: 'smooth', block: 'start' });

        }

    }



    document.addEventListener('DOMContentLoaded', function () {

        var feedSection = document.querySelector('[data-welcome-news-feed]');

        if (feedSection) {

            bindNewsFilters(feedSection);

            var force = shouldForceRefresh();

            if (force) {

                setActiveFilter(feedSection, 'unread');

            }

            refreshFeed(feedSection, { force: force, filter: getActiveFilter(feedSection) }).then(function () {

                if (force) {

                    scrollToNewsSection();

                    cleanRefreshParam();

                }

            });

        }



        var updatesSection = document.querySelector('[data-welcome-updates-feed]');

        if (updatesSection) {

            refreshUpdates(updatesSection, { force: true });

        }



        var readArticle = document.querySelector('.welcome-news-read');

        if (readArticle) {

            var slugMatch = window.location.pathname.match(/\/bem-vindo\/noticias\/([a-z0-9\-]+)/);

            if (slugMatch) {

                markRead('/bem-vindo/api/noticias/' + slugMatch[1] + '/leitura');

            }

        }

    });

})();


