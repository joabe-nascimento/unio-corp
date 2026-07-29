/**
 * HELIX TYPING INDICATOR
 * Indicador animado de "assistente está digitando..."
 */
(function(window) {
    'use strict';

    var typingElement = null;
    var typingTimeout = null;

    /**
     * Mostra typing indicator
     */
    function show() {
        if (typingElement) {
            return; // Já está visível
        }

        var container = document.getElementById('helixBody');
        if (!container) return;

        typingElement = document.createElement('div');
        typingElement.className = 'helix-msg helix-msg--assistant helix-typing';
        typingElement.id = 'helixTyping';
        
        var avatar = document.createElement('div');
        avatar.className = 'helix-msg-avatar';
        avatar.innerHTML = '<img src="/images/sasha-avatar.png" alt="Sasha" onerror="this.src=\'/images/default-avatar.png\'">';
        
        var bubble = document.createElement('div');
        bubble.className = 'helix-msg-bubble';
        
        var dots = document.createElement('div');
        dots.className = 'helix-typing__dots';
        dots.innerHTML = '<span class="helix-typing__dot"></span>' +
                         '<span class="helix-typing__dot"></span>' +
                         '<span class="helix-typing__dot"></span>';
        
        var text = document.createElement('div');
        text.className = 'helix-typing__text';
        text.textContent = (window.helixAssistantName || 'Sasha') + ' está digitando...';
        
        bubble.appendChild(dots);
        bubble.appendChild(text);
        
        typingElement.appendChild(avatar);
        typingElement.appendChild(bubble);
        
        container.appendChild(typingElement);
        
        // Scroll to bottom
        if (typeof window.helixScrollToBottom === 'function') {
            window.helixScrollToBottom();
        }
        
        // Auto-hide após 30s (fallback)
        typingTimeout = setTimeout(function() {
            hide();
        }, 30000);
    }

    /**
     * Esconde typing indicator
     */
    function hide() {
        if (typingTimeout) {
            clearTimeout(typingTimeout);
            typingTimeout = null;
        }
        
        if (typingElement) {
            typingElement.remove();
            typingElement = null;
        }
    }

    /**
     * Verifica se está visível
     */
    function isVisible() {
        return typingElement !== null;
    }

    // Expor API pública
    window.HelixTyping = {
        show: show,
        hide: hide,
        isVisible: isVisible
    };

})(window);
