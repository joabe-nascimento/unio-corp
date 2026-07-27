/**
 * Chat Bate Papo — API, Mercure, voz e chamadas WebRTC
 */
(function () {
    'use strict';

    var app = document.getElementById('chatApp');
    if (!app || app.dataset.hasWorkspace !== '1') return;

    var API = app.dataset.apiBase || '/bate-papo/api';
    var USER_ID = parseInt(app.dataset.userId || '0', 10);
    var USER_INITIALS = app.dataset.userInitials || 'U';
    var MERCURE_ENABLED = app.dataset.mercureEnabled === '1';
    var mercureUrl = app.dataset.mercureUrl || '';
    var mercureSource = null;
    var mercureConnected = false;
    var usePollingFallback = false;

    var convList = document.getElementById('chatConvList');
    var chatEmpty = document.getElementById('chatEmpty');
    var chatRoom = document.getElementById('chatRoom');
    var chatMessages = document.getElementById('chatMessages');
    var chatRoomName = document.getElementById('chatRoomName');
    var chatRoomStatus = document.getElementById('chatRoomStatus');
    var chatRoomAvatar = document.getElementById('chatRoomAvatar');
    var chatSearch = document.getElementById('chatSearch');
    var chatForm = document.getElementById('chatForm');
    var chatInput = document.getElementById('chatInput');
    var chatSendBtn = document.getElementById('chatSendBtn');
    var chatBackBtn = document.getElementById('chatBackBtn');
    var chatVoiceRecordBtn = document.getElementById('chatVoiceRecordBtn');
    var chatVoiceCallBtn = document.getElementById('chatVoiceCallBtn');
    var chatToast = document.getElementById('chatToast');
    var chatSidebarBrowse = document.getElementById('chatSidebarBrowse');
    var chatSidebarPanel = document.getElementById('chatSidebarPanel');
    var chatSidebarPanelTitle = document.getElementById('chatSidebarPanelTitle');
    var chatSidebarBackBtn = document.getElementById('chatSidebarBackBtn');
    var chatDirectPanel = document.getElementById('chatDirectPanel');
    var chatGroupPanel = document.getElementById('chatGroupPanel');
    var chatGroupFooter = document.getElementById('chatGroupFooter');
    var chatInfoFooter = document.getElementById('chatInfoFooter');
    var chatColleagueList = document.getElementById('chatColleagueList');
    var chatColleagueSearch = document.getElementById('chatColleagueSearch');
    var chatGroupName = document.getElementById('chatGroupName');
    var chatGroupMemberList = document.getElementById('chatGroupMemberList');
    var chatGroupMemberSearch = document.getElementById('chatGroupMemberSearch');
    var chatGroupCreateBtn = document.getElementById('chatGroupCreateBtn');
    var chatCallOverlay = document.getElementById('chatCallOverlay');
    var chatCallAvatar = document.getElementById('chatCallAvatar');
    var chatCallName = document.getElementById('chatCallName');
    var chatCallStatus = document.getElementById('chatCallStatus');
    var chatCallAcceptBtn = document.getElementById('chatCallAcceptBtn');
    var chatCallEndBtn = document.getElementById('chatCallEndBtn');
    var chatRoomInfoBtn = document.getElementById('chatRoomInfoBtn');
    var chatAttachBtn = document.getElementById('chatAttachBtn');
    var chatFileInput = document.getElementById('chatFileInput');
    var chatReplyBar = document.getElementById('chatReplyBar');
    var chatReplyText = document.getElementById('chatReplyText');
    var chatReplyCancel = document.getElementById('chatReplyCancel');
    var chatLoadMoreWrap = document.getElementById('chatLoadMoreWrap');
    var chatLoadMoreBtn = document.getElementById('chatLoadMoreBtn');
    var chatInfoDirect = document.getElementById('chatInfoDirect');
    var chatInfoLayout = document.getElementById('chatInfoLayout');
    var chatInfoGroup = document.getElementById('chatInfoGroup');
    var chatInfoGroupName = document.getElementById('chatInfoGroupName');
    var chatInfoRenameBtn = document.getElementById('chatInfoRenameBtn');
    var chatInfoMembers = document.getElementById('chatInfoMembers');
    var chatInfoAddToggle = document.getElementById('chatInfoAddToggle');
    var chatInfoAddWrap = document.getElementById('chatInfoAddWrap');
    var chatInfoAddSearch = document.getElementById('chatInfoAddSearch');
    var chatInfoAddList = document.getElementById('chatInfoAddList');
    var chatInfoAddBtn = document.getElementById('chatInfoAddBtn');
    var chatInfoLeaveBtn = document.getElementById('chatInfoLeaveBtn');
    var chatInfoMedia = document.getElementById('chatInfoMedia');
    var chatMediaBody = document.getElementById('chatMediaBody');
    var chatMediaLightbox = document.getElementById('chatMediaLightbox');
    var chatMediaLightboxBody = document.getElementById('chatMediaLightboxBody');
    var chatMediaLightboxClose = document.getElementById('chatMediaLightboxClose');
    var chatMediaGalleryModal = document.getElementById('chatMediaGalleryModal');
    var chatMediaGalleryModalBody = document.getElementById('chatMediaGalleryModalBody');
    var chatMediaGalleryTitle = document.getElementById('chatMediaGalleryTitle');
    var chatMediaGalleryClose = document.getElementById('chatMediaGalleryClose');
    var chatMsgMenu = document.getElementById('chatMsgMenu');
    var mobileMq = window.matchMedia('(max-width: 991.98px)');

    var conversations = [];
    var colleagues = [];
    var activeId = null;
    var activeTab = 'all';
    var replyTo = null;
    var msgMenuTarget = null;
    var infoAddSelected = {};
    var typingHideTimer = null;
    var typingPulseTimer = null;
    var activeAudio = null;
    var activeAudioBtn = null;
    var originalDocTitle = document.title;
    var pollTimer = null;
    var callPollTimer = null;
    var incomingCallTimer = null;
    var lastMessagePoll = null;
    var lastCallPoll = new Date().toISOString();
    var incomingCallSince = new Date().toISOString();

    var mediaRecorder = null;
    var recordChunks = [];
    var recordStartedAt = 0;

    var peerConnection = null;
    var localStream = null;
    var remoteAudio = null;
    var inCall = false;
    var isCaller = false;

    var ICE_SERVERS = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

    function parseInitial() {
        var el = document.getElementById('chatInitialData');
        try { return el ? JSON.parse(el.textContent || '[]') : []; } catch (e) { return []; }
    }

    function api(path, opts) {
        opts = opts || {};
        return fetch(API + path, Object.assign({
            credentials: 'same-origin',
            headers: Object.assign({ 'Accept': 'application/json' }, opts.headers || {}),
        }, opts)).then(function (r) {
            return r.json().catch(function () { return {}; }).then(function (body) {
                if (!r.ok) throw new Error(body.error || ('HTTP ' + r.status));
                return body;
            });
        });
    }

    function closeActionMenu() {
        var toggle = app.querySelector('.chat-action-menu') && app.querySelector('.chat-action-menu').closest('.dropdown');
        if (!toggle) return;
        var btn = toggle.querySelector('[data-toggle="dropdown"]');
        if (btn && window.jQuery) {
            window.jQuery(btn).dropdown('hide');
        } else if (btn) {
            btn.setAttribute('aria-expanded', 'false');
            toggle.classList.remove('show');
            var menu = toggle.querySelector('.dropdown-menu');
            if (menu) menu.classList.remove('show');
        }
    }

    function escapeHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function getConv(id) {
        return conversations.find(function (c) { return c.id === String(id); }) || null;
    }

    function formatTime(iso) {
        try { return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }); } catch (e) { return ''; }
    }

    function formatDateLabel(iso) {
        try {
            var d = new Date(iso);
            var today = new Date();
            if (d.toDateString() === today.toDateString()) return 'Hoje';
            var y = new Date(today); y.setDate(today.getDate() - 1);
            if (d.toDateString() === y.toDateString()) return 'Ontem';
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
        } catch (e) { return ''; }
    }

    function formatVoiceDuration(ms) {
        var s = Math.max(1, Math.round((ms || 0) / 1000));
        var m = Math.floor(s / 60);
        return m + ':' + String(s % 60).padStart(2, '0');
    }

    function showToast(msg) {
        if (!chatToast) return;
        chatToast.textContent = msg;
        chatToast.hidden = false;
        clearTimeout(showToast._t);
        showToast._t = setTimeout(function () { chatToast.hidden = true; }, 3200);
    }

    function filteredConversations() {
        var q = (chatSearch ? chatSearch.value : '').trim().toLowerCase();
        return conversations.filter(function (c) {
            if (activeTab !== 'all' && c.type !== activeTab) return false;
            if (!q) return true;
            return (c.name || '').toLowerCase().indexOf(q) !== -1 || (c.preview || '').toLowerCase().indexOf(q) !== -1;
        });
    }

    function renderListEmpty(title, text, icon) {
        icon = icon || 'fa-inbox';
        return '<div class="empty-state empty-state--compact chat-conv-empty-state">' +
            '<div class="empty-icon"><i class="fas ' + icon + '" aria-hidden="true"></i></div>' +
            '<h6>' + escapeHtml(title) + '</h6>' +
            (text ? '<p>' + escapeHtml(text) + '</p>' : '') +
            '</div>';
    }

    function renderConvList() {
        if (!convList) return;
        var items = filteredConversations();
        var placeholder = document.getElementById('chatConvListPlaceholder');
        if (placeholder) placeholder.hidden = true;
        convList.innerHTML = '';
        if (!items.length) {
            var isFiltered = (chatSearch && chatSearch.value.trim()) || activeTab !== 'all';
            convList.innerHTML = renderListEmpty(
                isFiltered ? 'Nenhuma conversa encontrada' : 'Nenhuma conversa',
                isFiltered ? 'Tente outro termo ou filtro.' : 'Inicie uma conversa ou crie um grupo.',
                isFiltered ? 'fa-search' : 'fa-inbox'
            );
            return;
        }
        items.forEach(function (c) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-conv-item' + (c.id === activeId ? ' chat-conv-item--active' : '');
            btn.dataset.convId = c.id;
            btn.innerHTML =
                '<span class="chat-conv-avatar' + (c.online ? ' chat-conv-avatar--online' : '') + '">' + escapeHtml(c.initials) + '</span>' +
                '<span class="chat-conv-body">' +
                    '<span class="chat-conv-row"><span class="chat-conv-name">' + escapeHtml(c.name) + '</span>' +
                    '<span class="chat-conv-time">' + escapeHtml(c.time_label || '') + '</span></span>' +
                    '<span class="chat-conv-row"><span class="chat-conv-preview">' + escapeHtml(c.preview || '') + '</span>' +
                    (c.unread > 0 ? '<span class="chat-conv-badge">' + c.unread + '</span>' : '') + '</span></span>';
            convList.appendChild(btn);
        });
    }

    function updateDocTitle() {
        var total = conversations.reduce(function (sum, c) { return sum + (c.unread || 0); }, 0);
        document.title = total > 0 ? '(' + total + ') Chat Bate Papo — Unio' : originalDocTitle;
    }

    function buildMessageBody(m, conv) {
        if (m.deleted) {
            return '<p class="chat-msg-deleted mb-0"><i class="fas fa-ban" aria-hidden="true"></i> Mensagem apagada</p>';
        }
        if (m.type === 'voice' && m.voice_url) {
            return '<div class="chat-voice-msg">' +
                '<button type="button" class="chat-voice-play" data-voice-url="' + escapeHtml(m.voice_url) + '"><i class="fas fa-play"></i></button>' +
                '<span class="chat-voice-wave" aria-hidden="true"></span>' +
                '<span class="chat-voice-dur">' + escapeHtml(formatVoiceDuration(m.voice_duration_ms)) + '</span></div>';
        }
        if (m.type === 'image' && m.file_url) {
            return '<a href="' + escapeHtml(m.file_url) + '" target="_blank" rel="noopener" class="chat-image-link">' +
                '<img src="' + escapeHtml(m.file_url) + '" alt="' + escapeHtml(m.file_name || 'Imagem') + '" class="chat-image-preview" loading="lazy"></a>';
        }
        if (m.type === 'file' && m.file_url) {
            if (m.file_mime && m.file_mime.indexOf('video/') === 0) {
                return '<video src="' + escapeHtml(m.file_url) + '" controls class="chat-video-preview" preload="metadata"></video>';
            }
            return '<a href="' + escapeHtml(m.file_url) + '" target="_blank" rel="noopener" class="chat-file-link">' +
                '<i class="fas fa-file-alt" aria-hidden="true"></i> ' + escapeHtml(m.file_name || 'Arquivo') + '</a>';
        }
        return '<p class="mb-0">' + escapeHtml(m.text || '').replace(/\n/g, '<br>') + '</p>';
    }

    function updateLoadMoreBtn(conv) {
        if (!chatLoadMoreWrap) return;
        chatLoadMoreWrap.hidden = !(conv && conv.has_more_older);
    }

    function renderMessages(conv, preserveScroll) {
        if (!chatMessages || !conv) return;
        var prevHeight = 0;
        var prevTop = 0;
        if (preserveScroll) {
            prevHeight = chatMessages.scrollHeight;
            prevTop = chatMessages.scrollTop;
        }
        var html = '';
        var lastDate = '';
        (conv.messages || []).forEach(function (m) {
            var dateLabel = formatDateLabel(m.at);
            if (dateLabel && dateLabel !== lastDate) {
                html += '<div class="chat-date-sep"><span>' + escapeHtml(dateLabel) + '</span></div>';
                lastDate = dateLabel;
            }
            if (m.role === 'system') {
                html += '<div class="chat-msg chat-msg--system"><span>' + escapeHtml(m.text) + '</span></div>';
                return;
            }
            var isUser = m.role === 'user';
            var replyHtml = '';
            if (m.reply_to) {
                replyHtml = '<div class="chat-msg-reply">' +
                    '<span class="chat-msg-reply-sender">' + escapeHtml(m.reply_to.sender || '') + '</span>' +
                    '<span class="chat-msg-reply-text">' + escapeHtml(m.reply_to.text || '') + '</span></div>';
            }
            var body = buildMessageBody(m, conv);
            html += '<div class="chat-msg chat-msg--' + (isUser ? 'user' : 'other') + (m.deleted ? ' chat-msg--deleted' : '') + '" data-msg-id="' + escapeHtml(m.id) + '">' +
                '<div class="chat-msg-bubble" data-msg-id="' + escapeHtml(m.id) + '">' +
                (conv.type === 'group' && !isUser && m.sender ? '<span class="chat-msg-sender">' + escapeHtml(m.sender) + '</span>' : '') +
                replyHtml + body +
                '<span class="chat-msg-time">' + escapeHtml(formatTime(m.at)) + '</span></div></div>';
        });
        chatMessages.innerHTML = html;
        if (preserveScroll) {
            chatMessages.scrollTop = chatMessages.scrollHeight - prevHeight + prevTop;
        } else {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        updateLoadMoreBtn(conv);
    }

    function updateVoiceCallBtn(conv) {
        if (!chatVoiceCallBtn) return;
        var direct = conv && conv.type === 'direct';
        chatVoiceCallBtn.disabled = !direct;
        chatVoiceCallBtn.title = direct ? 'Chamada de voz' : 'Chamada de voz (apenas conversas diretas)';
    }

    function openConversation(id) {
        var conv = getConv(id);
        if (!conv) return;
        activeId = id;
        if (chatEmpty) chatEmpty.hidden = true;
        if (chatRoom) chatRoom.hidden = false;
        if (chatRoomName) chatRoomName.textContent = conv.name;
        if (chatRoomStatus) {
            chatRoomStatus.textContent = conv.type === 'group'
                ? 'Grupo · ' + (conv.member_count || 0) + ' participantes'
                : (conv.online ? 'Online · voz disponível' : 'Offline');
        }
        if (chatRoomAvatar) {
            chatRoomAvatar.textContent = conv.initials;
            chatRoomAvatar.classList.toggle('chat-room-avatar--online', !!conv.online);
        }
        updateVoiceCallBtn(conv);
        updateActionMenu();
        conv.unread = 0;
        renderConvList();
        updateDocTitle();
        clearReply();
        clearTypingStatus();
        api('/conversations/' + id + '/read', { method: 'POST' }).catch(function () {});

        if (!conv.messages || !conv.messages.length) {
            loadMessages(id, true);
        } else {
            renderMessages(conv);
        }
        if (mobileMq.matches) app.classList.add('is-room-open');
        if (chatInput) chatInput.focus();
        ensureMessageRealtime();
    }

    function loadMessages(id, replace) {
        return api('/conversations/' + id + '/messages').then(function (data) {
            var conv = getConv(id);
            if (!conv) return;
            conv.messages = data.messages || [];
            conv.has_more_older = !!data.has_more;
            if (activeId === id) renderMessages(conv);
            if (replace) lastMessagePoll = conv.messages.length ? conv.messages[conv.messages.length - 1].at : new Date().toISOString();
        });
    }

    function loadOlderMessages() {
        var conv = getConv(activeId);
        if (!conv || conv.loadingOlder || !conv.has_more_older || !conv.messages.length) return;
        conv.loadingOlder = true;
        var before = conv.messages[0].at;
        api('/conversations/' + activeId + '/messages?before=' + encodeURIComponent(before))
            .then(function (data) {
                var older = data.messages || [];
                conv.has_more_older = !!data.has_more;
                older.forEach(function (msg) {
                    if (!conv.messages.some(function (m) { return messageId(m) === messageId(msg); })) {
                        conv.messages.unshift(msg);
                    }
                });
                conv.loadingOlder = false;
                if (activeId === conv.id) renderMessages(conv, true);
            }).catch(function () { conv.loadingOlder = false; });
    }

    function refreshConversations() {
        return api('/conversations').then(function (data) {
            colleagues = data.colleagues || [];
            var map = {};
            conversations.forEach(function (c) { map[c.id] = c.messages || []; });
            conversations = (data.conversations || []).map(function (c) {
                if (map[c.id]) c.messages = map[c.id];
                return c;
            });
            renderConvList();
            updateDocTitle();
            updateActionMenu();
            refreshSidebarPanelContent();
            if (activeId) {
                var conv = getConv(activeId);
                updateVoiceCallBtn(conv);
            }
        }).catch(function (err) {
            showToast(err.message || 'Não foi possível atualizar conversas.');
        });
    }

    var sendingMessage = false;

    function sendMessage(text) {
        text = (text || '').trim();
        if (!text || !activeId || sendingMessage) return;
        var payload = { text: text };
        if (replyTo) payload.reply_to_id = parseInt(replyTo.id, 10);
        sendingMessage = true;
        api('/conversations/' + activeId + '/messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then(function (msg) {
            var conv = getConv(activeId);
            if (!conv) return;
            upsertMessage(activeId, msg);
            if (msg.at) lastMessagePoll = msg.at;
            conv.preview = 'Você: ' + text;
            conv.time_label = 'Agora';
            renderConvList();
            clearReply();
            if (chatInput) { chatInput.value = ''; chatInput.style.height = ''; updateSendState(); }
        }).catch(function (err) { showToast(err.message || 'Não foi possível enviar a mensagem.'); })
            .finally(function () { sendingMessage = false; });
    }

    function clearReply() {
        replyTo = null;
        if (chatReplyBar) chatReplyBar.hidden = true;
        if (chatReplyText) chatReplyText.textContent = '';
    }

    function setReply(msg) {
        if (!msg || msg.deleted || msg.role === 'system') return;
        replyTo = msg;
        if (chatReplyBar) chatReplyBar.hidden = false;
        if (chatReplyText) {
            var preview = msg.type === 'voice' ? 'Mensagem de voz' : (msg.type === 'image' ? 'Imagem' : (msg.text || ''));
            chatReplyText.textContent = (msg.sender ? msg.sender + ': ' : '') + preview;
        }
        if (chatInput) chatInput.focus();
    }

    function pulseTyping() {
        if (!activeId) return;
        api('/conversations/' + activeId + '/typing', { method: 'POST' }).catch(function () {});
    }

    function showTypingStatus(name) {
        if (!chatRoomStatus || !name) return;
        chatRoomStatus.textContent = name + ' está digitando…';
        clearTimeout(typingHideTimer);
        typingHideTimer = setTimeout(clearTypingStatus, 3500);
    }

    function clearTypingStatus() {
        if (!chatRoomStatus || !activeId) return;
        var conv = getConv(activeId);
        if (!conv) return;
        chatRoomStatus.textContent = conv.type === 'group'
            ? 'Grupo · ' + (conv.member_count || 0) + ' participantes'
            : (conv.online ? 'Online · voz disponível' : 'Offline');
    }

    function uploadFile(file) {
        if (!activeId || !file) return;
        var fd = new FormData();
        fd.append('file', file);
        if (replyTo) fd.append('reply_to_id', replyTo.id);
        fetch(API + '/conversations/' + activeId + '/file', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (body) {
                    if (!r.ok) throw new Error(body.error || 'upload');
                    return body;
                });
            })
            .then(function (msg) {
                var conv = getConv(activeId);
                if (!conv) return;
                upsertMessage(activeId, msg);
                if (msg.at) lastMessagePoll = msg.at;
                conv.preview = msg.type === 'image' ? 'Você: Imagem' : ('Você: ' + (msg.file_name || 'Arquivo'));
                renderConvList();
                clearReply();
            }).catch(function (err) { showToast(err.message || 'Falha ao enviar arquivo.'); });
    }

    function deleteMessage(msgId) {
        if (!activeId || !msgId) return;
        api('/conversations/' + activeId + '/messages/' + msgId, { method: 'DELETE' })
            .then(function (updated) {
                applyMessageUpdate(activeId, updated);
                scheduleMediaGalleryRefresh();
            }).catch(function (err) { showToast(err.message || 'Não foi possível apagar.'); });
    }

    function applyMessageUpdate(convId, msg) {
        var conv = getConv(convId);
        if (!conv || !msg) return;
        var idx = (conv.messages || []).findIndex(function (m) { return messageId(m) === messageId(msg); });
        if (idx >= 0) {
            conv.messages[idx] = Object.assign({}, conv.messages[idx], msg);
            updateConvPreview(convId, conv.messages[idx]);
        }
        if (activeId === convId) renderMessages(conv);
        renderConvList();
    }

    function updateSendState() {
        if (!chatSendBtn || !chatInput) return;
        chatSendBtn.disabled = !chatInput.value.trim() || !activeId;
    }

    function setTab(tab) {
        activeTab = tab;
        app.querySelectorAll('.chat-tab').forEach(function (btn) {
            var on = btn.dataset.chatTab === tab;
            btn.classList.toggle('chat-tab--active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        renderConvList();
    }

    function messageId(msg) {
        return msg && msg.id != null && msg.id !== '' ? String(msg.id) : '';
    }

    function upsertMessage(convId, msg, opts) {
        opts = opts || {};
        var conv = getConv(convId);
        if (!conv || !msg) return false;
        if (!conv.messages) conv.messages = [];
        var id = messageId(msg);
        if (id) {
            var idx = conv.messages.findIndex(function (m) { return messageId(m) === id; });
            if (idx >= 0) {
                conv.messages[idx] = Object.assign({}, conv.messages[idx], msg);
                if (opts.render !== false && activeId === convId) renderMessages(conv);
                if (activeId === convId) scheduleMediaGalleryRefresh();
                return false;
            }
        }
        conv.messages.push(msg);
        if (opts.render !== false && activeId === convId) renderMessages(conv);
        if (activeId === convId) scheduleMediaGalleryRefresh();
        return true;
    }

    function appendMessage(convId, msg) {
        return upsertMessage(convId, msg);
    }

    function upsertConversation(conv) {
        var existing = getConv(conv.id);
        if (existing) {
            conv.messages = existing.messages || conv.messages || [];
            Object.keys(conv).forEach(function (k) { existing[k] = conv[k]; });
        } else {
            conv.messages = conv.messages || [];
            conversations.unshift(conv);
        }
    }

    function updateConvPreview(convId, msg) {
        var conv = getConv(convId);
        if (!conv || !msg) return;
        if (msg.role === 'user') {
            conv.preview = 'Você: ' + (msg.deleted ? 'Mensagem apagada' : (msg.type === 'voice' ? 'Mensagem de voz' : (msg.type === 'image' ? 'Imagem' : (msg.type === 'file' ? (msg.file_name || 'Arquivo') : (msg.text || '')))));
        } else if (msg.deleted) {
            conv.preview = 'Mensagem apagada';
        } else if (msg.type === 'voice') {
            conv.preview = (msg.sender ? msg.sender.split(' ')[0] + ': ' : '') + 'Mensagem de voz';
        } else if (msg.type === 'image') {
            conv.preview = (msg.sender ? msg.sender.split(' ')[0] + ': ' : (msg.role === 'user' ? 'Você: ' : '')) + 'Imagem';
        } else if (msg.type === 'file') {
            conv.preview = (msg.sender ? msg.sender.split(' ')[0] + ': ' : (msg.role === 'user' ? 'Você: ' : '')) + (msg.file_name || 'Arquivo');
        } else {
            conv.preview = msg.sender ? msg.sender.split(' ')[0] + ': ' + (msg.text || '') : (msg.text || '');
        }
        conv.time_label = 'Agora';
    }

    function handleMercureEvent(data) {
        if (!data || !data.type) return;
        var convId = data.conversation_id ? String(data.conversation_id) : null;

        if (data.type === 'message' && data.message && convId) {
            var added = appendMessage(convId, data.message);
            if (added) updateConvPreview(convId, data.message);
            if (activeId === convId) {
                api('/conversations/' + convId + '/read', { method: 'POST' }).catch(function () {});
            }
            renderConvList();
            updateDocTitle();
            return;
        }

        if (data.type === 'message_deleted' && data.message && convId) {
            applyMessageUpdate(convId, data.message);
            if (data.conversation) upsertConversation(data.conversation);
            updateDocTitle();
            return;
        }

        if (data.type === 'typing' && convId && data.user_id !== USER_ID) {
            if (activeId === convId) showTypingStatus(data.user_name || 'Alguém');
            return;
        }

        if (data.type === 'conversation_updated') {
            if (data.conversation) upsertConversation(data.conversation);
            if (data.system_message && convId) appendMessage(convId, data.system_message);
            if (activeId === convId) {
                var c = getConv(activeId);
                if (c && chatRoomName) chatRoomName.textContent = c.name;
                clearTypingStatus();
            }
            renderConvList();
            return;
        }

        if (data.type === 'conversation_left' && convId) {
            conversations = conversations.filter(function (c) { return c.id !== convId; });
            if (activeId === convId) {
                activeId = null;
                if (chatRoom) chatRoom.hidden = true;
                if (chatEmpty) chatEmpty.hidden = false;
                app.classList.remove('is-room-open');
            }
            renderConvList();
            updateDocTitle();
            reconnectMercure();
            return;
        }

        if (data.type === 'conversation_activity') {
            if (data.conversation) upsertConversation(data.conversation);
            if (data.message && convId) {
                if (activeId !== convId) {
                    var conv = getConv(convId);
                    if (conv) conv.unread = (conv.unread || 0) + 1;
                } else {
                    var wasNew = appendMessage(convId, data.message);
                    if (wasNew) {
                        updateConvPreview(convId, data.message);
                        api('/conversations/' + convId + '/read', { method: 'POST' }).catch(function () {});
                    }
                }
            }
            renderConvList();
            updateDocTitle();
            return;
        }

        if (data.type === 'call_signal' && data.signal) {
            if (data.signal.from_user_id === USER_ID) return;
            if (data.signal.to_user_id && data.signal.to_user_id !== USER_ID) return;
            handleSignal(data.signal, convId);
            return;
        }

        if (data.type === 'conversation_created' && data.conversation) {
            upsertConversation(data.conversation);
            renderConvList();
            if (data.resubscribe) reconnectMercure();
        }
    }

    function disconnectMercure() {
        if (mercureSource) {
            mercureSource.close();
            mercureSource = null;
        }
        mercureConnected = false;
    }

    function connectMercure() {
        if (!MERCURE_ENABLED || !mercureUrl || typeof EventSource === 'undefined') {
            usePollingFallback = true;
            return;
        }
        disconnectMercure();
        try {
            mercureSource = new EventSource(mercureUrl, { withCredentials: true });
            mercureSource.onopen = function () {
                mercureConnected = true;
                usePollingFallback = false;
                stopPolling();
                stopIncomingCallWatch();
                stopCallPolling();
            };
            mercureSource.onmessage = function (event) {
                try { handleMercureEvent(JSON.parse(event.data)); } catch (e) {}
            };
            mercureSource.onerror = function () {
                if (mercureConnected) disconnectMercure();
                enablePollingFallback();
            };
        } catch (e) {
            enablePollingFallback();
        }
    }

    function reconnectMercure() {
        return api('/mercure/subscribe').then(function (data) {
            if (data.hub_url) {
                mercureUrl = data.hub_url;
                app.dataset.mercureUrl = data.hub_url;
            }
            connectMercure();
        }).catch(function () {
            enablePollingFallback();
        });
    }

    function enablePollingFallback() {
        usePollingFallback = true;
        mercureConnected = false;
        startIncomingCallWatch();
        if (activeId) startPolling();
        if (inCall) startCallPolling();
    }

    function ensureMessageRealtime() {
        if (mercureConnected && !usePollingFallback) {
            stopPolling();
            return;
        }
        startPolling();
    }

    function ensureCallRealtime() {
        if (mercureConnected && !usePollingFallback) return;
        startCallPolling();
    }

    function startPolling() {
        stopPolling();
        lastMessagePoll = new Date().toISOString();
        pollTimer = setInterval(function () {
            if (!activeId || (mercureConnected && !usePollingFallback)) return;
            var since = lastMessagePoll;
            api('/conversations/' + activeId + '/messages?since=' + encodeURIComponent(since))
                .then(function (data) {
                    var msgs = data.messages || [];
                    if (!msgs.length) return;
                    var conv = getConv(activeId);
                    if (!conv) return;
                    var changed = false;
                    msgs.forEach(function (m) {
                        if (upsertMessage(activeId, m, { render: false })) changed = true;
                        if (m.at) lastMessagePoll = m.at;
                    });
                    if (changed) {
                        renderMessages(conv);
                        refreshConversations();
                    }
                }).catch(function () {});
        }, 3000);
    }

    function stopPolling() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = null;
    }

    /* ── Mensagem de voz (gravar) ── */
    function toggleVoiceRecord() {
        if (!chatVoiceRecordBtn || !activeId) return;
        if (mediaRecorder && mediaRecorder.state === 'recording') {
            mediaRecorder.stop();
            chatVoiceRecordBtn.classList.remove('is-recording');
            return;
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showToast('Microfone não disponível neste navegador.');
            return;
        }
        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            recordChunks = [];
            recordStartedAt = Date.now();
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = function (e) { if (e.data.size) recordChunks.push(e.data); };
            mediaRecorder.onstop = function () {
                stream.getTracks().forEach(function (t) { t.stop(); });
                var blob = new Blob(recordChunks, { type: mediaRecorder.mimeType || 'audio/webm' });
                uploadVoice(blob, Date.now() - recordStartedAt);
            };
            mediaRecorder.start();
            chatVoiceRecordBtn.classList.add('is-recording');
            showToast('Gravando… clique novamente para enviar.');
        }).catch(function () { showToast('Permita o acesso ao microfone.'); });
    }

    function uploadVoice(blob, durationMs) {
        if (!activeId) return;
        var fd = new FormData();
        fd.append('audio', blob, 'voice.webm');
        fd.append('duration_ms', String(durationMs));
        fetch(API + '/conversations/' + activeId + '/voice', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) {
                return r.json().catch(function () { return {}; }).then(function (body) {
                    if (!r.ok) throw new Error(body.error || 'upload');
                    return body;
                });
            })
            .then(function (msg) {
                var conv = getConv(activeId);
                if (!conv) return;
                upsertMessage(activeId, msg);
                if (msg.at) lastMessagePoll = msg.at;
                conv.preview = 'Você: Mensagem de voz';
                renderConvList();
            }).catch(function () { showToast('Falha ao enviar áudio.'); });
    }

    chatMessages && chatMessages.addEventListener('click', function (e) {
        var btn = e.target.closest('.chat-voice-play');
        if (!btn) return;
        e.preventDefault();
        var url = btn.dataset.voiceUrl;
        if (!url) return;
        if (activeAudio && activeAudioBtn === btn) {
            activeAudio.pause();
            activeAudio = null;
            activeAudioBtn = null;
            btn.innerHTML = '<i class="fas fa-play"></i>';
            return;
        }
        if (activeAudio) {
            activeAudio.pause();
            if (activeAudioBtn) activeAudioBtn.innerHTML = '<i class="fas fa-play"></i>';
        }
        activeAudio = new Audio(url);
        activeAudioBtn = btn;
        btn.innerHTML = '<i class="fas fa-pause"></i>';
        activeAudio.play();
        activeAudio.onended = function () {
            btn.innerHTML = '<i class="fas fa-play"></i>';
            activeAudio = null;
            activeAudioBtn = null;
        };
    });

    function openMsgMenu(msgId, x, y) {
        if (!chatMsgMenu || !activeId) return;
        var conv = getConv(activeId);
        if (!conv) return;
        var msg = (conv.messages || []).find(function (m) { return m.id === msgId; });
        if (!msg || msg.role === 'system') return;
        msgMenuTarget = msg;
        chatMsgMenu.querySelector('[data-msg-action="delete"]').hidden = msg.role !== 'user' || !!msg.deleted;
        chatMsgMenu.querySelector('[data-msg-action="reply"]').hidden = !!msg.deleted;
        chatMsgMenu.querySelector('[data-msg-action="copy"]').hidden = msg.deleted || msg.type === 'voice' || msg.type === 'image' || msg.type === 'file';
        chatMsgMenu.hidden = false;
        chatMsgMenu.style.left = Math.min(x, window.innerWidth - 180) + 'px';
        chatMsgMenu.style.top = Math.min(y, window.innerHeight - 120) + 'px';
    }

    function closeMsgMenu() {
        if (chatMsgMenu) chatMsgMenu.hidden = true;
        msgMenuTarget = null;
    }

    chatMessages && chatMessages.addEventListener('contextmenu', function (e) {
        var bubble = e.target.closest('.chat-msg-bubble');
        if (!bubble) return;
        e.preventDefault();
        openMsgMenu(bubble.dataset.msgId, e.clientX, e.clientY);
    });

    document.addEventListener('click', function (e) {
        if (chatMsgMenu && !chatMsgMenu.hidden && !e.target.closest('#chatMsgMenu')) closeMsgMenu();
    });

    chatMsgMenu && chatMsgMenu.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-msg-action]');
        if (!btn || !msgMenuTarget) return;
        var action = btn.dataset.msgAction;
        if (action === 'reply') setReply(msgMenuTarget);
        if (action === 'copy' && msgMenuTarget.text) {
            navigator.clipboard.writeText(msgMenuTarget.text).then(function () { showToast('Copiado.'); }).catch(function () {});
        }
        if (action === 'delete') deleteMessage(msgMenuTarget.id);
        closeMsgMenu();
    });

    /* ── Chamada de voz WebRTC ── */
    function showCallUI(name, initials, status, showAccept) {
        if (chatCallOverlay) chatCallOverlay.hidden = false;
        if (chatCallAvatar) chatCallAvatar.textContent = initials || '?';
        if (chatCallName) chatCallName.textContent = name || '—';
        if (chatCallStatus) chatCallStatus.textContent = status;
        if (chatCallAcceptBtn) chatCallAcceptBtn.hidden = !showAccept;
    }

    function hideCallUI() {
        if (chatCallOverlay) chatCallOverlay.hidden = true;
    }

    function endCall(sendSignal) {
        inCall = false;
        isCaller = false;
        stopCallPolling();
        if (!mercureConnected || usePollingFallback) startIncomingCallWatch();
        if (sendSignal && activeId) {
            api('/conversations/' + activeId + '/call/signal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'hangup', payload: '{}' }),
            }).catch(function () {});
        }
        if (peerConnection) { peerConnection.close(); peerConnection = null; }
        if (localStream) { localStream.getTracks().forEach(function (t) { t.stop(); }); localStream = null; }
        if (remoteAudio) { remoteAudio.remove(); remoteAudio = null; }
        hideCallUI();
    }

    function getLocalStream() {
        return navigator.mediaDevices.getUserMedia({ audio: true, video: false }).then(function (stream) {
            localStream = stream;
            return stream;
        });
    }

    function createPeer() {
        peerConnection = new RTCPeerConnection(ICE_SERVERS);
        if (localStream) {
            localStream.getTracks().forEach(function (t) { peerConnection.addTrack(t, localStream); });
        }
        peerConnection.ontrack = function (ev) {
            if (!remoteAudio) {
                remoteAudio = document.createElement('audio');
                remoteAudio.autoplay = true;
                document.body.appendChild(remoteAudio);
            }
            remoteAudio.srcObject = ev.streams[0];
        };
        peerConnection.onicecandidate = function (ev) {
            if (ev.candidate && activeId) {
                api('/conversations/' + activeId + '/call/signal', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'ice', payload: JSON.stringify(ev.candidate) }),
                }).catch(function () {});
            }
        };
        return peerConnection;
    }

    function startCall() {
        var conv = getConv(activeId);
        if (!conv || conv.type !== 'direct') return;
        stopIncomingCallWatch();
        isCaller = true;
        inCall = true;
        showCallUI(conv.name, conv.initials, 'Chamando…', false);
        getLocalStream().then(function () {
            createPeer();
            return peerConnection.createOffer();
        }).then(function (offer) {
            return peerConnection.setLocalDescription(offer);
        }).then(function () {
            return api('/conversations/' + activeId + '/call/signal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'offer', payload: JSON.stringify(peerConnection.localDescription), to_user_id: conv.peer_user_id }),
            });
        }).then(function () {
            ensureCallRealtime();
        }).catch(function () {
            showToast('Não foi possível iniciar a chamada.');
            endCall(false);
        });
    }

    function acceptCall(offerPayload) {
        var conv = getConv(activeId);
        if (!conv) return;
        stopIncomingCallWatch();
        inCall = true;
        isCaller = false;
        showCallUI(conv.name, conv.initials, 'Em chamada…', false);
        getLocalStream().then(function () {
            createPeer();
            var desc = JSON.parse(offerPayload);
            return peerConnection.setRemoteDescription(desc);
        }).then(function () {
            return peerConnection.createAnswer();
        }).then(function (answer) {
            return peerConnection.setLocalDescription(answer);
        }).then(function () {
            return api('/conversations/' + activeId + '/call/signal', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'answer', payload: JSON.stringify(peerConnection.localDescription) }),
            });
        }).then(function () {
            startCallPolling();
        }).catch(function () {
            showToast('Falha ao atender chamada.');
            endCall(true);
        });
    }

    function handleSignal(signal, convId) {
        if (!signal) return;
        if (convId) activeId = String(convId);
        if (!activeId) return;
        if (signal.type === 'hangup') {
            endCall(false);
            return;
        }
        if (signal.type === 'offer' && !inCall) {
            var offerConv = getConv(activeId);
            showCallUI(offerConv ? offerConv.name : '', offerConv ? offerConv.initials : '', 'Chamada recebida…', true);
            chatCallAcceptBtn._pendingOffer = signal.payload;
            ensureCallRealtime();
            return;
        }
        if (signal.type === 'answer' && peerConnection && isCaller) {
            peerConnection.setRemoteDescription(JSON.parse(signal.payload)).then(function () {
                if (chatCallStatus) chatCallStatus.textContent = 'Em chamada…';
            });
            return;
        }
        if (signal.type === 'ice' && peerConnection) {
            try { peerConnection.addIceCandidate(JSON.parse(signal.payload)); } catch (e) {}
        }
    }

    function startCallPolling() {
        stopCallPolling();
        lastCallPoll = new Date().toISOString();
        callPollTimer = setInterval(function () {
            if (!activeId) return;
            api('/conversations/' + activeId + '/call/poll?since=' + encodeURIComponent(lastCallPoll))
                .then(function (data) {
                    (data.signals || []).forEach(function (s) {
                        lastCallPoll = s.at;
                        handleSignal(s);
                    });
                }).catch(function () {});
        }, 1500);
    }

    function stopCallPolling() {
        if (callPollTimer) clearInterval(callPollTimer);
        callPollTimer = null;
    }

    function startIncomingCallWatch() {
        if (incomingCallTimer) return;
        incomingCallSince = new Date().toISOString();
        incomingCallTimer = setInterval(function () {
            if (inCall) return;
            conversations.filter(function (c) { return c.type === 'direct'; }).forEach(function (c) {
                api('/conversations/' + c.id + '/call/poll?since=' + encodeURIComponent(incomingCallSince))
                    .then(function (data) {
                        (data.signals || []).forEach(function (s) {
                            incomingCallSince = s.at;
                            if (s.type === 'offer' && !inCall) {
                                handleSignal(s, c.id);
                            }
                        });
                    }).catch(function () {});
            });
        }, 2500);
    }

    function stopIncomingCallWatch() {
        if (incomingCallTimer) clearInterval(incomingCallTimer);
        incomingCallTimer = null;
    }

    /* ── Painel lateral: nova conversa / grupo / detalhes ── */
    var sidebarPanelMode = 'browse';
    var selectedGroupMembers = {};
    var infoMemberIds = {};
    var infoPanelAddMode = false;
    var mediaGalleryData = null;
    var activeMediaTab = 'all';
    var mediaRefreshTimer = null;
    var MEDIA_PREVIEW_LIMIT = 3;
    var MEDIA_TAB_LABELS = { all: 'Tudo', images: 'Mídia', documents: 'Docs', links: 'Links', audio: 'Áudio' };

    function colleagueKey(id) {
        return String(id);
    }

    function updateActionMenu() {
        var addBtn = app.querySelector('[data-chat-action="adicionar-grupo"]');
        if (!addBtn) return;
        var conv = activeId ? getConv(activeId) : null;
        addBtn.hidden = !(conv && conv.type === 'group');
    }

    function refreshSidebarPanelContent() {
        if (sidebarPanelMode === 'direct') renderColleagues();
        else if (sidebarPanelMode === 'group') renderGroupMembers();
        else if (sidebarPanelMode === 'info-group') renderInfoAddList(chatInfoAddSearch ? chatInfoAddSearch.value : '');
    }

    function showSidebarPanel(mode, title) {
        closeActionMenu();
        sidebarPanelMode = mode;
        if (chatSidebarBrowse) chatSidebarBrowse.hidden = true;
        if (chatSidebarPanel) chatSidebarPanel.hidden = false;
        if (chatSidebarPanelTitle) chatSidebarPanelTitle.textContent = title || '';
        app.classList.add('is-sidebar-panel-open');

        if (chatDirectPanel) chatDirectPanel.hidden = mode !== 'direct';
        if (chatGroupPanel) chatGroupPanel.hidden = mode !== 'group';
        if (chatGroupFooter) chatGroupFooter.hidden = mode !== 'group';
        if (chatInfoLayout) chatInfoLayout.hidden = !(mode === 'info-direct' || mode === 'info-group');
        if (chatInfoGroup) chatInfoGroup.hidden = mode !== 'info-group';
        if (chatInfoFooter) chatInfoFooter.hidden = mode !== 'info-group';
        updateMediaGalleryVisibility(mode);
        updateGroupInfoLayout();
    }

    function closeSidebarPanel() {
        closeMediaGalleryModal();
        if (chatSidebarBrowse) chatSidebarBrowse.hidden = false;
        if (chatSidebarPanel) chatSidebarPanel.hidden = true;
        app.classList.remove('is-sidebar-panel-open');
        sidebarPanelMode = 'browse';
        infoPanelAddMode = false;
        selectedGroupMembers = {};
        infoAddSelected = {};
        infoMemberIds = {};
        updateActionMenu();
    }

    function updateGroupCreateState() {
        if (!chatGroupCreateBtn) return;
        var nameOk = chatGroupName && chatGroupName.value.trim().length > 0;
        var membersOk = Object.keys(selectedGroupMembers).length > 0;
        chatGroupCreateBtn.disabled = !(nameOk && membersOk);
    }

    function updateInfoAddState() {
        if (!chatInfoAddBtn) return;
        chatInfoAddBtn.disabled = Object.keys(infoAddSelected).length === 0;
    }

    function shouldShowMediaGallery(mode) {
        return mode === 'info-direct' || (mode === 'info-group' && !infoPanelAddMode);
    }

    function updateMediaGalleryVisibility(mode) {
        if (chatSidebarPanel) {
            chatSidebarPanel.classList.remove('is-info-direct', 'is-info-group', 'is-info-group-add-mode');
            if (mode === 'info-direct') chatSidebarPanel.classList.add('is-info-direct');
            if (mode === 'info-group') {
                chatSidebarPanel.classList.add('is-info-group');
                if (infoPanelAddMode) chatSidebarPanel.classList.add('is-info-group-add-mode');
            }
        }
    }

    function updateGroupInfoLayout() {
        if (chatInfoAddWrap) chatInfoAddWrap.hidden = !infoPanelAddMode;
        if (chatInfoAddBtn) chatInfoAddBtn.hidden = !infoPanelAddMode;
        if (chatInfoGroup) {
            chatInfoGroup.classList.toggle('is-add-mode', infoPanelAddMode);
        }
        if (chatSidebarPanel && sidebarPanelMode === 'info-group') {
            chatSidebarPanel.classList.toggle('is-info-group-add-mode', infoPanelAddMode);
        }
    }

    function linkHost(url) {
        try {
            return new URL(url).hostname.replace(/^www\./, '');
        } catch (e) {
            return url;
        }
    }

    function truncateText(text, max) {
        text = (text || '').trim();
        if (text.length <= max) return text;
        return text.slice(0, max - 1) + '…';
    }

    function updateMediaTabCounts(data) {
        if (!chatInfoMedia) return;
        var counts = {
            all: (data.all || []).length,
            images: (data.images || []).length,
            documents: (data.documents || []).length,
            links: (data.links || []).length,
            audio: (data.audio || []).length,
        };
        chatInfoMedia.querySelectorAll('[data-media-tab]').forEach(function (btn) {
            var tab = btn.getAttribute('data-media-tab');
            var count = counts[tab] || 0;
            var label = btn.getAttribute('data-media-label') || btn.textContent.replace(/\s*\(\d+\)$/, '').trim();
            if (!btn.getAttribute('data-media-label')) btn.setAttribute('data-media-label', label);
            btn.textContent = count > 0 ? label + ' (' + count + ')' : label;
        });
    }

    function mediaKindLabel(kind) {
        if (kind === 'image') return 'Imagem';
        if (kind === 'video') return 'Vídeo';
        if (kind === 'document') return 'Documento';
        if (kind === 'link') return 'Link';
        if (kind === 'audio') return 'Áudio';
        return 'Arquivo';
    }

    function mediaKindIcon(kind) {
        if (kind === 'image') return 'fa-image';
        if (kind === 'video') return 'fa-video';
        if (kind === 'document') return 'fa-file-alt';
        if (kind === 'link') return 'fa-link';
        if (kind === 'audio') return 'fa-microphone';
        return 'fa-paperclip';
    }

    function mediaItemTitle(item) {
        if (item.kind === 'link') return linkHost(item.url);
        if (item.kind === 'audio') return 'Mensagem de voz · ' + formatVoiceDuration(item.duration_ms);
        return item.name || mediaKindLabel(item.kind);
    }

    function mediaItemSub(item) {
        var parts = [formatTime(item.at)];
        if (item.sender) parts.push(item.sender);
        return parts.join(' · ');
    }

    function mediaItemKind(item, tab) {
        if (item.kind) return item.kind;
        if (tab === 'images') return item.is_video ? 'video' : 'image';
        if (tab === 'documents') return 'document';
        if (tab === 'links') return 'link';
        if (tab === 'audio') return 'audio';
        return 'document';
    }

    function renderMediaPreviewItem(item, tab) {
        var kind = mediaItemKind(item, tab);
        var title = escapeHtml(mediaItemTitle(item));
        var inner = '';

        if ((kind === 'image' || kind === 'video') && item.url) {
            if (kind === 'video' || item.is_video) {
                inner = '<video src="' + escapeHtml(item.url) + '" muted preload="metadata"></video>' +
                    '<span class="chat-media-preview-play" aria-hidden="true"><i class="fas fa-play"></i></span>';
            } else {
                inner = '<img src="' + escapeHtml(item.url) + '" alt="" loading="lazy">';
            }
        } else {
            inner = '<i class="fas ' + mediaKindIcon(kind) + '" aria-hidden="true"></i>';
        }

        return '<button type="button" class="chat-media-preview-item chat-media-preview-item--' + kind + '" ' +
            'title="' + title + '" ' +
            'data-msg-id="' + escapeHtml(item.message_id) + '" ' +
            'data-kind="' + escapeHtml(kind) + '" ' +
            'data-url="' + escapeHtml(item.url || '') + '" ' +
            (kind === 'image' || kind === 'video' ? 'data-open-lightbox="1" data-lightbox-kind="' + (kind === 'video' ? 'video' : 'image') + '" data-lightbox-url="' + escapeHtml(item.url || '') + '" ' : '') +
            '>' + inner + '</button>';
    }

    function getMediaTabItems(tab) {
        var data = mediaGalleryData || { all: [], images: [], documents: [], links: [], audio: [] };
        return tab === 'all' ? (data.all || []) : (data[tab] || []);
    }

    function renderMediaPreviewGrid(tab, items) {
        if (!items.length) {
            var emptyMsg = {
                all: 'Nenhum conteúdo compartilhado nesta conversa.',
                images: 'Nenhuma foto ou vídeo nesta conversa.',
                documents: 'Nenhum documento nesta conversa.',
                links: 'Nenhum link nesta conversa.',
                audio: 'Nenhuma mensagem de voz nesta conversa.',
            };
            return '<p class="chat-media-empty">' + (emptyMsg[tab] || emptyMsg.all) + '</p>';
        }

        var visible = items.slice(0, MEDIA_PREVIEW_LIMIT);
        var remaining = items.length - visible.length;
        var html = '<div class="chat-media-preview-grid">';

        visible.forEach(function (item) {
            html += renderMediaPreviewItem(item, tab);
        });

        if (remaining > 0) {
            html += '<button type="button" class="chat-media-more-btn" data-media-show-more="' + escapeHtml(tab) + '" title="Ver galeria completa">' +
                '<span class="chat-media-more-icon" aria-hidden="true"><i class="fas fa-plus"></i></span>' +
                '<span class="chat-media-more-count">+' + remaining + '</span></button>';
        }

        html += '</div>';
        return html;
    }

    function openMediaGalleryModal(tab) {
        if (!chatMediaGalleryModal || !chatMediaGalleryModalBody) return;
        var items = getMediaTabItems(tab);
        if (!items.length) return;
        if (chatMediaGalleryTitle) {
            chatMediaGalleryTitle.textContent = (MEDIA_TAB_LABELS[tab] || 'Galeria') + ' · ' + items.length;
        }
        var html = '<div class="chat-media-gallery-modal-grid">';
        items.forEach(function (item) {
            html += renderMediaPreviewItem(item, tab);
        });
        html += '</div>';
        chatMediaGalleryModalBody.innerHTML = html;
        chatMediaGalleryModal.hidden = false;
        document.body.classList.add('chat-media-gallery-open');
    }

    function closeMediaGalleryModal() {
        if (!chatMediaGalleryModal) return;
        chatMediaGalleryModal.hidden = true;
        if (chatMediaGalleryModalBody) chatMediaGalleryModalBody.innerHTML = '';
        document.body.classList.remove('chat-media-gallery-open');
    }

    function handleMediaPreviewClick(previewItem) {
        if (!previewItem) return;
        var kind = previewItem.getAttribute('data-kind');
        var url = previewItem.getAttribute('data-url');
        if ((kind === 'image' || kind === 'video') && url) {
            openMediaLightbox(url, kind === 'video' ? 'video' : 'image');
        } else if (kind === 'audio' && url) {
            if (activeAudio && activeAudioBtn === previewItem) {
                activeAudio.pause();
                activeAudio = null;
                activeAudioBtn = null;
                return;
            }
            if (activeAudio) {
                activeAudio.pause();
                activeAudioBtn = null;
            }
            activeAudio = new Audio(url);
            activeAudioBtn = previewItem;
            activeAudio.play().catch(function () {
                activeAudio = null;
                activeAudioBtn = null;
            });
            activeAudio.onended = function () {
                activeAudio = null;
                activeAudioBtn = null;
            };
        } else if (kind === 'link' && url) {
            window.open(url, '_blank', 'noopener');
        } else if (kind === 'document' && url) {
            window.open(url, '_blank', 'noopener');
        }
        scrollToMessage(previewItem.getAttribute('data-msg-id'));
    }

    function renderMediaTab(tab) {
        if (!chatMediaBody) return;
        activeMediaTab = tab || activeMediaTab;
        var data = mediaGalleryData || { all: [], images: [], documents: [], links: [], audio: [] };
        var items = tab === 'all' ? (data.all || []) : (data[tab] || []);

        chatMediaBody.innerHTML = renderMediaPreviewGrid(tab, items);

        if (chatInfoMedia) {
            chatInfoMedia.querySelectorAll('[data-media-tab]').forEach(function (btn) {
                var isActive = btn.getAttribute('data-media-tab') === activeMediaTab;
                btn.classList.toggle('chat-media-tab--active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        }
    }
    function pickDefaultMediaTab(data) {
        if ((data.all || []).length) return 'all';
        if ((data.images || []).length) return 'images';
        if ((data.documents || []).length) return 'documents';
        if ((data.links || []).length) return 'links';
        if ((data.audio || []).length) return 'audio';
        return 'all';
    }

    function refreshMediaGalleryIfOpen() {
        if (!activeId || !shouldShowMediaGallery(sidebarPanelMode)) return;
        loadMediaGallery(activeId, true);
    }

    function scheduleMediaGalleryRefresh() {
        if (!activeId || !shouldShowMediaGallery(sidebarPanelMode)) return;
        clearTimeout(mediaRefreshTimer);
        mediaRefreshTimer = setTimeout(refreshMediaGalleryIfOpen, 350);
    }

    function scrollToMessage(msgId) {
        if (!msgId || !chatMessages) return;
        var el = chatMessages.querySelector('.chat-msg[data-msg-id="' + msgId + '"]');
        if (!el) {
            showToast('Mensagem não visível. Carregue mensagens anteriores.');
            return;
        }
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('chat-msg--highlight');
        setTimeout(function () { el.classList.remove('chat-msg--highlight'); }, 2200);
    }

    function openMediaLightbox(url, kind) {
        if (!chatMediaLightbox || !chatMediaLightboxBody || !url) return;
        if (kind === 'video') {
            chatMediaLightboxBody.innerHTML = '<video src="' + escapeHtml(url) + '" controls autoplay></video>';
        } else {
            chatMediaLightboxBody.innerHTML = '<img src="' + escapeHtml(url) + '" alt="Visualização">';
        }
        chatMediaLightbox.hidden = false;
    }

    function closeMediaLightbox() {
        if (!chatMediaLightbox || !chatMediaLightboxBody) return;
        chatMediaLightbox.hidden = true;
        chatMediaLightboxBody.innerHTML = '';
    }

    function loadMediaGallery(convId, keepTab) {
        if (!chatMediaBody || !shouldShowMediaGallery(sidebarPanelMode)) return;
        chatMediaBody.innerHTML = '<p class="chat-media-empty">Carregando…</p>';
        api('/conversations/' + String(convId) + '/media').then(function (data) {
            mediaGalleryData = data;
            if (!keepTab) activeMediaTab = pickDefaultMediaTab(data);
            updateMediaTabCounts(data);
            renderMediaTab(activeMediaTab);
        }).catch(function (err) {
            var msg = (err && err.message) ? err.message : 'Não foi possível carregar a galeria.';
            chatMediaBody.innerHTML =
                '<p class="chat-media-empty">' + escapeHtml(msg) + '</p>' +
                '<button type="button" class="chat-media-retry btn-unio btn-sm">Tentar novamente</button>';
            var retryBtn = chatMediaBody.querySelector('.chat-media-retry');
            if (retryBtn) {
                retryBtn.addEventListener('click', function () {
                    loadMediaGallery(convId, keepTab);
                });
            }
        });
    }

    function startDirectChat(colleague, btn) {
        if (!colleague || !colleague.id) return;
        if (btn) btn.disabled = true;
        api('/conversations/direct', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: parseInt(colleague.id, 10) }),
        }).then(function (conv) {
            var exists = getConv(conv.id);
            if (!exists) {
                conv.messages = [];
                conversations.unshift(conv);
            } else {
                conv = exists;
            }
            renderConvList();
            closeSidebarPanel();
            reconnectMercure().finally(function () {
                openConversation(conv.id);
            });
        }).catch(function (err) {
            showToast(err.message || 'Não foi possível criar a conversa.');
            if (btn) btn.disabled = false;
        });
    }

    function renderColleagues() {
        if (!chatColleagueList) return;
        var q = (chatColleagueSearch ? chatColleagueSearch.value : '').trim().toLowerCase();
        chatColleagueList.innerHTML = '';
        var filtered = colleagues.filter(function (c) {
            return !q || (c.name || '').toLowerCase().indexOf(q) !== -1;
        });
        if (!filtered.length) {
            chatColleagueList.innerHTML = '<p class="chat-conv-empty">Nenhum colega disponível nesta empresa.</p>';
            return;
        }
        filtered.forEach(function (c) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-colleague-item';
            btn.innerHTML = '<span class="chat-conv-avatar">' + escapeHtml(c.initials) + '</span><span>' + escapeHtml(c.name) + '</span>';
            btn.addEventListener('click', function () { startDirectChat(c, btn); });
            chatColleagueList.appendChild(btn);
        });
    }

    function renderGroupMembers() {
        if (!chatGroupMemberList) return;
        var q = (chatGroupMemberSearch ? chatGroupMemberSearch.value : '').trim().toLowerCase();
        chatGroupMemberList.innerHTML = '';
        var filtered = colleagues.filter(function (c) {
            return !q || (c.name || '').toLowerCase().indexOf(q) !== -1;
        });
        if (!filtered.length) {
            chatGroupMemberList.innerHTML = '<p class="chat-conv-empty">Nenhum colega disponível para adicionar.</p>';
            return;
        }
        filtered.forEach(function (c) {
            var key = colleagueKey(c.id);
            var selected = !!selectedGroupMembers[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-colleague-item' + (selected ? ' chat-colleague-item--selected' : '');
            btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
            btn.innerHTML =
                '<span class="chat-colleague-check" aria-hidden="true"><i class="fas fa-check"></i></span>' +
                '<span class="chat-conv-avatar">' + escapeHtml(c.initials) + '</span>' +
                '<span>' + escapeHtml(c.name) + '</span>';
            btn.addEventListener('click', function () {
                if (selectedGroupMembers[key]) delete selectedGroupMembers[key];
                else selectedGroupMembers[key] = c;
                renderGroupMembers();
                updateGroupCreateState();
            });
            chatGroupMemberList.appendChild(btn);
        });
    }

    function createGroup() {
        if (!chatGroupName || !chatGroupCreateBtn) return;
        var name = chatGroupName.value.trim();
        var memberIds = Object.keys(selectedGroupMembers).map(function (id) { return parseInt(id, 10); });
        if (!name || !memberIds.length) return;

        chatGroupCreateBtn.disabled = true;
        api('/conversations/group', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name, member_ids: memberIds }),
        }).then(function (conv) {
            conv.messages = [];
            conversations.unshift(conv);
            renderConvList();
            closeSidebarPanel();
            reconnectMercure().finally(function () {
                loadMessages(conv.id, true).finally(function () {
                    openConversation(conv.id);
                });
            });
        }).catch(function (err) {
            showToast(err.message || 'Não foi possível criar o grupo.');
            updateGroupCreateState();
        });
    }

    function openDirectPanel() {
        showSidebarPanel('direct', 'Nova conversa');
        if (chatColleagueSearch) chatColleagueSearch.value = '';
        refreshConversations().then(function () {
            renderColleagues();
            if (chatColleagueSearch) chatColleagueSearch.focus();
        });
    }

    function openGroupPanel() {
        selectedGroupMembers = {};
        if (chatGroupName) chatGroupName.value = '';
        if (chatGroupMemberSearch) chatGroupMemberSearch.value = '';
        showSidebarPanel('group', 'Criar grupo');
        refreshConversations().then(function () {
            renderGroupMembers();
            updateGroupCreateState();
            if (chatGroupName) chatGroupName.focus();
        });
    }

    function populateInfoPanel(detail, options) {
        options = options || {};
        infoPanelAddMode = !!options.addMode;
        infoAddSelected = {};
        infoMemberIds = {};
        if (chatInfoAddSearch) chatInfoAddSearch.value = '';
        updateInfoAddState();

        (detail.members || []).forEach(function (m) {
            infoMemberIds[colleagueKey(m.id)] = true;
        });

        if (detail.type === 'group') {
            var memberCount = (detail.members || []).length;
            var panelTitle = options.addMode ? 'Adicionar colegas' : ((detail.name || 'Grupo') + ' · ' + memberCount);
            if (!options.keepMode) showSidebarPanel('info-group', panelTitle);
            else updateMediaGalleryVisibility('info-group');
            if (chatInfoGroupName) chatInfoGroupName.value = detail.name || '';
            if (chatInfoMembers) {
                chatInfoMembers.innerHTML = (detail.members || []).map(function (m) {
                    return '<div class="chat-info-member"><span class="chat-conv-avatar">' + escapeHtml(m.initials) +
                        '</span><span>' + escapeHtml(m.name) + (m.is_self ? ' <em>(você)</em>' : '') + '</span></div>';
                }).join('');
            }
            renderInfoAddList('');
            if (options.focusAdd && chatInfoAddSearch) chatInfoAddSearch.focus();
            updateGroupInfoLayout();
        } else {
            if (!options.keepMode) showSidebarPanel('info-direct', detail.name || 'Detalhes');
            else updateMediaGalleryVisibility('info-direct');
        }

        if (activeId && shouldShowMediaGallery(sidebarPanelMode)) {
            loadMediaGallery(activeId, !!options.keepTab);
        }
        if (detail.type === 'group') updateGroupInfoLayout();
    }

    function openInfoPanel(options) {
        if (!activeId) return;
        options = options || {};
        refreshConversations().then(function () {
            return api('/conversations/' + activeId);
        }).then(function (detail) {
            populateInfoPanel(detail, options);
        }).catch(function (err) { showToast(err.message || 'Não foi possível carregar detalhes.'); });
    }

    function openAddMembersPanel() {
        if (!activeId) return;
        var conv = getConv(activeId);
        if (!conv || conv.type !== 'group') {
            showToast('Selecione um grupo para adicionar participantes.');
            return;
        }
        openInfoPanel({ addMode: true, focusAdd: true, keepTab: true });
    }

    function renderInfoAddList(q) {
        if (!chatInfoAddList) return;
        q = (q || '').trim().toLowerCase();
        chatInfoAddList.innerHTML = '';
        var filtered = colleagues.filter(function (c) {
            if (infoMemberIds[colleagueKey(c.id)]) return false;
            return !q || (c.name || '').toLowerCase().indexOf(q) !== -1;
        });
        if (!filtered.length) {
            chatInfoAddList.innerHTML = '<p class="chat-conv-empty">Nenhum colega disponível para adicionar.</p>';
            return;
        }
        filtered.forEach(function (c) {
            var key = colleagueKey(c.id);
            var selected = !!infoAddSelected[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'chat-colleague-item' + (selected ? ' chat-colleague-item--selected' : '');
            btn.setAttribute('aria-pressed', selected ? 'true' : 'false');
            btn.innerHTML =
                '<span class="chat-colleague-check" aria-hidden="true"><i class="fas fa-check"></i></span>' +
                '<span class="chat-conv-avatar">' + escapeHtml(c.initials) + '</span>' +
                '<span>' + escapeHtml(c.name) + '</span>';
            btn.addEventListener('click', function () {
                if (infoAddSelected[key]) delete infoAddSelected[key];
                else infoAddSelected[key] = c;
                renderInfoAddList(q);
                updateInfoAddState();
            });
            chatInfoAddList.appendChild(btn);
        });
    }

    function refreshInfoPanel() {
        if (!activeId || sidebarPanelMode !== 'info-group') return;
        api('/conversations/' + activeId).then(function (detail) {
            populateInfoPanel(detail, { keepMode: true });
        }).catch(function () {});
    }

    function renameGroupFromInfo() {
        if (!activeId || !chatInfoGroupName) return;
        var name = chatInfoGroupName.value.trim();
        api('/conversations/' + activeId, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: name }),
        }).then(function (conv) {
            upsertConversation(conv);
            if (chatRoomName) chatRoomName.textContent = conv.name;
            if (chatSidebarPanelTitle && sidebarPanelMode === 'info-group') {
                chatSidebarPanelTitle.textContent = (conv.name || 'Grupo') + ' · ' + (conv.member_count || 0);
            }
            renderConvList();
            showToast('Grupo renomeado.');
        }).catch(function (err) { showToast(err.message || 'Não foi possível renomear.'); });
    }

    function addMembersFromInfo() {
        if (!activeId || !chatInfoAddBtn) return;
        var ids = Object.keys(infoAddSelected).map(function (id) { return parseInt(id, 10); });
        if (!ids.length) return;
        chatInfoAddBtn.disabled = true;
        api('/conversations/' + activeId + '/members', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ member_ids: ids }),
        }).then(function (conv) {
            upsertConversation(conv);
            renderConvList();
            if (chatRoomStatus) chatRoomStatus.textContent = 'Grupo · ' + (conv.member_count || 0) + ' participantes';
            refreshInfoPanel();
            loadMessages(activeId, false);
            showToast('Participantes adicionados.');
        }).catch(function (err) {
            showToast(err.message || 'Não foi possível adicionar.');
            updateInfoAddState();
        });
    }

    function leaveGroupFromInfo() {
        if (!activeId) return;
        if (!window.confirm('Deseja sair deste grupo?')) return;
        api('/conversations/' + activeId + '/leave', { method: 'POST' })
            .then(function () {
                conversations = conversations.filter(function (c) { return c.id !== activeId; });
                closeSidebarPanel();
                activeId = null;
                if (chatRoom) chatRoom.hidden = true;
                if (chatEmpty) chatEmpty.hidden = false;
                app.classList.remove('is-room-open');
                renderConvList();
                updateDocTitle();
                reconnectMercure();
                showToast('Você saiu do grupo.');
            }).catch(function (err) { showToast(err.message || 'Não foi possível sair.'); });
    }

    /* ── Init ── */
    conversations = parseInitial().map(function (c) {
        c.messages = c.messages || [];
        return c;
    });

    conversations.forEach(function (c) {
        if (!c.messages.length) {
            loadMessages(c.id, false);
        }
    });

    renderConvList();
    updateSendState();
    refreshConversations();

    if (MERCURE_ENABLED) {
        connectMercure();
        setTimeout(function () {
            if (!mercureConnected) enablePollingFallback();
        }, 4000);
    } else {
        enablePollingFallback();
    }

    convList && convList.addEventListener('click', function (e) {
        var item = e.target.closest('.chat-conv-item');
        if (item) openConversation(item.dataset.convId);
    });

    chatSearch && chatSearch.addEventListener('input', renderConvList);
    app.querySelectorAll('[data-chat-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () { setTab(btn.dataset.chatTab || 'all'); });
    });
    chatForm && chatForm.addEventListener('submit', function (e) {
        e.preventDefault();
        sendMessage(chatInput ? chatInput.value : '');
    });
    chatInput && chatInput.addEventListener('input', function () {
        chatInput.style.height = 'auto';
        chatInput.style.height = Math.min(chatInput.scrollHeight, 160) + 'px';
        updateSendState();
        clearTimeout(typingPulseTimer);
        typingPulseTimer = setTimeout(pulseTyping, 400);
    });
    chatInput && chatInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });
    chatBackBtn && chatBackBtn.addEventListener('click', function () { app.classList.remove('is-room-open'); });
    chatVoiceRecordBtn && chatVoiceRecordBtn.addEventListener('click', toggleVoiceRecord);
    chatVoiceCallBtn && chatVoiceCallBtn.addEventListener('click', startCall);
    chatCallEndBtn && chatCallEndBtn.addEventListener('click', function () { endCall(true); });
    chatCallAcceptBtn && chatCallAcceptBtn.addEventListener('click', function () {
        if (chatCallAcceptBtn._pendingOffer) acceptCall(chatCallAcceptBtn._pendingOffer);
    });

    app.addEventListener('click', function (e) {
        var actionEl = e.target.closest('[data-chat-action]');
        if (!actionEl) return;
        var action = actionEl.dataset.chatAction;
        if (action === 'nova-conversa') {
            e.preventDefault();
            openDirectPanel();
        } else if (action === 'criar-grupo') {
            e.preventDefault();
            openGroupPanel();
        } else if (action === 'adicionar-grupo') {
            e.preventDefault();
            openAddMembersPanel();
        }
    });
    chatSidebarBackBtn && chatSidebarBackBtn.addEventListener('click', closeSidebarPanel);
    chatColleagueSearch && chatColleagueSearch.addEventListener('input', renderColleagues);
    chatGroupMemberSearch && chatGroupMemberSearch.addEventListener('input', renderGroupMembers);
    chatGroupName && chatGroupName.addEventListener('input', updateGroupCreateState);
    chatGroupCreateBtn && chatGroupCreateBtn.addEventListener('click', createGroup);
    chatLoadMoreBtn && chatLoadMoreBtn.addEventListener('click', loadOlderMessages);
    chatReplyCancel && chatReplyCancel.addEventListener('click', clearReply);
    chatAttachBtn && chatAttachBtn.addEventListener('click', function () { if (chatFileInput) chatFileInput.click(); });
    chatFileInput && chatFileInput.addEventListener('change', function () {
        if (chatFileInput.files && chatFileInput.files[0]) uploadFile(chatFileInput.files[0]);
        chatFileInput.value = '';
    });
    chatRoomInfoBtn && chatRoomInfoBtn.addEventListener('click', function () { openInfoPanel(); });
    chatInfoRenameBtn && chatInfoRenameBtn.addEventListener('click', renameGroupFromInfo);
    chatInfoGroupName && chatInfoGroupName.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            renameGroupFromInfo();
        }
    });
    chatInfoAddBtn && chatInfoAddBtn.addEventListener('click', addMembersFromInfo);
    chatInfoLeaveBtn && chatInfoLeaveBtn.addEventListener('click', leaveGroupFromInfo);
    chatInfoAddSearch && chatInfoAddSearch.addEventListener('input', function () {
        renderInfoAddList(chatInfoAddSearch.value);
    });
    chatInfoMedia && chatInfoMedia.addEventListener('click', function (e) {
        var tabBtn = e.target.closest('[data-media-tab]');
        if (tabBtn) {
            e.preventDefault();
            renderMediaTab(tabBtn.getAttribute('data-media-tab'));
            return;
        }

        var moreBtn = e.target.closest('[data-media-show-more]');
        if (moreBtn) {
            e.preventDefault();
            openMediaGalleryModal(moreBtn.getAttribute('data-media-show-more'));
            return;
        }

        var lightboxBtn = e.target.closest('[data-open-lightbox]');
        if (lightboxBtn) {
            e.preventDefault();
            openMediaLightbox(lightboxBtn.getAttribute('data-lightbox-url'), lightboxBtn.getAttribute('data-lightbox-kind'));
            scrollToMessage(lightboxBtn.getAttribute('data-msg-id'));
            return;
        }

        var previewItem = e.target.closest('.chat-media-preview-item');
        if (previewItem) {
            e.preventDefault();
            handleMediaPreviewClick(previewItem);
        }
    });
    chatMediaGalleryModal && chatMediaGalleryModal.addEventListener('click', function (e) {
        if (e.target === chatMediaGalleryModal) {
            closeMediaGalleryModal();
            return;
        }
        var previewItem = e.target.closest('.chat-media-preview-item');
        if (previewItem) {
            e.preventDefault();
            handleMediaPreviewClick(previewItem);
        }
    });
    chatMediaGalleryClose && chatMediaGalleryClose.addEventListener('click', closeMediaGalleryModal);
    chatInfoAddToggle && chatInfoAddToggle.addEventListener('click', function () {
        if (!activeId) return;
        openInfoPanel({ addMode: true, focusAdd: true, keepTab: true });
    });
    chatMediaLightboxClose && chatMediaLightboxClose.addEventListener('click', closeMediaLightbox);
    chatMediaLightbox && chatMediaLightbox.addEventListener('click', function (e) {
        if (e.target === chatMediaLightbox) closeMediaLightbox();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (chatMediaGalleryModal && !chatMediaGalleryModal.hidden) {
            closeMediaGalleryModal();
            return;
        }
        if (chatMediaLightbox && !chatMediaLightbox.hidden) closeMediaLightbox();
    });
    updateActionMenu();
    updateDocTitle();
})();
