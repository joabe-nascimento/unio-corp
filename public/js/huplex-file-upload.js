/**
 * huplex-file-upload — drop zone + preview do arquivo selecionado
 */
(function () {
    'use strict';

    function formatSize(bytes) {
        if (!bytes || bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function iconForFile(name) {
        var ext = (name.split('.').pop() || '').toLowerCase();
        if (ext === 'pdf') return 'fa-file-pdf';
        if (['jpg', 'jpeg', 'png', 'webp', 'gif'].indexOf(ext) !== -1) return 'fa-file-image';
        if (['doc', 'docx'].indexOf(ext) !== -1) return 'fa-file-word';
        return 'fa-file-lines';
    }

    function init(root) {
        if (root.dataset.huplexFileUploadInit) return;
        root.dataset.huplexFileUploadInit = '1';

        var input = root.querySelector('.huplex-file-upload-input');
        var drop = root.querySelector('.huplex-file-upload-drop');
        var preview = root.querySelector('.huplex-file-upload-preview');
        var nameEl = root.querySelector('[data-file-name]');
        var clearBtn = root.querySelector('[data-file-clear]');
        var iconEl = root.querySelector('.huplex-file-upload-preview-icon i');

        if (!input || !drop || !preview) return;

        function showPreview(file) {
            if (!file) return;
            nameEl.textContent = file.name + ' (' + formatSize(file.size) + ')';
            if (iconEl) iconEl.className = 'fas ' + iconForFile(file.name);
            drop.hidden = true;
            preview.hidden = false;
            root.classList.add('huplex-file-upload--has-file');
        }

        function reset() {
            input.value = '';
            drop.hidden = false;
            preview.hidden = true;
            root.classList.remove('huplex-file-upload--has-file');
        }

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) showPreview(input.files[0]);
            else reset();
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                reset();
            });
        }

        ['dragenter', 'dragover'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) {
                e.preventDefault();
                drop.classList.add('huplex-file-upload-drop--over');
            });
        });
        ['dragleave', 'drop'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) {
                e.preventDefault();
                drop.classList.remove('huplex-file-upload-drop--over');
            });
        });
        drop.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (!files || !files.length) return;
            input.files = files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    function boot() {
        document.querySelectorAll('[data-huplex-file-upload]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
