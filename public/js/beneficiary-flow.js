(function () {
    'use strict';

    var methodLabels = {
        cpf: 'CPF do beneficiário',
        codigo: 'Código do paciente',
        verificacao: 'Código de verificação da carteirinha'
    };

    var placeholders = {
        cpf: '000.000.000-00',
        codigo: 'PO-0042',
        verificacao: 'PM-24K9X7Q1'
    };

    document.querySelectorAll('[data-benef-form]').forEach(function (form) {
        var input = form.querySelector('[data-benef-identificador]');
        var label = form.querySelector('[data-benef-field-label]');
        var cpfHint = form.querySelector('[data-benef-cpf-hint]');
        var methods = form.querySelectorAll('[data-benef-method]');

        function applyMethod(method) {
            if (!input || !label) return;
            label.textContent = methodLabels[method] || methodLabels.cpf;
            input.placeholder = placeholders[method] || '';
            input.value = '';
            input.removeAttribute('data-cpf-mask');
            if (cpfHint) {
                cpfHint.hidden = method !== 'cpf';
            }
            if (method === 'cpf') {
                input.setAttribute('data-cpf-mask', '');
                input.setAttribute('inputmode', 'numeric');
            } else {
                input.removeAttribute('inputmode');
            }
        }

        methods.forEach(function (radio) {
            radio.addEventListener('change', function () {
                if (radio.checked) applyMethod(radio.value);
            });
        });

        var checked = form.querySelector('[data-benef-method]:checked');
        if (checked) applyMethod(checked.value);
    });

    document.querySelectorAll('[data-cpf-mask]').forEach(function (input) {
        input.addEventListener('input', function () {
            var digits = input.value.replace(/\D/g, '').slice(0, 11);
            var out = digits;
            if (digits.length > 9) {
                out = digits.replace(/(\d{3})(\d{3})(\d{3})(\d{0,2})/, '$1.$2.$3-$4');
            } else if (digits.length > 6) {
                out = digits.replace(/(\d{3})(\d{3})(\d{0,3})/, '$1.$2.$3');
            } else if (digits.length > 3) {
                out = digits.replace(/(\d{3})(\d{0,3})/, '$1.$2');
            }
            input.value = out;
        });
    });

    var printBtn = document.querySelector('[data-benef-print]');
    if (printBtn) {
        printBtn.addEventListener('click', function () {
            document.body.classList.add('is-printing-carteirinha');
            window.print();
        });
        window.addEventListener('afterprint', function () {
            document.body.classList.remove('is-printing-carteirinha');
        });
    }

    function initIdcardWidget(widget) {
        if (widget.dataset.idcardReady === '1') {
            return;
        }
        widget.dataset.idcardReady = '1';

        var card = widget.querySelector('[data-idcard-flip]');
        var tabs = widget.querySelectorAll('[data-idcard-tab]');
        if (!card || !tabs.length) {
            return;
        }

        function setSide(side) {
            var flipped = side === 'verso';
            card.classList.toggle('is-flipped', flipped);
            card.setAttribute('aria-pressed', flipped ? 'true' : 'false');
            card.setAttribute('aria-label', flipped
                ? 'Carteira digital. Toque para ver a frente.'
                : 'Carteira digital. Toque para ver o verso.');

            tabs.forEach(function (tab) {
                var active = tab.getAttribute('data-idcard-tab') === side;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                setSide(tab.getAttribute('data-idcard-tab') || 'frente');
            });
        });

        card.addEventListener('click', function () {
            setSide(card.classList.contains('is-flipped') ? 'frente' : 'verso');
        });
    }

    document.querySelectorAll('[data-idcard-widget]').forEach(initIdcardWidget);
})();
