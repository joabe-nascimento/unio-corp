(function () {
    'use strict';

    var root = document.getElementById('mktModuloRoot');
    if (!root) {
        return;
    }

    var pulsoUrl = root.getAttribute('data-pulso-url') || '';
    var likeUrl = root.getAttribute('data-like-url') || '';
    var commentUrl = root.getAttribute('data-comment-url') || '';
    var newsEl = root.querySelector('[data-modulo-news]');
    var commentsEl = root.querySelector('[data-modulo-comments]');
    var likeBtn = root.querySelector('[data-modulo-like]');
    var likeCountEl = root.querySelector('[data-modulo-like-count]');
    var updatedEl = root.querySelector('[data-modulo-updated]');
    var commentForm = root.querySelector('[data-modulo-comment-form]');
    var pollTimer = null;

    var visitorKey = 'unio-modulo-visitor';
    var visitorId = localStorage.getItem(visitorKey);
    if (!visitorId) {
        visitorId = 'v_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
        localStorage.setItem(visitorKey, visitorId);
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderNews(items) {
        if (!newsEl) {
            return;
        }
        if (!items || !items.length) {
            newsEl.innerHTML = '<li class="mkt-modulo-news-empty">Nenhuma notícia disponível no momento.</li>';
            return;
        }
        newsEl.innerHTML = items.map(function (item) {
            var url = item.url ? escapeHtml(item.url) : '#';
            var source = item.source ? '<span>' + escapeHtml(item.source) + '</span>' : '';
            return (
                '<li class="mkt-modulo-news-item">' +
                    '<span class="mkt-modulo-news-item__icon"><i class="fas ' + escapeHtml(item.icon || 'fa-newspaper') + '" aria-hidden="true"></i></span>' +
                    '<div class="mkt-modulo-news-item__body">' +
                        '<a class="mkt-modulo-news-item__link" href="' + url + '" target="_blank" rel="noopener noreferrer">' +
                            escapeHtml(item.text || '') +
                        '</a>' +
                        '<div class="mkt-modulo-news-item__meta">' + source +
                            '<time>' + escapeHtml(item.ago || 'agora') + '</time>' +
                        '</div>' +
                    '</div>' +
                '</li>'
            );
        }).join('');
    }

    function renderComments(items) {
        if (!commentsEl) {
            return;
        }
        if (!items || !items.length) {
            commentsEl.innerHTML = '<li class="mkt-modulo-comments__empty">Seja o primeiro a comentar.</li>';
            return;
        }
        commentsEl.innerHTML = items.map(function (item) {
            return (
                '<li class="mkt-modulo-comment">' +
                    '<strong>' + escapeHtml(item.author || 'Visitante') + '</strong>' +
                    '<p>' + escapeHtml(item.text || '') + '</p>' +
                    '<time>' + escapeHtml(item.ago || 'agora') + '</time>' +
                '</li>'
            );
        }).join('');
    }

    function renderLikes(likes) {
        if (!likeBtn || !likeCountEl || !likes) {
            return;
        }
        likeCountEl.textContent = String(likes.count || 0);
        likeBtn.setAttribute('aria-pressed', likes.liked ? 'true' : 'false');
        likeBtn.classList.toggle('is-liked', !!likes.liked);
        var icon = likeBtn.querySelector('i');
        if (icon) {
            icon.className = likes.liked ? 'fas fa-heart' : 'far fa-heart';
        }
    }

    function applySnapshot(data) {
        renderNews(data.news || data.activities || []);
        renderComments(data.comments || []);
        renderLikes(data.likes || {});
        if (updatedEl && data.updated_at) {
            var dt = new Date(data.updated_at);
            if (!isNaN(dt.getTime())) {
                updatedEl.textContent = 'Atualizado às ' + dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            }
        }
    }

    function fetchJson(url, options) {
        options = options || {};
        options.headers = Object.assign({
            'Accept': 'application/json',
            'X-Visitor-Id': visitorId
        }, options.headers || {});
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(Object.assign({ visitor_id: visitorId }, options.body));
        }
        return fetch(url, options).then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        });
    }

    function refreshPulso() {
        if (!pulsoUrl) {
            return;
        }
        fetchJson(pulsoUrl)
            .then(applySnapshot)
            .catch(function () {
                if (updatedEl) {
                    updatedEl.textContent = 'Reconectando…';
                }
            });
    }

    if (likeBtn) {
        likeBtn.addEventListener('click', function () {
            if (!likeUrl) {
                return;
            }
            likeBtn.disabled = true;
            fetchJson(likeUrl, { method: 'POST', body: { visitor_id: visitorId } })
                .then(applySnapshot)
                .finally(function () {
                    likeBtn.disabled = false;
                });
        });
    }

    if (commentForm) {
        commentForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!commentUrl) {
                return;
            }
            var authorInput = commentForm.querySelector('[name="author"]');
            var textInput = commentForm.querySelector('[name="text"]');
            var author = authorInput ? authorInput.value.trim() : '';
            var text = textInput ? textInput.value.trim() : '';
            if (!text) {
                return;
            }
            var submitBtn = commentForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
            fetchJson(commentUrl, {
                method: 'POST',
                body: { visitor_id: visitorId, author: author, text: text }
            })
                .then(function (data) {
                    applySnapshot(data);
                    if (textInput) {
                        textInput.value = '';
                    }
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                });
        });
    }

    refreshPulso();
    pollTimer = window.setInterval(refreshPulso, 60000);

    window.addEventListener('beforeunload', function () {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
    });
})();
