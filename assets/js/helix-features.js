/**
 * HELIX FEATURES
 * Copiar, editar, avaliar mensagens e timestamps
 */
(function(window) {
    'use strict';

    /**
     * Adiciona botões de ação em cada mensagem
     */
    function enhanceMessage(msgEl, message, isAssistant) {
        if (!msgEl) return;

        // Adicionar header com timestamp
        addMessageHeader(msgEl, message, isAssistant);

        // Adicionar ações
        if (isAssistant) {
            addAssistantActions(msgEl, message);
        } else {
            addUserActions(msgEl, message);
        }
    }

    /**
     * Adiciona header com autor e timestamp
     */
    function addMessageHeader(msgEl, message, isAssistant) {
        var bubble = msgEl.querySelector('.helix-msg-bubble');
        if (!bubble) return;

        var header = document.createElement('div');
        header.className = 'helix-msg__header';
        
        var author = document.createElement('span');
        author.className = 'helix-msg__author';
        author.textContent = isAssistant ? (window.helixAssistantName || 'Sasha') : 'Você';
        
        var timestamp = document.createElement('span');
        timestamp.className = 'helix-msg__timestamp';
        timestamp.textContent = formatTimestamp(message.at || message.created_at);
        
        header.appendChild(author);
        header.appendChild(timestamp);
        bubble.prepend(header);
    }

    /**
     * Formata timestamp para exibição
     */
    function formatTimestamp(isoString) {
        if (!isoString) return '';
        
        try {
            var date = new Date(isoString);
            var now = new Date();
            var diff = now - date;
            
            // Menos de 1 minuto
            if (diff < 60000) {
                return 'agora';
            }
            
            // Menos de 1 hora
            if (diff < 3600000) {
                var mins = Math.floor(diff / 60000);
                return mins + 'min atrás';
            }
            
            // Hoje
            if (date.toDateString() === now.toDateString()) {
                return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            }
            
            // Ontem
            var yesterday = new Date(now);
            yesterday.setDate(yesterday.getDate() - 1);
            if (date.toDateString() === yesterday.toDateString()) {
                return 'ontem ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            }
            
            // Outra data
            return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) + 
                   ' ' + date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch (e) {
            return '';
        }
    }

    /**
     * Adiciona ações para mensagens do assistente
     */
    function addAssistantActions(msgEl, message) {
        var actions = document.createElement('div');
        actions.className = 'helix-msg__actions';
        
        // Botão copiar
        var copyBtn = createActionButton('copy', 'Copiar resposta');
        copyBtn.addEventListener('click', function() {
            copyMessage(msgEl, message);
        });
        actions.appendChild(copyBtn);
        
        // Botão avaliar (thumbs up/down)
        var ratingDiv = createRatingButtons(message);
        
        msgEl.appendChild(actions);
        
        var bubble = msgEl.querySelector('.helix-msg-bubble');
        if (bubble && ratingDiv) {
            bubble.appendChild(ratingDiv);
        }
    }

    /**
     * Adiciona ações para mensagens do usuário
     */
    function addUserActions(msgEl, message) {
        var actions = document.createElement('div');
        actions.className = 'helix-msg__actions';
        
        // Botão copiar
        var copyBtn = createActionButton('copy', 'Copiar mensagem');
        copyBtn.addEventListener('click', function() {
            copyMessage(msgEl, message);
        });
        actions.appendChild(copyBtn);
        
        // Botão editar (apenas última mensagem)
        var messages = document.querySelectorAll('.helix-msg--user');
        if (messages[messages.length - 1] === msgEl) {
            var editBtn = createActionButton('edit', 'Editar mensagem');
            editBtn.addEventListener('click', function() {
                editMessage(msgEl, message);
            });
            actions.appendChild(editBtn);
        }
        
        msgEl.appendChild(actions);
    }

    /**
     * Cria botão de ação
     */
    function createActionButton(icon, title) {
        var btn = document.createElement('button');
        btn.className = 'helix-msg__action-btn';
        btn.title = title;
        btn.setAttribute('aria-label', title);
        
        var iconMap = {
            'copy': 'fa-copy',
            'edit': 'fa-pen',
            'thumbs-up': 'fa-thumbs-up',
            'thumbs-down': 'fa-thumbs-down'
        };
        
        var i = document.createElement('i');
        i.className = 'fas ' + (iconMap[icon] || 'fa-question');
        btn.appendChild(i);
        
        return btn;
    }

    /**
     * Copia mensagem para clipboard
     */
    function copyMessage(msgEl, message) {
        var text = message.text || message.content || '';
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('Copiado!', 'success');
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }

    /**
     * Fallback para copiar
     */
    function fallbackCopy(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            showToast('Copiado!', 'success');
        } catch (err) {
            showToast('Erro ao copiar', 'error');
        }
        
        document.body.removeChild(textarea);
    }

    /**
     * Edita mensagem do usuário
     */
    function editMessage(msgEl, message) {
        var bubble = msgEl.querySelector('.helix-msg-bubble');
        if (!bubble) return;

        var originalText = message.text || message.content || '';
        
        // Criar input de edição
        var editDiv = document.createElement('div');
        editDiv.className = 'helix-edit-wrapper';
        
        var textarea = document.createElement('textarea');
        textarea.className = 'helix-edit-input';
        textarea.value = originalText;
        textarea.rows = 3;
        
        var actionsDiv = document.createElement('div');
        actionsDiv.className = 'helix-edit-actions';
        
        var saveBtn = document.createElement('button');
        saveBtn.className = 'helix-edit-btn helix-edit-btn--save';
        saveBtn.textContent = 'Salvar';
        
        var cancelBtn = document.createElement('button');
        cancelBtn.className = 'helix-edit-btn helix-edit-btn--cancel';
        cancelBtn.textContent = 'Cancelar';
        
        actionsDiv.appendChild(cancelBtn);
        actionsDiv.appendChild(saveBtn);
        
        editDiv.appendChild(textarea);
        editDiv.appendChild(actionsDiv);
        
        // Substituir conteúdo
        var originalContent = bubble.innerHTML;
        bubble.innerHTML = '';
        bubble.appendChild(editDiv);
        
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        
        // Handlers
        cancelBtn.addEventListener('click', function() {
            bubble.innerHTML = originalContent;
        });
        
        saveBtn.addEventListener('click', function() {
            var newText = textarea.value.trim();
            if (newText && newText !== originalText) {
                // Reenviar mensagem
                if (typeof window.helixResendMessage === 'function') {
                    window.helixResendMessage(newText);
                }
            }
            bubble.innerHTML = originalContent;
        });
    }

    /**
     * Cria botões de rating
     */
    function createRatingButtons(message) {
        var div = document.createElement('div');
        div.className = 'helix-msg__rating';
        
        var upBtn = document.createElement('button');
        upBtn.className = 'helix-rating-btn helix-rating-btn--thumbs-up';
        upBtn.innerHTML = '<i class="fas fa-thumbs-up"></i> Útil';
        upBtn.setAttribute('data-rating', '1');
        
        var downBtn = document.createElement('button');
        downBtn.className = 'helix-rating-btn helix-rating-btn--thumbs-down';
        downBtn.innerHTML = '<i class="fas fa-thumbs-down"></i> Não útil';
        downBtn.setAttribute('data-rating', '-1');
        
        // Se já tem rating, marcar como ativo
        if (message.rating) {
            if (message.rating > 0) {
                upBtn.classList.add('helix-rating-btn--active');
            } else if (message.rating < 0) {
                downBtn.classList.add('helix-rating-btn--active');
            }
        }
        
        // Event listeners
        upBtn.addEventListener('click', function() {
            rateMessage(message.id, 1, upBtn, downBtn);
        });
        
        downBtn.addEventListener('click', function() {
            rateMessage(message.id, -1, upBtn, downBtn);
        });
        
        div.appendChild(upBtn);
        div.appendChild(downBtn);
        
        return div;
    }

    /**
     * Envia rating para API
     */
    function rateMessage(messageId, rating, upBtn, downBtn) {
        if (!messageId) return;
        
        var url = '/api/sasha/messages/' + messageId + '/rate';
        
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ rating: rating })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                upBtn.classList.remove('helix-rating-btn--active');
                downBtn.classList.remove('helix-rating-btn--active');
                
                if (rating > 0) {
                    upBtn.classList.add('helix-rating-btn--active');
                    showToast('Obrigado pelo feedback!', 'success');
                } else {
                    downBtn.classList.add('helix-rating-btn--active');
                    showToast('Feedback registrado', 'success');
                }
            }
        })
        .catch(function(err) {
            console.error('Rating failed:', err);
            showToast('Erro ao enviar avaliação', 'error');
        });
    }

    /**
     * Mostra toast notification
     */
    function showToast(message, type) {
        var existing = document.querySelector('.helix-toast');
        if (existing) {
            existing.remove();
        }
        
        var toast = document.createElement('div');
        toast.className = 'helix-toast helix-toast--' + (type || 'success');
        
        var icon = document.createElement('i');
        icon.className = 'fas ' + (type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle');
        
        var text = document.createElement('span');
        text.textContent = message;
        
        toast.appendChild(icon);
        toast.appendChild(text);
        document.body.appendChild(toast);
        
        setTimeout(function() {
            toast.remove();
        }, 3000);
    }

    // Expor API pública
    window.HelixFeatures = {
        enhanceMessage: enhanceMessage,
        formatTimestamp: formatTimestamp,
        showToast: showToast
    };

})(window);
