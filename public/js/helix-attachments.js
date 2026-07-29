/**
 * HELIX ATTACHMENTS
 * Upload de PDF/DOCX/TXT no chat: extrai o texto no servidor (ad-hoc, nada é
 * salvo) e mantém uma lista de anexos pendentes para anexar à próxima mensagem.
 */
(function (window) {
    'use strict';

    var MAX_ATTACHMENTS = 3;
    var pending = []; // { filename, text, truncated }

    function uploadUrl() {
        return '/api/sasha/upload';
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function render() {
        var container = document.getElementById('helixAttachments');
        if (!container) return;

        if (pending.length === 0) {
            container.hidden = true;
            container.innerHTML = '';
            return;
        }

        container.hidden = false;
        container.innerHTML = pending.map(function (item, index) {
            var icon = item.loading ? 'fa-spinner fa-spin' : (item.error ? 'fa-triangle-exclamation' : 'fa-file-lines');
            var statusClass = item.error ? ' helix-attachment-chip--error' : '';
            var title = item.error ? item.error : (item.truncated ? 'Texto truncado (documento muito longo)' : item.filename);
            return (
                '<span class="helix-attachment-chip' + statusClass + '" title="' + escapeHtml(title) + '">' +
                '<i class="fas ' + icon + '" aria-hidden="true"></i>' +
                '<span class="helix-attachment-chip__name">' + escapeHtml(item.filename) + '</span>' +
                '<button type="button" class="helix-attachment-chip__remove" data-remove-index="' + index + '" aria-label="Remover anexo">' +
                '<i class="fas fa-xmark" aria-hidden="true"></i>' +
                '</button>' +
                '</span>'
            );
        }).join('');

        container.querySelectorAll('[data-remove-index]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var idx = parseInt(btn.getAttribute('data-remove-index'), 10);
                pending.splice(idx, 1);
                render();
            });
        });

        if (typeof window.helixUpdateSendState === 'function') {
            window.helixUpdateSendState();
        }
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function uploadFile(file) {
        if (pending.length >= MAX_ATTACHMENTS) {
            if (typeof HelixFeatures !== 'undefined') {
                HelixFeatures.showToast('Máximo de ' + MAX_ATTACHMENTS + ' anexos por mensagem', 'error');
            }
            return;
        }

        var item = { filename: file.name, text: '', loading: true, truncated: false, error: null };
        pending.push(item);
        render();

        var formData = new FormData();
        formData.append('arquivo', file);

        fetch(uploadUrl(), { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
            .then(function (result) {
                item.loading = false;
                if (!result.ok || result.data.error) {
                    item.error = (result.data && result.data.error) || 'Falha ao processar o arquivo';
                } else {
                    item.text = result.data.text || '';
                    item.truncated = !!result.data.truncated;
                    item.filename = result.data.filename || item.filename;
                }
                render();
            })
            .catch(function () {
                item.loading = false;
                item.error = 'Falha ao enviar o arquivo';
                render();
            });
    }

    function handleFiles(fileList) {
        Array.prototype.forEach.call(fileList || [], function (file) {
            uploadFile(file);
        });
    }

    /**
     * Monta o bloco de texto extraído dos anexos prontos, para anexar à mensagem
     * do usuário antes de enviar. Ignora anexos ainda carregando ou com erro.
     */
    function getReadySnapshot() {
        return pending
            .filter(function (p) { return !p.loading && !p.error && p.text; })
            .map(function (p) {
                return {
                    filename: p.filename,
                    truncated: !!p.truncated,
                };
            });
    }

    function buildMessageSuffix() {
        var ready = pending.filter(function (p) { return !p.loading && !p.error && p.text; });
        if (ready.length === 0) return '';

        return ready.map(function (p) {
            var aviso = p.truncated ? ' (texto truncado — documento muito longo)' : '';
            return '\n\n[Anexo: ' + p.filename + aviso + ']\n' + p.text;
        }).join('');
    }

    function hasPending() {
        return pending.length > 0;
    }

    function isBusy() {
        return pending.some(function (p) { return p.loading; });
    }

    function clear() {
        pending = [];
        render();
    }

    function init() {
        var attachBtn = document.getElementById('helixAttachBtn');
        var attachInput = document.getElementById('helixAttachInput');
        var composer = document.querySelector('.helix-composer');
        var messagesEl = document.getElementById('helixMessages');

        if (attachBtn && attachInput) {
            attachBtn.addEventListener('click', function () {
                attachInput.click();
            });
            attachInput.addEventListener('change', function () {
                handleFiles(attachInput.files);
                attachInput.value = '';
            });
        }

        // Drag & drop no painel do chat inteiro
        [composer, messagesEl].forEach(function (el) {
            if (!el) return;
            el.addEventListener('dragover', function (e) {
                e.preventDefault();
                el.classList.add('is-drag-over');
            });
            el.addEventListener('dragleave', function () {
                el.classList.remove('is-drag-over');
            });
            el.addEventListener('drop', function (e) {
                e.preventDefault();
                el.classList.remove('is-drag-over');
                if (e.dataTransfer && e.dataTransfer.files) {
                    handleFiles(e.dataTransfer.files);
                }
            });
        });
    }

    window.HelixAttachments = {
        init: init,
        buildMessageSuffix: buildMessageSuffix,
        getReadySnapshot: getReadySnapshot,
        hasPending: hasPending,
        isBusy: isBusy,
        clear: clear,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
