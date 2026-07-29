/**
 * HELIX DARK MODE
 * Sistema de dark mode persistente
 */
(function(window) {
    'use strict';

    var STORAGE_KEY = 'helix-theme';
    var currentTheme = 'light';

    /**
     * Inicializa dark mode
     */
    function init() {
        // Carregar tema salvo
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'dark') {
            applyTheme('dark');
        } else {
            applyTheme('light');
        }

        // Criar toggle button se não existe
        createToggle();
    }

    /**
     * Cria botão de toggle
     */
    function createToggle() {
        var header = document.querySelector('.helix-header-actions');
        if (!header || document.getElementById('helixDarkModeToggle')) return;

        var toggle = document.createElement('button');
        toggle.id = 'helixDarkModeToggle';
        toggle.className = 'helix-darkmode-toggle';
        toggle.setAttribute('aria-label', 'Alternar modo escuro');
        toggle.setAttribute('title', 'Alternar modo escuro');
        
        var thumb = document.createElement('span');
        thumb.className = 'helix-darkmode-toggle__thumb';
        thumb.innerHTML = currentTheme === 'dark' ? '🌙' : '☀️';
        
        toggle.appendChild(thumb);
        toggle.addEventListener('click', toggleTheme);
        
        // Inserir antes do botão de histórico
        var historyBtn = document.getElementById('helixHistoryToggle');
        if (historyBtn) {
            header.insertBefore(toggle, historyBtn);
        } else {
            header.prepend End(toggle);
        }
    }

    /**
     * Aplica tema
     */
    function applyTheme(theme) {
        currentTheme = theme;
        var panel = document.getElementById('helixPanel');
        
        if (panel) {
            panel.setAttribute('data-helix-theme', theme);
        }
        
        // Atualizar ícone
        var thumb = document.querySelector('.helix-darkmode-toggle__thumb');
        if (thumb) {
            thumb.innerHTML = theme === 'dark' ? '🌙' : '☀️';
        }
        
        localStorage.setItem(STORAGE_KEY, theme);
    }

    /**
     * Toggle tema
     */
    function toggleTheme() {
        var newTheme = currentTheme === 'light' ? 'dark' : 'light';
        applyTheme(newTheme);
    }

    /**
     * Get tema atual
     */
    function getTheme() {
        return currentTheme;
    }

    // Auto-inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expor API pública
    window.HelixDarkMode = {
        init: init,
        toggle: toggleTheme,
        getTheme: getTheme,
        applyTheme: applyTheme
    };

})(window);
