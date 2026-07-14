(function (window, document) {
    'use strict';

    var LAST_FICHA_KEY = 'unio.posop.lastFicha';

    function replaceIdInUrl(tpl, id) {
        return String(tpl).replace('/0/', '/' + id + '/').replace(/\/0$/, '/' + id);
    }

    function setOffcanvasHeading(offcanvasId, title, subtitle) {
        var root = document.querySelector('[data-unio-offcanvas="' + offcanvasId + '"]');
        if (!root) {
            return;
        }
        var titleEl = root.querySelector('.unio-offcanvas-title');
        var subtitleEl = root.querySelector('.unio-offcanvas-subtitle');
        if (titleEl && title) {
            titleEl.textContent = title;
        }
        if (subtitleEl && subtitle) {
            subtitleEl.textContent = subtitle;
        }
    }

    function readLastFicha() {
        try {
            var raw = window.localStorage.getItem(LAST_FICHA_KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (err) {
            return null;
        }
    }

    function rememberLastFicha(id, nome, codigo) {
        if (!id) {
            return;
        }
        try {
            window.localStorage.setItem(LAST_FICHA_KEY, JSON.stringify({
                id: String(id),
                nome: nome || '',
                codigo: codigo || '',
                at: Date.now(),
            }));
        } catch (err) {
            /* ignore quota */
        }
        refreshUltimaFichaBtn();
    }

    function refreshUltimaFichaBtn() {
        var btn = document.getElementById('posOpUltimaFichaBtn');
        if (!btn) {
            return;
        }
        var last = readLastFicha();
        if (!last || !last.id) {
            btn.hidden = true;
            return;
        }
        btn.hidden = false;
        btn.setAttribute('data-pos-op-paciente-ficha', last.id);
        btn.setAttribute('data-pos-op-paciente-nome', last.nome || '');
        btn.setAttribute('data-pos-op-paciente-codigo', last.codigo || '');
        btn.setAttribute('data-unio-offcanvas-open', 'pos-op-paciente-ficha');
        var label = last.codigo || last.nome || 'ficha';
        btn.title = 'Abrir última ficha · ' + label;
    }

    function applyFichaMeta(host) {
        if (!host) {
            return;
        }
        var meta = host.querySelector('#posOpFichaMeta');
        if (!meta) {
            return;
        }
        var codigo = meta.getAttribute('data-codigo') || '';
        var nome = meta.getAttribute('data-nome') || '';
        var procedimento = meta.getAttribute('data-procedimento') || '';
        var diaPos = meta.getAttribute('data-dia-pos') || '';
        var id = meta.getAttribute('data-id') || '';
        var subtitleParts = [codigo, procedimento];
        if (diaPos !== '') {
            subtitleParts.push('D+' + diaPos);
        }
        setOffcanvasHeading(
            'pos-op-paciente-ficha',
            nome || codigo || 'Ficha do paciente',
            subtitleParts.filter(Boolean).join(' · ')
        );
        if (id) {
            rememberLastFicha(id, nome, codigo);
        }
    }

    function renderPartialError(host, retryUrl, loadingText, onLoaded) {
        host.innerHTML =
            '<div class="clinic-partial-error" role="alert">' +
            '<p class="clinic-partial-error__text mb-2">Não foi possível carregar. Tente novamente.</p>' +
            '<button type="button" class="btn-unio-ghost btn-sm" data-clinic-partial-retry>' +
            '<i class="fas fa-rotate-right mr-1" aria-hidden="true"></i>Tentar de novo</button>' +
            '</div>';
        var retry = host.querySelector('[data-clinic-partial-retry]');
        if (retry) {
            retry.addEventListener('click', function () {
                loadPartial(host, retryUrl, loadingText, onLoaded);
            });
        }
    }

    function loadPartial(host, url, loadingText, onLoaded) {
        if (!host || !url) {
            return Promise.resolve();
        }
        host.innerHTML = '<p class="text-muted small mb-0">' + (loadingText || 'Carregando…') + '</p>';
        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }
                return res.text();
            })
            .then(function (html) {
                host.innerHTML = html;
                if (typeof onLoaded === 'function') {
                    onLoaded(host);
                }
                if (window.ClinicPacienteForm && typeof window.ClinicPacienteForm.refresh === 'function') {
                    window.ClinicPacienteForm.refresh();
                }
                if (window.UnioInputMasks && typeof window.UnioInputMasks.scan === 'function') {
                    window.UnioInputMasks.scan(host);
                }
            })
            .catch(function () {
                renderPartialError(host, url, loadingText, onLoaded);
            });
    }

    function loadEditPartial(id, nome, codigo) {
        var host = document.getElementById('posOpPacienteEditHost');
        var label = document.getElementById('posOpPacienteEditLabel');
        if (!id || !host) {
            return Promise.resolve();
        }
        var urlTpl = host.getAttribute('data-pos-op-form-partial-url') || '';
        nome = nome || '';
        codigo = codigo || '';
        if (label) {
            label.textContent = codigo ? codigo + ' · ' + nome : nome;
        }
        setOffcanvasHeading('pos-op-paciente-edit', 'Editar paciente', codigo ? codigo + ' · ' + nome : nome);
        return loadPartial(host, replaceIdInUrl(urlTpl, id), 'Carregando formulário…');
    }

    function loadFichaPartial(id, nome, codigo) {
        var host = document.getElementById('posOpPacienteFichaHost');
        var editFromFicha = document.getElementById('posOpPacienteFichaEditBtn');
        if (!id || !host) {
            return Promise.resolve();
        }
        var urlTpl = host.getAttribute('data-pos-op-ficha-partial-url') || '';
        codigo = codigo || '';
        setOffcanvasHeading(
            'pos-op-paciente-ficha',
            codigo ? codigo + ' · Ficha clínica' : 'Ficha do paciente',
            nome || 'Carregando…'
        );
        if (editFromFicha) {
            editFromFicha.hidden = false;
            editFromFicha.setAttribute('data-pos-op-paciente-edit', id);
            editFromFicha.setAttribute('data-pos-op-paciente-nome', nome || '');
            editFromFicha.setAttribute('data-pos-op-paciente-codigo', codigo);
        }
        rememberLastFicha(id, nome, codigo);
        return loadPartial(host, replaceIdInUrl(urlTpl, id), 'Carregando ficha…', applyFichaMeta);
    }

    function openOffcanvas(id) {
        if (window.UnioOffcanvas && typeof window.UnioOffcanvas.open === 'function') {
            window.UnioOffcanvas.open(id);
        }
    }

    function findRowEditTrigger(id) {
        var nodes = document.querySelectorAll('[data-pos-op-paciente-edit="' + id + '"]');
        for (var i = 0; i < nodes.length; i++) {
            if (nodes[i].id !== 'posOpPacienteFichaEditBtn') {
                return nodes[i];
            }
        }
        return null;
    }

    var delegatedBound = false;

    function bindDelegatedTriggers() {
        if (delegatedBound) {
            return;
        }
        delegatedBound = true;
        document.addEventListener('click', function (e) {
            var fichaBtn = e.target.closest('[data-pos-op-paciente-ficha]');
            if (fichaBtn) {
                loadFichaPartial(
                    fichaBtn.getAttribute('data-pos-op-paciente-ficha'),
                    fichaBtn.getAttribute('data-pos-op-paciente-nome') || '',
                    fichaBtn.getAttribute('data-pos-op-paciente-codigo') || ''
                );
                return;
            }

            var editBtn = e.target.closest('[data-pos-op-paciente-edit]');
            if (editBtn && editBtn.id !== 'posOpPacienteFichaEditBtn') {
                loadEditPartial(
                    editBtn.getAttribute('data-pos-op-paciente-edit'),
                    editBtn.getAttribute('data-pos-op-paciente-nome') || '',
                    editBtn.getAttribute('data-pos-op-paciente-codigo') || ''
                );
            }
        }, true);
    }

    function bindFichaFooterEdit() {
        var editFromFicha = document.getElementById('posOpPacienteFichaEditBtn');
        if (!editFromFicha || editFromFicha.dataset.posOpBound) {
            return;
        }
        editFromFicha.dataset.posOpBound = '1';
        editFromFicha.addEventListener('click', function () {
            var id = editFromFicha.getAttribute('data-pos-op-paciente-edit');
            if (!id) {
                return;
            }
            loadEditPartial(
                id,
                editFromFicha.getAttribute('data-pos-op-paciente-nome') || '',
                editFromFicha.getAttribute('data-pos-op-paciente-codigo') || ''
            );
            openOffcanvas('pos-op-paciente-edit');
        });
    }

    function cleanOpenQuery() {
        try {
            var url = new URL(window.location.href);
            var changed = false;
            ['open_novo', 'open_edit', 'open_ficha'].forEach(function (key) {
                if (url.searchParams.has(key)) {
                    url.searchParams.delete(key);
                    changed = true;
                }
            });
            if (changed) {
                window.history.replaceState({}, '', url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : '') + url.hash);
            }
        } catch (err) {
            /* ignore */
        }
    }

    function openByQuery(options) {
        options = options || {};
        var opened = false;

        if (options.openNovo) {
            openOffcanvas('pos-op-paciente-form');
            opened = true;
        }

        if (options.openEditId) {
            var editId = String(options.openEditId);
            var rowEdit = findRowEditTrigger(editId);
            if (rowEdit) {
                loadEditPartial(
                    editId,
                    rowEdit.getAttribute('data-pos-op-paciente-nome') || '',
                    rowEdit.getAttribute('data-pos-op-paciente-codigo') || ''
                );
            } else {
                loadEditPartial(editId, '', '');
            }
            openOffcanvas('pos-op-paciente-edit');
            opened = true;
        }

        if (options.openFichaId) {
            var fichaId = String(options.openFichaId);
            var rowFicha = document.querySelector('[data-pos-op-paciente-ficha="' + fichaId + '"]');
            if (rowFicha) {
                loadFichaPartial(
                    fichaId,
                    rowFicha.getAttribute('data-pos-op-paciente-nome') || '',
                    rowFicha.getAttribute('data-pos-op-paciente-codigo') || ''
                );
            } else {
                loadFichaPartial(fichaId, '', '');
            }
            openOffcanvas('pos-op-paciente-ficha');
            opened = true;
        }

        if (opened) {
            cleanOpenQuery();
        }
    }

    window.PosOperatorioHub = window.PosOperatorioHub || {
        initPacientesList: function (options) {
            bindDelegatedTriggers();
            bindFichaFooterEdit();
            refreshUltimaFichaBtn();
            openByQuery(options);
        },
    };
})(window, document);
