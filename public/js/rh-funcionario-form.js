/**
 * rh-funcionario-form — máscaras, CEP e navegação por seções
 */
(function () {
    'use strict';

    var form = document.querySelector('[data-rh-funcionario-form]');
    if (!form) return;

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

    function maskAgency(value) {
        return digits(value).slice(0, 6);
    }

    function maskMoney(value) {
        var d = digits(value);
        if (d === '') return '';
        var num = parseInt(d, 10) / 100;
        return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function applyMask(input) {
        var type = input.getAttribute('data-mask');
        var raw = input.value;
        if (type === 'cpf') input.value = maskCpf(raw);
        else if (type === 'phone') input.value = maskPhone(raw);
        else if (type === 'cep') input.value = maskCep(raw);
        else if (type === 'agency') input.value = maskAgency(raw);
        else if (type === 'money') input.value = maskMoney(raw);
    }

    form.querySelectorAll('[data-mask]').forEach(function (input) {
        if (input.getAttribute('data-mask') === 'money') {
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
    });

    var cepInput = form.querySelector('[data-cep-lookup]');
    var cepFields = {
        logradouro: form.querySelector('#logradouro'),
        bairro: form.querySelector('#bairro'),
        cidade: form.querySelector('#cidade'),
        uf: form.querySelector('#uf'),
    };

    function fillAddress(data) {
        if (data.logradouro && cepFields.logradouro && !cepFields.logradouro.value) {
            cepFields.logradouro.value = data.logradouro;
        }
        if (data.bairro && cepFields.bairro && !cepFields.bairro.value) {
            cepFields.bairro.value = data.bairro;
        }
        if (data.localidade && cepFields.cidade && !cepFields.cidade.value) {
            cepFields.cidade.value = data.localidade;
        }
        if (data.uf && cepFields.uf && !cepFields.uf.value) {
            cepFields.uf.value = data.uf;
        }
        if (cepFields.logradouro) {
            cepFields.logradouro.focus();
        }
    }

    function lookupCep() {
        if (!cepInput) return;
        var cep = digits(cepInput.value);
        if (cep.length !== 8) return;

        cepInput.classList.add('is-loading');
        fetch('https://viacep.com.br/ws/' + cep + '/json/')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.erro) return;
                fillAddress(data);
            })
            .catch(function () { /* silencioso */ })
            .finally(function () {
                cepInput.classList.remove('is-loading');
            });
    }

    if (cepInput) {
        cepInput.addEventListener('blur', lookupCep);
    }

    var sectionIds = ['ident', 'contrato', 'endereco', 'banco', 'obs'];
    var navItems = form.querySelectorAll('[data-rh-section-trigger]');
    var panels = form.querySelectorAll('[data-rh-section-panel]');
    var btnPrev = form.querySelector('[data-rh-section-prev]');
    var btnNext = form.querySelector('[data-rh-section-next]');
    var currentSection = 'ident';

    function setSection(id) {
        if (sectionIds.indexOf(id) === -1) return;
        currentSection = id;

        navItems.forEach(function (btn) {
            var active = btn.getAttribute('data-rh-section-trigger') === id;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-current', active ? 'step' : 'false');
        });

        panels.forEach(function (panel) {
            var active = panel.getAttribute('data-rh-section-panel') === id;
            panel.classList.toggle('is-active', active);
            if (active) panel.removeAttribute('hidden');
            else panel.setAttribute('hidden', '');
        });

        if (btnPrev) btnPrev.hidden = id === sectionIds[0];
        if (btnNext) btnNext.hidden = id === sectionIds[sectionIds.length - 1];
    }

    navItems.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setSection(btn.getAttribute('data-rh-section-trigger'));
        });
    });

    if (btnPrev) {
        btnPrev.addEventListener('click', function () {
            var idx = sectionIds.indexOf(currentSection);
            if (idx > 0) setSection(sectionIds[idx - 1]);
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            var idx = sectionIds.indexOf(currentSection);
            if (idx < sectionIds.length - 1) setSection(sectionIds[idx + 1]);
        });
    }

    setSection('ident');
})();
