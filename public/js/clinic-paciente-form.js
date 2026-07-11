(function (window, document) {
    'use strict';

    function initForm(root) {
        if (!root || root.getAttribute('data-clinic-paciente-init') === '1') {
            return;
        }
        root.setAttribute('data-clinic-paciente-init', '1');

        var protocolSelect = root.querySelector('[data-clinic-protocol-select]');
        var dataInput = root.querySelector('[id$="data_cirurgia"], [name="data_cirurgia"]');
        var procedimentoHidden = root.querySelector('[name="procedimento"]');
        var preview = root.querySelector('.clinic-paciente-preview');
        var previewPhase = root.querySelector('[data-clinic-preview-phase]');
        var previewDia = root.querySelector('[data-clinic-preview-dia]');
        var previewDias = root.querySelector('[data-clinic-preview-dias]');
        var previewMarcos = root.querySelector('[data-clinic-preview-marcos]');

        function selectedProtocolOption() {
            if (!protocolSelect || protocolSelect.selectedIndex < 0) {
                return null;
            }
            return protocolSelect.options[protocolSelect.selectedIndex];
        }

        function calcDiaPos(dataCirurgia) {
            if (!dataCirurgia) {
                return null;
            }
            var parts = dataCirurgia.split('-');
            if (parts.length !== 3) {
                return null;
            }
            var surgery = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            surgery.setHours(0, 0, 0, 0);
            var diff = Math.floor((today - surgery) / 86400000);
            return diff < 0 ? 0 : diff;
        }

        function refreshPreview() {
            var option = selectedProtocolOption();
            var hasProtocol = option && option.value !== '';
            var dataVal = dataInput ? dataInput.value : '';
            var diaPos = calcDiaPos(dataVal);

            if (procedimentoHidden && option) {
                procedimentoHidden.value = option.getAttribute('data-procedimento') || '';
            }

            if (!preview) {
                return;
            }

            if (!hasProtocol && !dataVal) {
                preview.hidden = true;
                return;
            }

            preview.hidden = false;

            if (previewDias) {
                previewDias.textContent = hasProtocol
                    ? (option.getAttribute('data-dias') || '—') + ' dias'
                    : '—';
            }
            if (previewMarcos) {
                previewMarcos.textContent = hasProtocol
                    ? (option.getAttribute('data-checklist') || '0') + ' itens'
                    : '—';
            }
            if (previewDia) {
                previewDia.textContent = diaPos !== null ? 'D+' + diaPos : '—';
            }
            if (previewPhase) {
                if (hasProtocol && diaPos !== null) {
                    var nome = option.textContent.trim();
                    previewPhase.textContent = nome + ' · fase D+' + diaPos;
                } else if (hasProtocol) {
                    previewPhase.textContent = option.textContent.trim();
                } else {
                    previewPhase.textContent = 'Informe a data da cirurgia para calcular a fase';
                }
            }
        }

        if (protocolSelect) {
            protocolSelect.addEventListener('change', refreshPreview);
        }
        if (dataInput) {
            dataInput.addEventListener('change', refreshPreview);
            dataInput.addEventListener('input', refreshPreview);
        }

        refreshPreview();
        if (window.UnioInputMasks && typeof window.UnioInputMasks.scan === 'function') {
            window.UnioInputMasks.scan(root);
        }
    }

    function scan() {
        document.querySelectorAll('[data-clinic-paciente-form]').forEach(initForm);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }

    window.ClinicPacienteForm = {
        init: initForm,
        refresh: scan,
    };
})(window, document);
