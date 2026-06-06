/**
 * carreiras-public — portal público de carreiras
 */
(function () {
    'use strict';

    function copyText(text, onSuccess) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                if (typeof onSuccess === 'function') onSuccess();
            }).catch(function () {
                window.prompt('Copiar link:', text);
            });
        } else {
            window.prompt('Copiar link:', text);
        }
    }

    function initCopyUrl() {
        document.querySelectorAll('[data-carreiras-copy-url]').forEach(function (btn) {
            if (btn.dataset.carreirasCopyBound) return;
            btn.dataset.carreirasCopyBound = '1';
            var defaultHtml = btn.innerHTML;
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy-text') || window.location.href;
                copyText(text, function () {
                    btn.classList.add('is-copied');
                    btn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i> Copiado!';
                    setTimeout(function () {
                        btn.classList.remove('is-copied');
                        btn.innerHTML = defaultHtml;
                    }, 2200);
                });
            });
        });
    }

    function initFilePicker() {
        document.querySelectorAll('[data-carreiras-file]').forEach(function (fileRoot) {
            if (fileRoot.dataset.carreirasFileBound) return;
            fileRoot.dataset.carreirasFileBound = '1';
            var fileInput = fileRoot.querySelector('.carreiras-apply-file-input');
            var fileText = fileRoot.querySelector('[data-carreiras-file-text]');
            var fileHint = fileRoot.querySelector('[data-carreiras-file-hint]');

            function setFile(file) {
                if (!file || !fileInput) return;
                var dt = new DataTransfer();
                dt.items.add(file);
                fileInput.files = dt.files;
                if (fileText) fileText.textContent = file.name;
                if (fileHint) fileHint.textContent = 'Arquivo pronto para envio · clique para trocar';
                fileRoot.classList.add('is-filled');
            }

            if (fileInput) {
                fileInput.addEventListener('change', function () {
                    if (fileInput.files && fileInput.files[0]) {
                        setFile(fileInput.files[0]);
                    }
                });
            }

            ['dragenter', 'dragover'].forEach(function (ev) {
                fileRoot.addEventListener(ev, function (e) {
                    e.preventDefault();
                    fileRoot.classList.add('is-dragover');
                });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                fileRoot.addEventListener(ev, function (e) {
                    e.preventDefault();
                    fileRoot.classList.remove('is-dragover');
                });
            });
            fileRoot.addEventListener('drop', function (e) {
                var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
                if (file) setFile(file);
            });
        });
    }

    function initApplyForm() {
        var form = document.querySelector('[data-carreiras-apply-form]');
        if (!form || form.dataset.carreirasApplyBound) return;
        form.dataset.carreirasApplyBound = '1';

        var steps = form.querySelectorAll('[data-carreiras-step]');
        var panels = form.querySelectorAll('[data-carreiras-panel]');
        var nextBtn = form.querySelector('[data-carreiras-next]');
        var backBtn = form.querySelector('[data-carreiras-back]');
        var submitBtn = form.querySelector('[data-carreiras-submit]');
        var stepFooters = form.querySelectorAll('[data-carreiras-step-footer]');

        function showStep(name) {
            steps.forEach(function (btn) {
                var active = btn.getAttribute('data-carreiras-step') === name;
                btn.classList.toggle('is-active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panels.forEach(function (panel) {
                var active = panel.getAttribute('data-carreiras-panel') === name;
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);
            });
            var onCurriculo = name === 'curriculo';
            if (nextBtn) nextBtn.hidden = onCurriculo;
            if (backBtn) backBtn.hidden = !onCurriculo;
            if (submitBtn) submitBtn.hidden = !onCurriculo;
            stepFooters.forEach(function (el) {
                el.hidden = !onCurriculo;
            });
        }

        function validateDados() {
            var nome = form.querySelector('#carreiras_nome');
            var email = form.querySelector('#carreiras_email');
            if (nome && !nome.checkValidity()) {
                nome.reportValidity();
                return false;
            }
            if (email && !email.checkValidity()) {
                email.reportValidity();
                return false;
            }
            return true;
        }

        steps.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var target = btn.getAttribute('data-carreiras-step');
                if (target === 'curriculo' && !validateDados()) return;
                showStep(target);
            });
        });

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                if (!validateDados()) return;
                showStep('curriculo');
            });
        }

        if (backBtn) {
            backBtn.addEventListener('click', function () {
                showStep('dados');
            });
        }
    }

    function initVagaFilters() {
        var root = document.querySelector('[data-carreiras-filters]');
        if (!root || root.dataset.carreirasFiltersBound) return;
        root.dataset.carreirasFiltersBound = '1';

        var search = root.querySelector('[data-carreiras-filter-search]');
        var dept = root.querySelector('[data-carreiras-filter-dept]');
        var contrato = root.querySelector('[data-carreiras-filter-contrato]');
        var countEl = root.querySelector('[data-carreiras-filter-count]');
        var emptyEl = root.querySelector('[data-carreiras-filter-empty]');
        var cards = Array.prototype.slice.call(document.querySelectorAll('[data-carreiras-vaga-card]'));

        function applyFilters() {
            var q = (search && search.value || '').trim().toLowerCase();
            var d = dept ? dept.value : '';
            var c = contrato ? contrato.value : '';
            var visible = 0;

            cards.forEach(function (card) {
                var matchQ = !q || (card.getAttribute('data-search') || '').indexOf(q) !== -1;
                var matchD = !d || card.getAttribute('data-departamento') === d;
                var matchC = !c || card.getAttribute('data-contrato') === c;
                var show = matchQ && matchD && matchC;
                card.hidden = !show;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            if (countEl) {
                countEl.textContent = visible + ' de ' + cards.length + ' vaga' + (cards.length !== 1 ? 's' : '');
            }
            if (emptyEl) {
                emptyEl.hidden = visible > 0 || cards.length === 0;
            }
        }

        if (search) search.addEventListener('input', applyFilters);
        if (dept) dept.addEventListener('change', applyFilters);
        if (contrato) contrato.addEventListener('change', applyFilters);
        applyFilters();
    }

    window.CarreirasPublic = {
        init: function () {
            initCopyUrl();
            initFilePicker();
            initApplyForm();
            initVagaFilters();
        },
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            window.CarreirasPublic.init();
        });
    } else {
        window.CarreirasPublic.init();
    }
})();
