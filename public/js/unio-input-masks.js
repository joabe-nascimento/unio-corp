/**
 * Máscaras de entrada reutilizáveis — data-mask em qualquer formulário.
 * Tipos: cpf, phone, cep, cnpj, agency, money, patient-code, card-code, beneficiary-confirm
 */
(function (global, document) {
    'use strict';

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function maskCpf(value) {
        var d = digits(value).slice(0, 11);
        if (d.length <= 3) return d;
        if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
        if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
        return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
    }

    function maskPhone(value) {
        var d = digits(value).slice(0, 11);
        if (d.length === 0) return '';
        if (d.length <= 2) return '(' + d;
        if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
        if (d.length <= 10) {
            return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
        }
        return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
    }

    function maskCep(value) {
        var d = digits(value).slice(0, 8);
        if (d.length <= 5) return d;
        return d.slice(0, 5) + '-' + d.slice(5);
    }

    function maskCnpj(value) {
        var d = digits(value).slice(0, 14);
        if (d.length <= 2) return d;
        if (d.length <= 5) return d.slice(0, 2) + '.' + d.slice(2);
        if (d.length <= 8) return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5);
        if (d.length <= 12) {
            return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8);
        }
        return d.slice(0, 2) + '.' + d.slice(2, 5) + '.' + d.slice(5, 8) + '/' + d.slice(8, 12) + '-' + d.slice(12);
    }

    function maskAgency(value) {
        return digits(value).slice(0, 6);
    }

    function maskMoney(value) {
        var d = digits(value);
        if (d === '') return '';
        return (parseInt(d, 10) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function maskPatientCode(value) {
        var raw = String(value || '').toUpperCase().replace(/\s+/g, '');
        if (/^PO-?/i.test(raw)) {
            var nums = digits(raw.replace(/^PO-?/i, '')).slice(0, 6);
            return nums === '' ? 'PO-' : 'PO-' + nums;
        }
        if (/^P$/i.test(raw)) {
            return 'PO-';
        }
        if (/^P[^O]/i.test(raw)) {
            return maskPatientCode('PO-' + digits(raw.slice(1)));
        }
        var onlyDigits = digits(raw).slice(0, 6);
        if (onlyDigits !== '' && raw === onlyDigits) {
            return 'PO-' + onlyDigits;
        }
        return raw.slice(0, 10);
    }

    function maskCardCode(value) {
        return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 32);
    }

    function maskBeneficiaryConfirm(value) {
        var trim = String(value || '').trim();
        if (/^PO-?/i.test(trim) || (/^P/i.test(trim) && !/^\d/.test(trim))) {
            return maskPatientCode(trim);
        }
        if (/^[\d.\-\s/]+$/.test(trim) && digits(trim).length > 0) {
            return maskCpf(trim);
        }
        return maskCardCode(trim);
    }

    function resolveMaskType(input) {
        if (input.hasAttribute('data-cpf-mask')) {
            return 'cpf';
        }
        return input.getAttribute('data-mask') || '';
    }

    function applyMask(input) {
        if (!input) return;
        var type = resolveMaskType(input);
        var raw = input.value;
        switch (type) {
            case 'cpf':
                input.value = maskCpf(raw);
                break;
            case 'phone':
                input.value = maskPhone(raw);
                break;
            case 'cep':
                input.value = maskCep(raw);
                break;
            case 'cnpj':
                input.value = maskCnpj(raw);
                break;
            case 'agency':
                input.value = maskAgency(raw);
                break;
            case 'money':
                input.value = maskMoney(raw);
                break;
            case 'patient-code':
                input.value = maskPatientCode(raw);
                break;
            case 'card-code':
                input.value = maskCardCode(raw);
                break;
            case 'beneficiary-confirm':
                input.value = maskBeneficiaryConfirm(raw);
                break;
            default:
                break;
        }
    }

    function initInput(input) {
        if (!input || input.getAttribute('data-unio-mask-init') === '1') {
            return;
        }
        var type = resolveMaskType(input);
        if (!type) {
            return;
        }

        input.setAttribute('data-unio-mask-init', '1');

        if (type === 'money') {
            var raw = String(input.value || '').trim();
            if (/^\d+(\.\d{1,2})?$/.test(raw)) {
                input.value = maskMoney(String(Math.round(parseFloat(raw) * 100)));
            } else {
                applyMask(input);
            }
        } else {
            applyMask(input);
        }

        input.addEventListener('input', function () {
            applyMask(input);
        });
    }

    function resetInput(input) {
        if (!input) return;
        input.removeAttribute('data-unio-mask-init');
        initInput(input);
    }

    function scan(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('[data-mask], [data-cpf-mask]').forEach(initInput);
    }

    global.UnioInputMasks = {
        apply: applyMask,
        init: initInput,
        reset: resetInput,
        scan: scan,
        maskCpf: maskCpf,
        maskPhone: maskPhone,
        maskCep: maskCep,
        maskCnpj: maskCnpj,
    };

    function boot() {
        scan(document);
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);

    if (document.readyState !== 'loading') {
        boot();
    }
})(window, document);
