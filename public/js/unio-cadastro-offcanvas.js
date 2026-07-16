/**
 * UnioCadastroOffcanvas — create/edit em painel lateral reutilizável.
 *
 * Form:  [data-cadastro-form="panel-id"]
 * Novo:  [data-cadastro-create="panel-id"]
 * Edit:  [data-cadastro-edit="panel-id"] + data-cadastro-id + data-cadastro-token
 *        + data-field-{name}="value"
 */
(function () {
    'use strict';

    function findForm(panelId) {
        return document.querySelector('[data-cadastro-form="' + panelId + '"]');
    }

    function findPanel(panelId) {
        return document.querySelector('[data-unio-offcanvas="' + panelId + '"]');
    }

    function setTitle(panel, text) {
        if (!panel || !text) return;
        var title = panel.querySelector('.unio-offcanvas-title');
        if (title) title.textContent = text;
    }

    function setSubmitLabel(panelId, text) {
        var label = document.querySelector('[data-cadastro-submit-label="' + panelId + '"]');
        if (label && text) label.textContent = text;
    }

    function setModeOnlyVisible(form, isEdit) {
        form.querySelectorAll('[data-cadastro-edit-only]').forEach(function (el) {
            if (isEdit) {
                el.removeAttribute('hidden');
            } else {
                el.setAttribute('hidden', '');
            }
            toggleDisabled(el, !isEdit);
        });
        form.querySelectorAll('[data-cadastro-create-only]').forEach(function (el) {
            if (isEdit) {
                el.setAttribute('hidden', '');
            } else {
                el.removeAttribute('hidden');
            }
            toggleDisabled(el, isEdit);
        });
    }

    function toggleDisabled(root, disabled) {
        var nodes = root.matches('input,select,textarea') ? [root] : root.querySelectorAll('input,select,textarea');
        nodes.forEach(function (el) {
            if (disabled) {
                el.setAttribute('disabled', 'disabled');
            } else {
                el.removeAttribute('disabled');
            }
        });
    }

    function setFieldValue(el, value) {
        if (!el || el.name === '_token') return;

        if (el.type === 'checkbox') {
            el.checked = value === true || value === 1 || value === '1' || value === 'true' || value === 'on';
            return;
        }

        if (el.type === 'radio') {
            el.checked = String(el.value) === String(value);
            return;
        }

        el.value = value == null ? '' : String(value);
        if (el.hasAttribute('data-mask') && window.UnioInputMasks) {
            window.UnioInputMasks.apply(el);
        }
    }

    function refreshMasks(form) {
        if (!form || !window.UnioInputMasks) return;
        if (typeof window.UnioInputMasks.scan === 'function') {
            window.UnioInputMasks.scan(form);
        }
        form.querySelectorAll('[data-mask]').forEach(function (el) {
            window.UnioInputMasks.apply(el);
        });
    }

    function clearSelectFilters(form) {
        form.querySelectorAll('select[data-cadastro-filtered]').forEach(function (sel) {
            Array.prototype.forEach.call(sel.options, function (opt) {
                opt.hidden = false;
                opt.disabled = false;
            });
            sel.removeAttribute('data-cadastro-filtered');
        });
    }

    function applySelectFilters(form, btn) {
        clearSelectFilters(form);
        var raw = btn.getAttribute('data-select-filters');
        if (!raw) return;
        var map;
        try {
            map = JSON.parse(raw);
        } catch (e) {
            return;
        }
        Object.keys(map || {}).forEach(function (name) {
            var sel = form.querySelector('select[name="' + name + '"]');
            if (!sel) return;
            var allowed = map[name] || [];
            if (!allowed.length) return;
            Array.prototype.forEach.call(sel.options, function (opt) {
                var ok = allowed.indexOf(opt.value) !== -1;
                opt.hidden = !ok;
                opt.disabled = !ok;
            });
            sel.setAttribute('data-cadastro-filtered', '1');
        });
    }

    function fillFromButton(form, btn) {
        applySelectFilters(form, btn);

        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name || el.name === '_token') return;
            // Campos com nome[] (ex.: item_nome[]) — limpar no create; no edit usar JSON dedicado
            if (el.name.indexOf('[') !== -1) return;

            var attr = 'data-field-' + el.name;
            if (!btn.hasAttribute(attr)) return;
            setFieldValue(el, btn.getAttribute(attr));
        });

        var ativo = form.querySelector('[data-cadastro-ativo]');
        if (ativo && btn.hasAttribute('data-field-ativo')) {
            setFieldValue(ativo, btn.getAttribute('data-field-ativo'));
        }
        refreshMasks(form);
    }

    function resolveEditAction(template, id) {
        if (!template) return '';
        return String(template)
            .replace(/\/0(\/|$)/, '/' + id + '$1')
            .replace(/\/0$/, '/' + id)
            .replace(/\{id\}/g, String(id))
            .replace(/:id/g, String(id));
    }

    function resetCreate(panelId) {
        var form = findForm(panelId);
        var panel = findPanel(panelId);
        if (!form) return;

        form.reset();
        form.action = form.getAttribute('data-cadastro-create-action') || form.action;

        var tokenInput = form.querySelector('[data-cadastro-token-input]');
        var createToken = form.getAttribute('data-cadastro-create-token');
        if (tokenInput && createToken) {
            tokenInput.value = createToken;
        }

        setModeOnlyVisible(form, false);
        clearSelectFilters(form);
        setTitle(panel, form.getAttribute('data-cadastro-title-create'));
        setSubmitLabel(panelId, form.getAttribute('data-cadastro-submit-create'));
        form.removeAttribute('data-cadastro-mode');
        form.removeAttribute('data-cadastro-entity-id');
        refreshMasks(form);
    }

    function openEdit(panelId, btn) {
        var form = findForm(panelId);
        var panel = findPanel(panelId);
        if (!form || !btn) return;

        var id = btn.getAttribute('data-cadastro-id');
        if (!id) return;

        form.reset();
        fillFromButton(form, btn);

        var editTpl = form.getAttribute('data-cadastro-edit-action') || '';
        form.action = resolveEditAction(editTpl, id);

        var tokenInput = form.querySelector('[data-cadastro-token-input]');
        var editToken = btn.getAttribute('data-cadastro-token');
        if (tokenInput && editToken) {
            tokenInput.value = editToken;
        }

        setModeOnlyVisible(form, true);
        setTitle(panel, form.getAttribute('data-cadastro-title-edit'));
        setSubmitLabel(panelId, form.getAttribute('data-cadastro-submit-edit'));
        form.setAttribute('data-cadastro-mode', 'edit');
        form.setAttribute('data-cadastro-entity-id', id);
    }

    function onClick(e) {
        var createBtn = e.target.closest('[data-cadastro-create]');
        if (createBtn) {
            resetCreate(createBtn.getAttribute('data-cadastro-create'));
            return;
        }

        var editBtn = e.target.closest('[data-cadastro-edit]');
        if (editBtn) {
            openEdit(editBtn.getAttribute('data-cadastro-edit'), editBtn);
        }
    }

    document.addEventListener('click', onClick, true);

    window.UnioCadastroOffcanvas = {
        resetCreate: resetCreate,
        openEdit: openEdit,
        fillFromButton: fillFromButton,
    };
})();
