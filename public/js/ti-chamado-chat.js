(function () {
    'use strict';

    var AV_PALETTE = ['av-c1', 'av-c2', 'av-c3', 'av-c4', 'av-c5', 'av-c6', 'av-c7', 'av-c8'];
    var POLL_MS = 2500;
    var SESSION_KEY = 'ti-chat-active';
    var NOTIF_SINCE_KEY = 'ti-notif-since';
    var sessionPollTimer = null;

    window.TiChamadoChat = window.TiChamadoChat || {};

    function stopSessionPolling() {
        if (sessionPollTimer) {
            window.clearInterval(sessionPollTimer);
            sessionPollTimer = null;
        }
    }

    /** Chamado sumiu / sem acesso à área de trabalho atual — encerra sessão fantasma. */
    function abandonChatSession() {
        stopSessionPolling();
        writeSession(null);
        syncGlobalLauncher();
    }

    function parsePollResponse(response) {
        if (!response) {
            return Promise.resolve(null);
        }
        if (response.status === 404 || response.status === 403 || response.status === 401) {
            abandonChatSession();
            return Promise.resolve(null);
        }
        return response.json().catch(function () {
            return null;
        });
    }

    function readJson(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try { return JSON.parse(el.textContent || '{}'); } catch (e) { return null; }
    }

    function readSession() {
        try {
            var raw = localStorage.getItem(SESSION_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) {
            return null;
        }
    }

    function writeSession(data) {
        try {
            if (!data) {
                localStorage.removeItem(SESSION_KEY);
                return;
            }
            localStorage.setItem(SESSION_KEY, JSON.stringify(data));
        } catch (e) { /* ignore */ }
        syncGlobalLauncher();
    }

    function mergeSession(partial) {
        var current = readSession() || {};
        writeSession(Object.assign({}, current, partial, { updatedAt: Date.now() }));
    }

    function initials(name) {
        var parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        var first = parts[0].charAt(0);
        var last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
        return (first + last).toUpperCase();
    }

    function avatarClass(name, index) {
        return AV_PALETTE[(String(name || '').length + (index || 0)) % AV_PALETTE.length];
    }

    function splitDateTime(at) {
        var parts = String(at || '').split(' ');
        return { date: parts[0] || '', time: parts.length > 1 ? parts[parts.length - 1] : at };
    }

    function escapeHtml(text) {
        return String(text || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderAvatarHtml(name, size, colorClass) {
        var sz = size || 'sm';
        return '<div class="member-avatar member-avatar--' + sz + ' ' + colorClass + '" title="' + escapeHtml(name) + '">' +
            escapeHtml(initials(name)) + '</div>';
    }

    function isMine(msg, viewerRole) {
        return (viewerRole === 'ti' && msg.role === 'ti')
            || (viewerRole === 'solicitante' && msg.role === 'solicitante');
    }

    function buildDefaultMsg(msg, index, requesterName, viewerRole) {
        var mine = isMine(msg, viewerRole);
        var side = mine ? 'right' : 'left';
        var dt = splitDateTime(msg.at);
        var avatarName = msg.role === 'ti' ? (msg.actor || 'Equipe TI') : requesterName;
        var displayName = msg.display_name || (msg.role === 'ti' ? 'Equipe de TI' : requesterName);
        var avColor = avatarClass(avatarName, index);

        return '<div class="ti-chat-msg ti-chat-msg--' + side + '" data-ti-chat-msg data-ti-chat-role="' + msg.role + '" data-chat-seq="' + (msg.seq != null ? msg.seq : index) + '">' +
            '<div class="ti-chat-msg-row">' +
            renderAvatarHtml(avatarName, 'sm', avColor) +
            '<div class="ti-chat-bubble">' +
            '<div class="ti-chat-bubble-header">' +
            '<span class="ti-chat-sender">' + escapeHtml(displayName) + '</span>' +
            '<time class="ti-chat-time" datetime="' + escapeHtml(msg.at) + '">' + escapeHtml(dt.time) + '</time>' +
            '</div>' +
            '<p class="ti-chat-text mb-0">' + escapeHtml(msg.body) + '</p>' +
            '</div></div></div>';
    }

    function buildHelixMsg(msg, index, requesterName, viewerRole, prevDate) {
        var helixRole = isMine(msg, viewerRole) ? 'user' : 'assistant';
        var dt = splitDateTime(msg.at);
        var dateSep = '';
        if (dt.date && dt.date !== prevDate) {
            dateSep = '<div class="chat-date-sep" aria-hidden="true"><span>' + escapeHtml(dt.date) + '</span></div>';
        }
        var avatarName = msg.role === 'ti' ? (msg.actor || 'Equipe TI') : requesterName;
        var displayName = msg.display_name || (msg.role === 'ti' ? 'Equipe de TI' : requesterName);
        var avColor = avatarClass(avatarName, index);
        var av = renderAvatarHtml(avatarName, 'sm', avColor);
        var leftAv = helixRole === 'assistant' ? av : '';
        var rightAv = helixRole === 'user' ? av : '';

        return dateSep +
            '<div class="helix-msg helix-msg--' + helixRole + '" data-ti-chat-msg data-ti-chat-role="' + msg.role + '" data-chat-seq="' + (msg.seq != null ? msg.seq : index) + '">' +
            leftAv +
            '<div class="helix-msg-bubble">' +
            '<p class="mb-1"><strong>' + escapeHtml(displayName) + '</strong></p>' +
            '<p class="mb-1">' + escapeHtml(msg.body) + '</p>' +
            '<p class="mb-0 helix-msg-muted">' + escapeHtml(dt.time) + '</p>' +
            '</div>' + rightAv + '</div>';
    }

    function getGlobalLauncher() {
        return document.querySelector('[data-ti-chat-launcher-global]');
    }

    function isOnTicketShowPage(ticketId) {
        var panel = document.querySelector('[data-ti-chat-panel]');
        return !!(panel && panel.getAttribute('data-ticket-id') === ticketId);
    }

    function shouldShowGlobalLauncher(session) {
        return !!(session && session.ticketId && session.minimized);
    }

    function normalizeSessionOnBoot() {
        var session = readSession();
        if (!session || !session.ticketId) return;
        if (!isOnTicketShowPage(session.ticketId) && session.float && !session.minimized) {
            mergeSession({ minimized: true });
        }
    }

    window.TiChamadoChat.syncGlobalLauncher = syncGlobalLauncher;

    function syncGlobalLauncher() {
        var launcher = getGlobalLauncher();
        if (!launcher) return;

        var session = readSession();
        var show = shouldShowGlobalLauncher(session);
        launcher.hidden = !show;

        if (!show || !session) {
            launcher.classList.remove('ti-chamado-chat-alert--blink');
            return;
        }

        var idEl = launcher.querySelector('[data-ti-chat-launcher-id]');
        var badge = launcher.querySelector('[data-ti-chat-launcher-badge]');
        if (idEl) {
            idEl.textContent = session.ticketId || '';
            idEl.hidden = !session.ticketId;
        }
        if (badge) {
            var unread = session.unread || 0;
            badge.hidden = unread <= 0;
            badge.textContent = unread > 9 ? '9+' : String(unread);
            launcher.setAttribute('aria-label', unread > 0
                ? ('Conversa com a TI — ' + unread + ' mensagem' + (unread === 1 ? '' : 'ns') + ' nova' + (unread === 1 ? '' : 's'))
                : 'Conversa com a TI');
        }
        launcher.classList.toggle('ti-chamado-chat-alert--blink', (session.unread || 0) > 0 && show);
    }

    function openChatFromGlobal(session) {
        if (!session || !session.showUrl) return;
        var url = session.showUrl;
        if (url.indexOf('?') === -1) url += '?chat=restore';
        else url += '&chat=restore';
        window.location.href = url;
    }

    function initGlobalLauncher() {
        var launcher = getGlobalLauncher();
        if (!launcher) return;

        launcher.addEventListener('click', function () {
            var session = readSession();
            if (!session) return;

            mergeSession({ unread: 0 });
            launcher.classList.remove('ti-chamado-chat-alert--blink');

            if (isOnTicketShowPage(session.ticketId)) {
                document.dispatchEvent(new CustomEvent('ti-chat-restore-request'));
                return;
            }

            openChatFromGlobal(session);
        });

        normalizeSessionOnBoot();
        syncGlobalLauncher();
    }

    function pollSessionMessages(session) {
        if (!session || !session.pollUrl) return;
        fetch(session.pollUrl + '?after=' + (session.messageCount || 0), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(parsePollResponse)
            .then(function (data) {
                if (!data) {
                    return;
                }
                if (!data.ok) {
                    if (data.error) {
                        abandonChatSession();
                    }
                    return;
                }
                if (!data.messages || !data.messages.length) {
                    if (data.total != null) {
                        mergeSession({ messageCount: data.total });
                    }
                    return;
                }

                var live = readSession() || session;
                var prevCount = live.messageCount || 0;
                var fresh = data.messages.filter(function (m) {
                    return m.seq == null || m.seq >= prevCount;
                });
                var lastSeq = live.lastIncomingSeq != null ? live.lastIncomingSeq : (prevCount - 1);
                var incoming = fresh.filter(function (m) {
                    return m.role !== live.viewerRole && m.seq != null && m.seq > lastSeq;
                });
                var newLastSeq = lastSeq;
                incoming.forEach(function (m) {
                    if (m.seq > newLastSeq) newLastSeq = m.seq;
                });
                mergeSession({
                    messageCount: data.total != null ? data.total : (prevCount + fresh.length),
                    unread: (live.unread || 0) + incoming.length,
                    lastIncomingSeq: newLastSeq,
                });

                if (incoming.length && typeof window.TiChamadoChat.onRemoteMessages === 'function') {
                    window.TiChamadoChat.onRemoteMessages(data.messages);
                }
            })
            .catch(function () { /* ignore */ });
    }

    function initSessionPolling() {
        stopSessionPolling();
        var session = readSession();
        if (!session || !session.pollUrl) return;

        if (isOnTicketShowPage(session.ticketId)) return;

        function tick() {
            var live = readSession();
            if (!live || !live.pollUrl) {
                stopSessionPolling();
                syncGlobalLauncher();
                return;
            }
            pollSessionMessages(live);
        }

        sessionPollTimer = window.setInterval(tick, POLL_MS);
        tick();
    }

    function initManageMotivo() {
        document.querySelectorAll('[data-ti-manage-situacao]').forEach(function (sel) {
            var form = sel.closest('[data-ti-chamado-gestao]');
            var motivo = form ? form.querySelector('[data-ti-manage-motivo]') : null;
            if (!motivo) return;
            function sync() {
                motivo.hidden = sel.value !== 'em_analise';
            }
            sel.addEventListener('change', sync);
            sync();
        });
    }

    function initEmojiPickers() {
        document.querySelectorAll('[data-ti-chamado-message]').forEach(function (form) {
            var toggle = form.querySelector('[data-ti-chat-emoji-toggle]');
            var panel = form.querySelector('[data-ti-chat-emoji-panel]');
            var textarea = form.querySelector('textarea[name="message"]');
            if (!toggle || !panel || !textarea) return;

            toggle.addEventListener('click', function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                var open = !panel.hidden;
                document.querySelectorAll('[data-ti-chat-emoji-panel]').forEach(function (p) { p.hidden = true; });
                panel.hidden = open;
            });

            panel.querySelectorAll('[data-ti-chat-emoji]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var emoji = btn.getAttribute('data-ti-chat-emoji') || '';
                    var start = textarea.selectionStart != null ? textarea.selectionStart : textarea.value.length;
                    var end = textarea.selectionEnd != null ? textarea.selectionEnd : start;
                    var before = textarea.value.slice(0, start);
                    var after = textarea.value.slice(end);
                    textarea.value = before + emoji + after;
                    var pos = start + emoji.length;
                    textarea.setSelectionRange(pos, pos);
                    textarea.focus();
                    panel.hidden = true;
                    textarea.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });
        });

        document.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-ti-chat-emoji-toggle], [data-ti-chat-emoji-panel]')) return;
            document.querySelectorAll('[data-ti-chat-emoji-panel]').forEach(function (p) { p.hidden = true; });
        });
    }

    function initChamadoChat() {
        var cfg = readJson('tiChamadoChatConfig');
        var panel = document.querySelector('[data-ti-chat-panel]');
        if (!cfg || !panel) return;
        if (panel.dataset.tiChatReady === '1') return;
        panel.dataset.tiChatReady = '1';

        var ticketId = cfg.ticketId || panel.getAttribute('data-ticket-id') || '';
        var viewerRole = cfg.viewerRole || panel.getAttribute('data-ti-chat-viewer') || 'solicitante';
        var requesterName = cfg.requesterName || panel.getAttribute('data-ti-chat-requester') || 'Solicitante';
        var messageCount = cfg.initialCount || parseInt(panel.getAttribute('data-ti-chat-initial-count') || '0', 10);
        var unread = 0;
        var pollTimer = null;
        var sending = false;
        var lastIncomingSeq = messageCount > 0 ? messageCount - 1 : -1;
        var restoreBtn = panel.querySelector('[data-ti-chat-restore]');

        function filterNewMessages(messages) {
            var known = {};
            panel.querySelectorAll('[data-chat-seq]').forEach(function (el) {
                known[el.getAttribute('data-chat-seq')] = true;
            });
            return (messages || []).filter(function (m) {
                return m.seq != null && !known[String(m.seq)];
            });
        }

        function syncMessageCount(total) {
            if (typeof total === 'number' && total >= 0) {
                messageCount = total;
            } else {
                var max = -1;
                panel.querySelectorAll('[data-chat-seq]').forEach(function (el) {
                    var s = parseInt(el.getAttribute('data-chat-seq'), 10);
                    if (!isNaN(s) && s > max) max = s;
                });
                messageCount = max + 1;
            }
            mergeSession({ messageCount: messageCount });
        }


        mergeSession({
            ticketId: ticketId,
            showUrl: cfg.showUrl || window.location.pathname,
            pollUrl: cfg.pollUrl,
            viewerRole: viewerRole,
            requesterName: requesterName,
            messageCount: messageCount,
            float: panel.classList.contains('ti-chamado-conversation--float'),
            minimized: panel.classList.contains('ti-chamado-conversation--minimized'),
        });

        window.TiChamadoChat.syncSession = function (partial) {
            mergeSession(Object.assign({ ticketId: ticketId, showUrl: cfg.showUrl, pollUrl: cfg.pollUrl, viewerRole: viewerRole, messageCount: messageCount, unread: unread }, partial));
        };

        window.TiChamadoChat.onRemoteMessages = function (messages) {
            appendMessages(messages);
        };

        window.TiChamadoChat.clearSession = function () {
            writeSession(null);
        };

        function getThreads() {
            return panel.querySelectorAll('[data-ti-chat-thread]');
        }

        function scrollThreads() {
            getThreads().forEach(function (thread) {
                var scrollHost = thread.closest('[data-ti-chat-box-scroll]');
                if (scrollHost) {
                    scrollHost.scrollTop = scrollHost.scrollHeight;
                } else {
                    thread.scrollTop = thread.scrollHeight;
                }
            });
        }

        function ensureThread(kind) {
            var thread = panel.querySelector('[data-ti-chat-thread="' + kind + '"]');
            if (thread) return thread;

            if (kind === 'helix') {
                var helixPane = panel.querySelector('[data-ti-conv-pane="mensagens"]');
                if (!helixPane) return null;
                var empty = helixPane.querySelector('.helix-msg-muted');
                if (empty) {
                    var wrap = empty.closest('.helix-messages');
                    if (wrap) {
                        wrap.innerHTML = '';
                        wrap.setAttribute('data-ti-chat-thread', 'helix');
                        return wrap;
                    }
                }
                thread = document.createElement('div');
                thread.className = 'helix-messages ti-chamado-helix-messages';
                thread.setAttribute('data-ti-chat-thread', 'helix');
                thread.setAttribute('role', 'log');
                helixPane.insertBefore(thread, helixPane.firstChild);
                return thread;
            }

            var docked = panel.querySelector('[data-ti-chat-docked]');
            if (!docked) return null;
            var scrollHost = docked.querySelector('[data-ti-chat-box-scroll]');
            var insertParent = scrollHost || docked;
            var empty = insertParent.querySelector('[data-ti-chat-empty]');
            if (empty) empty.remove();
            thread = document.createElement('div');
            thread.className = 'ti-chat-thread';
            thread.setAttribute('data-ti-chat-thread', 'default');
            thread.setAttribute('role', 'log');
            var firstDetails = insertParent.querySelector('.unio-disclosure, .ti-chamado-conv-details');
            if (firstDetails && !scrollHost) {
                insertParent.insertBefore(thread, firstDetails);
            } else {
                insertParent.insertBefore(thread, insertParent.firstChild);
            }
            return thread;
        }

        function appendMessages(messages) {
            var fresh = filterNewMessages(messages);
            if (!fresh.length) {
                if (messages && messages.length && messages[0].seq != null) {
                    syncMessageCount(Math.max(messageCount, messages[messages.length - 1].seq + 1));
                }
                return fresh;
            }

            var prevHelixDate = null;
            var helixThread = ensureThread('helix');
            if (helixThread) {
                var lastSep = helixThread.querySelector('.chat-date-sep:last-of-type span');
                if (lastSep) prevHelixDate = lastSep.textContent;
            }

            fresh.forEach(function (msg) {
                var idx = msg.seq != null ? msg.seq : messageCount;
                var defaultThread = ensureThread('default');
                if (defaultThread) {
                    defaultThread.insertAdjacentHTML('beforeend', buildDefaultMsg(msg, idx, requesterName, viewerRole));
                }
                var helixThread2 = ensureThread('helix');
                if (helixThread2) {
                    var html = buildHelixMsg(msg, idx, requesterName, viewerRole, prevHelixDate);
                    helixThread2.insertAdjacentHTML('beforeend', html);
                    var dt = splitDateTime(msg.at);
                    if (dt.date) prevHelixDate = dt.date;
                }
            });

            var maxSeq = -1;
            fresh.forEach(function (m) {
                if (m.seq != null && m.seq > maxSeq) maxSeq = m.seq;
            });
            syncMessageCount(maxSeq >= 0 ? maxSeq + 1 : messageCount + fresh.length);
            scrollThreads();
            return fresh;
        }

        function isChatVisible() {
            var floating = panel.classList.contains('ti-chamado-conversation--float');
            var minimized = panel.classList.contains('ti-chamado-conversation--minimized');
            if (minimized) return false;
            if (!floating) return true;
            var activeTab = panel.querySelector('[data-ti-conv-tab].is-active');
            return !activeTab || activeTab.getAttribute('data-ti-conv-tab') === 'mensagens';
        }

        function setBlink(on) {
            if (restoreBtn) restoreBtn.classList.toggle('ti-chamado-chat-alert--blink', on);
            syncGlobalLauncher();
        }

        function updateUnreadBadge() {
            mergeSession({ unread: unread });
            syncGlobalLauncher();
        }

        function markRead() {
            unread = 0;
            lastIncomingSeq = messageCount > 0 ? messageCount - 1 : -1;
            updateUnreadBadge();
            setBlink(false);
            mergeSession({ unread: 0, lastIncomingSeq: lastIncomingSeq });
            syncGlobalLauncher();
        }

        function notifyIncoming(fresh) {
            var incoming = fresh.filter(function (m) {
                return !isMine(m, viewerRole) && m.seq != null && m.seq > lastIncomingSeq;
            });
            if (!incoming.length) return;
            incoming.forEach(function (m) {
                if (m.seq > lastIncomingSeq) lastIncomingSeq = m.seq;
            });
            unread += incoming.length;
            updateUnreadBadge();
            mergeSession({ lastIncomingSeq: lastIncomingSeq });
            if (!isChatVisible()) setBlink(true);
        }

        function stopPanelPoll() {
            if (pollTimer) {
                window.clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function poll() {
            if (sending || !cfg.pollUrl) return;
            fetch(cfg.pollUrl + '?after=' + messageCount, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) {
                    if (r.status === 404 || r.status === 403 || r.status === 401) {
                        stopPanelPoll();
                        abandonChatSession();
                        return null;
                    }
                    return r.json().catch(function () { return null; });
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        if (data && data.error) {
                            stopPanelPoll();
                            abandonChatSession();
                        }
                        return;
                    }
                    if (data.total != null) syncMessageCount(data.total);
                    if (data.messages && data.messages.length) {
                        var fresh = appendMessages(data.messages);
                        if (fresh.length) notifyIncoming(fresh);
                    }
                })
                .catch(function () { /* ignore */ });
        }

        function sendMessage(form) {
            var textarea = form.querySelector('textarea[name="message"]');
            var text = textarea ? textarea.value.trim() : '';
            var fd = new FormData(form);
            if (form.id) {
                document.querySelectorAll('[form="' + form.id + '"]').forEach(function (el) {
                    if (el.type === 'file' && el.name && el.files) {
                        for (var i = 0; i < el.files.length; i++) {
                            fd.append(el.name, el.files[i]);
                        }
                    }
                });
            }
            var hasFiles = false;
            fd.forEach(function (value) {
                if (value instanceof File && value.size > 0) hasFiles = true;
            });
            if (!text && !hasFiles) return Promise.resolve();

            fd.set('ajax', '1');

            var submitBtn = form.querySelector('[type="submit"]');
            if (submitBtn) submitBtn.disabled = true;
            sending = true;

            return fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) {
                        window.alert((data && data.error) || 'Não foi possível enviar a mensagem.');
                        return;
                    }
                    var newMsgs = data.new_messages && data.new_messages.length
                        ? data.new_messages
                        : (data.messages ? data.messages.slice(-1) : []);
                    if (data.total != null) syncMessageCount(data.total);
                    if (newMsgs.length) appendMessages(newMsgs);
                    if (textarea) textarea.value = '';
                    form.querySelectorAll('input[type="file"]').forEach(function (inp) { inp.value = ''; });
                    document.querySelectorAll('[data-ti-chat-emoji-panel]').forEach(function (p) { p.hidden = true; });
                    markRead();
                })
                .catch(function () {
                    window.alert('Erro de rede ao enviar mensagem.');
                })
                .finally(function () {
                    sending = false;
                    if (submitBtn) submitBtn.disabled = false;
                    if (textarea) textarea.focus();
                });
        }

        document.querySelectorAll('[data-ti-chamado-message]').forEach(function (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                sendMessage(form);
            });
        });

        panel.addEventListener('click', function (ev) {
            if (ev.target.closest('[data-ti-conv-tab="mensagens"], [data-ti-chat-restore], [data-ti-chat-float-toggle]')) {
                if (isChatVisible()) markRead();
            }
        });

        var saved = readSession();
        if (saved && saved.ticketId === ticketId) {
            if ((saved.unread || 0) > 0) {
                unread = saved.unread;
                updateUnreadBadge();
                if (!isChatVisible()) setBlink(true);
            }
            if (saved.lastIncomingSeq != null) lastIncomingSeq = saved.lastIncomingSeq;
            if (saved.messageCount != null && saved.messageCount > messageCount) {
                syncMessageCount(saved.messageCount);
            }
        }

        pollTimer = window.setInterval(poll, POLL_MS);
        window.addEventListener('beforeunload', function () {
            if (pollTimer) window.clearInterval(pollTimer);
        });

        scrollThreads();
        poll();
    }

    function initGotoTicket() {
        document.querySelectorAll('[data-ti-chat-goto-ticket]').forEach(function (link) {
            link.addEventListener('click', function (ev) {
                var href = link.getAttribute('href') || '';
                var path = href.split('?')[0];
                if (path && path === window.location.pathname) {
                    ev.preventDefault();
                    var top = document.querySelector('[data-ti-ticket-top]');
                    if (top) top.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    var chatPanel = document.querySelector('[data-ti-chat-panel]');
                    if (chatPanel && chatPanel.classList.contains('ti-chamado-conversation--minimized')) {
                        var restore = chatPanel.querySelector('[data-ti-chat-restore]');
                        if (restore) restore.click();
                    }
                }
            });
        });
    }

    function renderNotificationMenu(items) {
        var menu = document.querySelector('.ti-notifications-menu');
        if (!menu) return;

        var head = menu.querySelector('.ti-notifications-menu-head');
        menu.querySelectorAll('.ti-notifications-item, .ti-notifications-empty').forEach(function (el) {
            el.remove();
        });

        if (!items.length) {
            var empty = document.createElement('span');
            empty.className = 'dropdown-item text-muted ti-notifications-empty';
            empty.textContent = 'Nenhuma notificação pendente.';
            menu.appendChild(empty);
            return;
        }

        items.forEach(function (n) {
            var a = document.createElement('a');
            a.href = n.link || '#';
            a.className = 'dropdown-item ti-notifications-item ti-notifications-item--new';
            a.innerHTML =
                '<span class="ti-notifications-item-title">' + escapeHtml(n.title) + '</span>' +
                '<span class="ti-notifications-item-msg">' + escapeHtml(n.message) + '</span>' +
                '<span class="ti-notifications-item-at">' + escapeHtml(n.at || '') + '</span>';
            menu.appendChild(a);
        });

        if (head && items.length) {
            var badge = head.querySelector('.ti-metric-badge, .badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge bg-warning text-dark';
                head.appendChild(badge);
            }
            badge.textContent = items.length + ' novas';
        }
    }

    function updateNotificationBadge(count) {
        var btn = document.querySelector('.ti-notifications-btn');
        if (!btn) return;
        var badge = btn.querySelector('.ti-notifications-badge');
        if (count <= 0) {
            if (badge) badge.remove();
            btn.classList.remove('ti-notifications-btn--pulse');
            return;
        }
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'ti-notifications-badge';
            btn.appendChild(badge);
        }
        badge.textContent = count > 9 ? '9+' : String(count);
        btn.classList.add('ti-notifications-btn--pulse');
    }

    function initNotificationsPoll() {
        var cfg = readJson('tiHubRealtimeConfig');
        if (!cfg || !cfg.notificationsPollUrl) return;

        var sinceId = 0;
        try {
            sinceId = parseInt(localStorage.getItem(NOTIF_SINCE_KEY) || '0', 10) || 0;
        } catch (e) { /* ignore */ }

        function pollNotif() {
            fetch(cfg.notificationsPollUrl + '?since=' + sinceId, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    updateNotificationBadge(data.count || 0);
                    if (data.latest_id) {
                        sinceId = Math.max(sinceId, data.latest_id);
                        try { localStorage.setItem(NOTIF_SINCE_KEY, String(sinceId)); } catch (e) { /* ignore */ }
                    }
                    if (data.notifications && data.notifications.length) {
                        renderNotificationMenu(data.notifications);
                    }
                    if ((data.new_count || 0) > 0) {
                        updateNotificationBadge(data.count || data.new_count);
                    }
                })
                .catch(function () { /* ignore */ });
        }

        setInterval(pollNotif, POLL_MS);
        pollNotif();
    }

    function boot() {
        initManageMotivo();
        initEmojiPickers();
        initGlobalLauncher();
        initSessionPolling();
        initChamadoChat();
        initGotoTicket();
        initNotificationsPoll();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
