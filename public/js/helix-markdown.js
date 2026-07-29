/**
 * HELIX MARKDOWN RENDERER
 * Renderiza markdown (sem syntax highlighting por enquanto)
 */
(function(window) {
    'use strict';

    // Configurar marked
    if (typeof marked !== 'undefined') {
        marked.setOptions({
            breaks: true,
            gfm: true,
        });
    }

    /**
     * Renderiza texto markdown para HTML
     */
    function renderMarkdown(text) {
        if (!text) return '';
        
        if (typeof marked === 'undefined') {
            // Fallback: escape HTML e quebrar linhas
            return escapeHtml(text).replace(/\n/g, '<br>');
        }

        try {
            var html = marked.parse(text);
            return wrapCodeBlocks(html);
        } catch (e) {
            console.error('Markdown parse error:', e);
            return escapeHtml(text).replace(/\n/g, '<br>');
        }
    }

    /**
     * Adiciona botão de copiar em code blocks
     */
    function wrapCodeBlocks(html) {
        return html.replace(/<pre><code([^>]*)>([\s\S]*?)<\/code><\/pre>/g, function(match, attrs, code) {
            var codeId = 'code-' + Math.random().toString(36).substr(2, 9);
            return '<div class="helix-code-wrapper">' +
                   '<button class="helix-code-copy" data-code-id="' + codeId + '" title="Copiar código">' +
                   '<i class="fas fa-copy"></i>' +
                   '</button>' +
                   '<pre id="' + codeId + '"><code' + attrs + '>' + code + '</code></pre>' +
                   '</div>';
        });
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Inicializa event listeners para copiar código
     */
    function initCopyButtons() {
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('.helix-code-copy');
            if (!btn) return;

            var codeId = btn.getAttribute('data-code-id');
            var codeEl = document.getElementById(codeId);
            if (!codeEl) return;

            var code = codeEl.textContent;
            
            // Copiar para clipboard
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function() {
                    showCopySuccess(btn);
                }).catch(function(err) {
                    console.error('Copy failed:', err);
                    fallbackCopy(code, btn);
                });
            } else {
                fallbackCopy(code, btn);
            }
        });
    }

    /**
     * Fallback para copiar (browsers antigos)
     */
    function fallbackCopy(text, btn) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            showCopySuccess(btn);
        } catch (err) {
            console.error('Fallback copy failed:', err);
        }
        
        document.body.removeChild(textarea);
    }

    /**
     * Mostra feedback de cópia bem-sucedida
     */
    function showCopySuccess(btn) {
        var originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.add('helix-code-copy--copied');
        
        setTimeout(function() {
            btn.innerHTML = originalHtml;
            btn.classList.remove('helix-code-copy--copied');
        }, 2000);
    }

    // Inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCopyButtons);
    } else {
        initCopyButtons();
    }

    // Expor API pública
    window.HelixMarkdown = {
        render: renderMarkdown,
        initCopyButtons: initCopyButtons
    };

})(window);
