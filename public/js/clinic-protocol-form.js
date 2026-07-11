(function () {
    'use strict';

    var checklistEl = document.getElementById('checklist_text');
    var previewList = document.getElementById('clinic-protocol-preview-checklist');
    var previewEmpty = document.getElementById('clinic-protocol-preview-empty');
    var diasEl = document.getElementById('duracao_dias');
    var dorEl = document.getElementById('regras_dor_p1');
    var febreEl = document.getElementById('regras_febre_p2');
    var previewDias = document.getElementById('clinic-protocol-preview-dias');
    var previewDor = document.getElementById('clinic-protocol-preview-dor');
    var previewFebre = document.getElementById('clinic-protocol-preview-febre');

    if (!checklistEl || !previewList) {
        return;
    }

    function parseChecklist(text) {
        return String(text || '')
            .split('\n')
            .map(function (line) { return line.trim(); })
            .filter(Boolean)
            .map(function (line) {
                var match = line.match(/^(\d+)\s*:\s*(.+)$/);
                if (!match) {
                    return null;
                }
                return { dia: match[1], item: match[2] };
            })
            .filter(Boolean)
            .sort(function (a, b) { return Number(a.dia) - Number(b.dia); });
    }

    function renderChecklist() {
        var items = parseChecklist(checklistEl.value);
        previewList.innerHTML = '';

        if (!items.length) {
            if (previewEmpty) {
                previewEmpty.hidden = false;
            }
            return;
        }

        if (previewEmpty) {
            previewEmpty.hidden = true;
        }

        items.forEach(function (item) {
            var li = document.createElement('li');
            li.className = 'pos-op-protocol-progress__item';
            li.innerHTML =
                '<span class="pos-op-protocol-progress__day">D+' + item.dia + '</span>' +
                '<span class="pos-op-protocol-progress__text">' + item.item + '</span>';
            previewList.appendChild(li);
        });
    }

    function syncMetrics() {
        if (previewDias && diasEl) {
            previewDias.textContent = diasEl.value || '—';
        }
        if (previewDor && dorEl) {
            previewDor.textContent = dorEl.value || '—';
        }
        if (previewFebre && febreEl) {
            previewFebre.textContent = febreEl.value || '—';
        }
    }

    function refresh() {
        renderChecklist();
        syncMetrics();
    }

    ['input', 'change'].forEach(function (evt) {
        checklistEl.addEventListener(evt, renderChecklist);
        if (diasEl) diasEl.addEventListener(evt, syncMetrics);
        if (dorEl) dorEl.addEventListener(evt, syncMetrics);
        if (febreEl) febreEl.addEventListener(evt, syncMetrics);
    });

    refresh();
})();
