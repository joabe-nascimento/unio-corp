/**
 * Importação Trilha Unio — preview CSV, abas e utilitários.
 */
(function () {
    'use strict';

    var REQUIRED = ['nome', 'procedimento', 'data_cirurgia'];

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function parseCsvLine(line) {
        var result = [];
        var cur = '';
        var inQuotes = false;
        for (var i = 0; i < line.length; i++) {
            var ch = line[i];
            if (ch === '"') {
                inQuotes = !inQuotes;
                continue;
            }
            if (ch === ',' && !inQuotes) {
                result.push(cur.trim());
                cur = '';
                continue;
            }
            cur += ch;
        }
        result.push(cur.trim());
        return result;
    }

    function analyzeCsv(text) {
        var lines = (text || '').split(/\r\n|\r|\n/).filter(function (l) { return l.trim() !== ''; });
        if (!lines.length) {
            return { rows: 0, headers: [], missing: REQUIRED.slice(), ok: false };
        }

        var headerLine = lines[0].replace(/^\uFEFF/, '');
        var headers = parseCsvLine(headerLine).map(function (h) {
            return h.toLowerCase().replace(/[\s-]+/g, '_');
        });

        var normalized = headers.map(function (h) {
            if (h === 'paciente' || h === 'nome_paciente') return 'nome';
            if (h === 'protocolo' || h === 'trilha') return 'procedimento';
            if (h === 'data' || h === 'data_alta') return 'data_cirurgia';
            return h;
        });

        var missing = REQUIRED.filter(function (col) {
            return normalized.indexOf(col) === -1;
        });

        var dataRows = Math.max(0, lines.length - 1);

        return {
            rows: dataRows,
            headers: headers,
            missing: missing,
            ok: missing.length === 0 && dataRows > 0,
        };
    }

    function updatePreview(root, analysis) {
        var preview = qs('[data-trilha-preview]', root);
        var rowsEl = qs('[data-trilha-preview-rows]', root);
        var colsEl = qs('[data-trilha-preview-cols]', root);
        var statusEl = qs('[data-trilha-preview-status]', root);
        var submit = qs('[data-trilha-submit]', root);

        if (!preview || !rowsEl) return;

        if (analysis.rows === 0 && !analysis.headers.length) {
            preview.hidden = true;
            if (submit) submit.disabled = false;
            return;
        }

        preview.hidden = false;
        rowsEl.textContent = analysis.rows + (analysis.rows === 1 ? ' paciente detectado' : ' pacientes detectados');
        colsEl.textContent = analysis.headers.length
            ? 'Colunas: ' + analysis.headers.join(', ')
            : '';

        if (statusEl) {
            if (analysis.ok) {
                statusEl.textContent = 'Pronto';
                statusEl.className = 'trilha-import-preview__badge is-ok';
            } else if (analysis.missing.length) {
                statusEl.textContent = 'Falta: ' + analysis.missing.join(', ');
                statusEl.className = 'trilha-import-preview__badge is-warn';
            } else {
                statusEl.textContent = 'Sem linhas';
                statusEl.className = 'trilha-import-preview__badge is-warn';
            }
        }

        if (submit) submit.disabled = !analysis.ok;
    }

    function readFile(file, cb) {
        if (!file) {
            cb('');
            return;
        }
        var reader = new FileReader();
        reader.onload = function (e) { cb(e.target.result || ''); };
        reader.readAsText(file);
    }

    function initTabs(root) {
        var tabs = qsa('[data-trilha-tab]', root);
        var panes = qsa('[data-trilha-pane]', root);

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                var id = tab.getAttribute('data-trilha-tab');
                tabs.forEach(function (t) {
                    var active = t === tab;
                    t.classList.toggle('is-active', active);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                panes.forEach(function (pane) {
                    var show = pane.getAttribute('data-trilha-pane') === id;
                    pane.classList.toggle('is-active', show);
                    pane.hidden = !show;
                });
            });
        });
    }

    function initCopy(root) {
        var headersBtn = qs('[data-trilha-copy-headers]', document);
        if (headersBtn) {
            headersBtn.addEventListener('click', function () {
                var cols = qsa('.trilha-import-col code', document).map(function (el) { return el.textContent; });
                navigator.clipboard.writeText(cols.join(',')).then(function () {
                    headersBtn.innerHTML = '<i class="fas fa-check mr-1"></i> Copiado!';
                    setTimeout(function () {
                        headersBtn.innerHTML = '<i class="fas fa-copy mr-1"></i> Copiar cabeçalho';
                    }, 1800);
                });
            });
        }

        qsa('[data-trilha-copy-text]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-trilha-copy-text') || '';
                navigator.clipboard.writeText(text).then(function () {
                    btn.classList.add('is-copied');
                    setTimeout(function () { btn.classList.remove('is-copied'); }, 1200);
                });
            });
        });
    }

    function init(root) {
        var textarea = qs('[data-trilha-csv-text]', root);
        var fileInput = qs('#trilha-import-arquivo', root);
        var exampleBtn = qs('[data-trilha-fill-example]', root);

        function refreshFromText(text) {
            updatePreview(root, analyzeCsv(text));
        }

        if (textarea) {
            textarea.addEventListener('input', function () {
                refreshFromText(textarea.value);
            });
            if (textarea.value.trim()) refreshFromText(textarea.value);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                readFile(file, refreshFromText);
            });
        }

        if (exampleBtn && textarea) {
            exampleBtn.addEventListener('click', function () {
                var placeholder = textarea.getAttribute('placeholder') || '';
                textarea.value = placeholder.replace(/&#10;/g, '\n');
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                qsa('[data-trilha-tab]', root).forEach(function (t) {
                    if (t.getAttribute('data-trilha-tab') === 'colar') t.click();
                });
                textarea.focus();
            });
        }

        initTabs(root);
    }

    function boot() {
        initCopy(document);
        qsa('[data-trilha-import-root]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
