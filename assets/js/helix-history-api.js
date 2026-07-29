/**
 * HELIX HISTORY API
 * Integração com API de histórico de conversas
 */
(function(window) {
    'use strict';

    var currentConversationId = null;
    var conversations = [];

    /**
     * Carrega lista de conversas
     */
    function loadConversations(searchTerm) {
        var url = '/api/sasha/conversations';
        if (searchTerm) {
            url += '?search=' + encodeURIComponent(searchTerm);
        }
        
        return fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // API retorna array diretamente ou { conversations: [...] }
                conversations = Array.isArray(data) ? data : (data.conversations || []);
                renderConversations(conversations);
                return conversations;
            })
            .catch(function(err) {
                console.error('Failed to load conversations:', err);
                return [];
            });
    }

    /**
     * Renderiza lista de conversas no sidebar
     */
    function renderConversations(convs) {
        var list = document.getElementById('helixHistoryList');

        var countEl = document.getElementById('helixHistoryCount');
        if (countEl) countEl.textContent = String((convs || []).length);

        var toggleCountEl = document.querySelector('.helix-history-toggle-count');
        if (toggleCountEl) {
            var n = (convs || []).length;
            toggleCountEl.textContent = String(n);
            toggleCountEl.setAttribute('data-count', String(n));
        }

        if (!list) return;

        if (!convs || convs.length === 0) {
            list.innerHTML = '<div class="helix-history-empty">Nenhuma conversa ainda</div>';
            return;
        }

        list.innerHTML = '';
        
        // Separar fixadas das não fixadas
        var pinned = convs.filter(function(c) { return c.pinned; });
        var unpinned = convs.filter(function(c) { return !c.pinned; });
        
        // Renderizar fixadas primeiro
        if (pinned.length > 0) {
            var pinnedHeader = document.createElement('div');
            pinnedHeader.className = 'helix-history-section-header';
            pinnedHeader.textContent = 'Fixadas';
            list.appendChild(pinnedHeader);
            
            pinned.forEach(function(conv) {
                list.appendChild(createConversationItem(conv));
            });
        }
        
        // Renderizar não fixadas
        if (unpinned.length > 0 && pinned.length > 0) {
            var unpinnedHeader = document.createElement('div');
            unpinnedHeader.className = 'helix-history-section-header';
            unpinnedHeader.textContent = 'Recentes';
            list.appendChild(unpinnedHeader);
        }
        
        unpinned.forEach(function(conv) {
            list.appendChild(createConversationItem(conv));
        });
    }

    /**
     * Cria elemento de conversa
     */
    function createConversationItem(conv) {
        var item = document.createElement('div');
        item.className = 'helix-history-item';
        if (conv.pinned) {
            item.classList.add('helix-history-item--pinned');
        }
        if (conv.id === currentConversationId) {
            item.classList.add('helix-history-item--active');
        }
        item.setAttribute('data-conversation-id', conv.id);
        
        var title = document.createElement('div');
        title.className = 'helix-history-item__title';
        title.textContent = conv.title || 'Conversa sem título';
        
        var meta = document.createElement('div');
        meta.className = 'helix-history-item__meta';
        meta.textContent = formatDate(conv.updated_at) + ' • ' + (conv.message_count || 0) + ' msgs';
        
        var actions = document.createElement('div');
        actions.className = 'helix-history-item__actions';
        
        var pinBtn = document.createElement('button');
        pinBtn.className = 'helix-history-action helix-history-action--pin';
        if (conv.pinned) {
            pinBtn.classList.add('is-pinned');
        }
        pinBtn.innerHTML = '<i class="fas fa-thumbtack"></i>';
        pinBtn.title = conv.pinned ? 'Desafixar' : 'Fixar';
        pinBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            togglePin(conv.id);
        });
        
        var deleteBtn = document.createElement('button');
        deleteBtn.className = 'helix-history-action helix-history-action--delete';
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
        deleteBtn.title = 'Excluir';
        deleteBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            deleteConversation(conv.id);
        });
        
        actions.appendChild(pinBtn);
        actions.appendChild(deleteBtn);
        
        item.appendChild(title);
        item.appendChild(meta);
        item.appendChild(actions);
        
        // Click para carregar conversa
        item.addEventListener('click', function() {
            loadConversation(conv.id);
        });
        
        return item;
    }

    /**
     * Formata data para exibição
     */
    function formatDate(isoString) {
        if (!isoString) return '';
        
        try {
            var date = new Date(isoString);
            var now = new Date();
            var diff = now - date;
            
            if (diff < 60000) return 'agora';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'min';
            if (diff < 86400000) return Math.floor(diff / 3600000) + 'h';
            if (date.toDateString() === now.toDateString()) return 'hoje';
            
            var yesterday = new Date(now);
            yesterday.setDate(yesterday.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) return 'ontem';
            
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
        } catch (e) {
            return '';
        }
    }

    /**
     * Carrega uma conversa específica
     */
    function loadConversation(conversationId) {
        var url = '/api/sasha/conversations/' + conversationId;
        
        return fetch(url)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                currentConversationId = conversationId;
                
                // Renderizar mensagens
                if (data.messages && typeof window.helixRenderHistory === 'function') {
                    window.helixRenderHistory(data.messages);
                }
                
                // Atualizar UI
                updateActiveConversation(conversationId);
                
                return data;
            })
            .catch(function(err) {
                console.error('Failed to load conversation:', err);
                if (typeof HelixFeatures !== 'undefined') {
                    HelixFeatures.showToast('Erro ao carregar conversa', 'error');
                }
            });
    }

    /**
     * Atualiza item ativo na lista
     */
    function updateActiveConversation(id) {
        var items = document.querySelectorAll('.helix-history-item');
        items.forEach(function(item) {
            if (item.getAttribute('data-conversation-id') == id) {
                item.classList.add('helix-history-item--active');
            } else {
                item.classList.remove('helix-history-item--active');
            }
        });
    }

    /**
     * Toggle pin/unpin de conversa
     */
    function togglePin(conversationId) {
        var url = '/api/sasha/conversations/' + conversationId + '/pin';
        
        fetch(url, { method: 'POST' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    loadConversations();
                }
            })
            .catch(function(err) {
                console.error('Failed to toggle pin:', err);
            });
    }

    /**
     * Deleta conversa
     */
    function deleteConversation(conversationId) {
        if (!confirm('Deseja realmente excluir esta conversa?')) {
            return;
        }
        
        var url = '/api/sasha/conversations/' + conversationId;
        
        fetch(url, { method: 'DELETE' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (conversationId === currentConversationId) {
                        currentConversationId = null;
                        // Limpar chat
                        if (typeof window.helixClearChat === 'function') {
                            window.helixClearChat();
                        }
                    }
                    loadConversations();
                    if (typeof HelixFeatures !== 'undefined') {
                        HelixFeatures.showToast('Conversa excluída', 'success');
                    }
                }
            })
            .catch(function(err) {
                console.error('Failed to delete conversation:', err);
                if (typeof HelixFeatures !== 'undefined') {
                    HelixFeatures.showToast('Erro ao excluir conversa', 'error');
                }
            });
    }

    /**
     * Cria nova conversa
     */
    function newConversation() {
        currentConversationId = null;
        if (typeof window.helixClearChat === 'function') {
            window.helixClearChat();
        }
        updateActiveConversation(null);
    }

    /**
     * Get ID da conversa atual
     */
    function getCurrentConversationId() {
        return currentConversationId;
    }

    /**
     * Set ID da conversa atual (usado após enviar primeira mensagem)
     */
    function setCurrentConversationId(id) {
        currentConversationId = id;
        updateActiveConversation(id);
    }

    /**
     * Inicializa search
     */
    function initSearch() {
        var searchInput = document.getElementById('helixHistorySearch');
        if (!searchInput) return;

        var timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                var term = searchInput.value.trim();
                loadConversations(term);
            }, 300);
        });
    }

    /**
     * Inicializa
     */
    function init() {
        initSearch();
        loadConversations();
    }

    // Auto-inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expor API pública
    window.HelixHistory = {
        init: init,
        loadConversations: loadConversations,
        loadConversation: loadConversation,
        newConversation: newConversation,
        getCurrentConversationId: getCurrentConversationId,
        setCurrentConversationId: setCurrentConversationId
    };

})(window);
