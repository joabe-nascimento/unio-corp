/**
 * Máscaras de entrada reutilizáveis — data-mask em qualquer formulário.
 * Tipos: cpf, phone, cep, cnpj, agency, money, uf, uppercase, patient-code, card-code, beneficiary-confirm
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

    function maskDocumento(value) {
        var d = digits(value).slice(0, 14);
        return d.length <= 11 ? maskCpf(d) : maskCnpj(d);
    }

    function maskCnj(value) {
        var d = digits(value).slice(0, 20);
        if (d.length <= 7) return d;
        if (d.length <= 9) return d.slice(0, 7) + '-' + d.slice(7);
        if (d.length <= 13) return d.slice(0, 7) + '-' + d.slice(7, 9) + '.' + d.slice(9);
        if (d.length <= 14) return d.slice(0, 7) + '-' + d.slice(7, 9) + '.' + d.slice(9, 13) + '.' + d.slice(13);
        if (d.length <= 16) return d.slice(0, 7) + '-' + d.slice(7, 9) + '.' + d.slice(9, 13) + '.' + d.slice(13, 14) + '.' + d.slice(14);
        return d.slice(0, 7) + '-' + d.slice(7, 9) + '.' + d.slice(9, 13) + '.' + d.slice(13, 14) + '.' + d.slice(14, 16) + '.' + d.slice(16);
    }

    function maskMoney(value) {
        var d = digits(value);
        if (d === '') return '';
        // Formato estável sem NBSP (evita quebra no parse do backend).
        var formatted = (parseInt(d, 10) / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        return 'R$ ' + formatted;
    }

    function maskUf(value) {
        return String(value || '')
            .toUpperCase()
            .replace(/[^A-Z]/g, '')
            .slice(0, 2);
    }

    function maskUppercase(value) {
        return String(value || '')
            .toUpperCase()
            .replace(/\s+/g, '')
            .slice(0, 32);
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
            case 'documento':
                input.value = maskDocumento(raw);
                break;
            case 'cnj':
                input.value = maskCnj(raw);
                break;
            case 'money':
                input.value = maskMoney(raw);
                break;
            case 'uf':
                input.value = maskUf(raw);
                break;
            case 'uppercase':
                input.value = maskUppercase(raw);
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

    function isValidCpf(value) {
        var cpf = digits(value);
        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
        var t, i, sum, digit;
        for (t = 9; t < 11; t++) {
            sum = 0;
            for (i = 0; i < t; i++) sum += parseInt(cpf.charAt(i), 10) * ((t + 1) - i);
            digit = ((10 * sum) % 11) % 10;
            if (parseInt(cpf.charAt(t), 10) !== digit) return false;
        }
        return true;
    }

    function isValidCnpj(value) {
        var cnpj = digits(value);
        if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;
        var w1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        var w2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        var sum = 0;
        var i;
        for (i = 0; i < 12; i++) sum += parseInt(cnpj.charAt(i), 10) * w1[i];
        var d1 = sum % 11;
        d1 = d1 < 2 ? 0 : 11 - d1;
        if (parseInt(cnpj.charAt(12), 10) !== d1) return false;
        sum = 0;
        for (i = 0; i < 13; i++) sum += parseInt(cnpj.charAt(i), 10) * w2[i];
        var d2 = sum % 11;
        d2 = d2 < 2 ? 0 : 11 - d2;
        return parseInt(cnpj.charAt(13), 10) === d2;
    }

    var UFS = {
        AC: 1, AL: 1, AP: 1, AM: 1, BA: 1, CE: 1, DF: 1, ES: 1, GO: 1, MA: 1,
        MT: 1, MS: 1, MG: 1, PA: 1, PB: 1, PR: 1, PE: 1, PI: 1, RJ: 1, RN: 1,
        RS: 1, RO: 1, RR: 1, SC: 1, SP: 1, SE: 1, TO: 1
    };

    function validateInput(input) {
        if (!input || input.disabled) {
            return true;
        }
        input.setCustomValidity('');
        var value = String(input.value || '').trim();
        var type = resolveMaskType(input) || input.getAttribute('data-validate') || '';
        var required = input.hasAttribute('required');

        if (value === '') {
            if (required) {
                input.setCustomValidity('Campo obrigatório.');
                return false;
            }
            return true;
        }

        switch (type) {
            case 'phone':
                if (digits(value).length < 10 || digits(value).length > 11) {
                    input.setCustomValidity('Telefone inválido. Use DDD + número.');
                    return false;
                }
                break;
            case 'cpf':
                if (!isValidCpf(value)) {
                    input.setCustomValidity('CPF inválido.');
                    return false;
                }
                break;
            case 'cnpj':
                if (!isValidCnpj(value)) {
                    input.setCustomValidity('CNPJ inválido.');
                    return false;
                }
                break;
            case 'cep':
                if (digits(value).length !== 8) {
                    input.setCustomValidity('CEP deve ter 8 dígitos.');
                    return false;
                }
                break;
            case 'uf':
                if (!UFS[value.toUpperCase()]) {
                    input.setCustomValidity('UF inválida.');
                    return false;
                }
                break;
            case 'money':
                if (digits(value) === '') {
                    input.setCustomValidity('Valor inválido.');
                    return false;
                }
                break;
            case 'documento': {
                var docLen = digits(value).length;
                if (docLen === 11 && !isValidCpf(value)) {
                    input.setCustomValidity('CPF inválido.');
                    return false;
                }
                if (docLen === 14 && !isValidCnpj(value)) {
                    input.setCustomValidity('CNPJ inválido.');
                    return false;
                }
                if (docLen !== 11 && docLen !== 14) {
                    input.setCustomValidity('Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.');
                    return false;
                }
                break;
            }
            case 'cnj':
                if (digits(value).length !== 20) {
                    input.setCustomValidity('Número do processo deve seguir o padrão CNJ (20 dígitos).');
                    return false;
                }
                break;
            default:
                break;
        }

        if (input.type === 'email' && value !== '') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                input.setCustomValidity('E-mail inválido.');
                return false;
            }
        }

        return true;
    }

    function validateForm(form) {
        if (!form) return true;
        var ok = true;
        var firstInvalid = null;
        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.disabled || el.type === 'hidden') return;
            if (!validateInput(el)) {
                ok = false;
                if (!firstInvalid) firstInvalid = el;
            }
        });
        if (firstInvalid) {
            firstInvalid.reportValidity();
            firstInvalid.focus();
        }
        return ok;
    }

    global.UnioInputMasks = {
        apply: applyMask,
        init: initInput,
        reset: resetInput,
        scan: scan,
        validateInput: validateInput,
        validateForm: validateForm,
        isValidCpf: isValidCpf,
        isValidCnpj: isValidCnpj,
        maskCpf: maskCpf,
        maskPhone: maskPhone,
        maskCep: maskCep,
        maskCnpj: maskCnpj,
        maskUf: maskUf,
        maskMoney: maskMoney,
        maskDocumento: maskDocumento,
        maskCnj: maskCnj,
    };

    function boot() {
        scan(document);
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form || !form.matches || !form.matches('[data-cadastro-form], [data-unio-validate]')) {
                return;
            }
            if (!validateForm(form)) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
        document.addEventListener('blur', function (e) {
            var input = e.target;
            if (!input || !input.matches) return;
            if (input.matches('[data-mask], [data-validate], input[type="email"]')) {
                validateInput(input);
            }
        }, true);
    }

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);

    if (document.readyState !== 'loading') {
        boot();
    }
})(window, document);
