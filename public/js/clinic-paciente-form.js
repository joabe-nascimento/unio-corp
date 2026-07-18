(function (window, document) {
    'use strict';

    function digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    function getTomSelect(select) {
        return select && select.tomselect ? select.tomselect : null;
    }

    function getSelectValue(select) {
        if (!select) return '';
        var ts = getTomSelect(select);
        return ts ? String(ts.getValue() || '') : String(select.value || '');
    }

    function setSelectValue(select, value) {
        if (!select) return;
        var ts = getTomSelect(select);
        if (ts) ts.setValue(value || '', true);
        else select.value = value || '';
    }

    function initTomSelect(select, placeholder, extra) {
        if (!select || getTomSelect(select) || typeof window.TomSelect === 'undefined') {
            return getTomSelect(select);
        }
        var options = Object.assign({
            allowEmptyOption: true,
            create: false,
            maxOptions: 500,
            placeholder: placeholder || 'Selecione…',
            dropdownParent: 'body',
            sortField: { field: 'text', direction: 'asc' },
        }, extra || {});
        return new window.TomSelect(select, options);
    }

    function optionIbge(select, value) {
        if (!select || !value) return '';
        var ts = getTomSelect(select);
        if (ts && ts.options[value] && ts.options[value].ibge) {
            return ts.options[value].ibge;
        }
        var opts = select.options || [];
        for (var i = 0; i < opts.length; i++) {
            if (opts[i].value === value) {
                return opts[i].getAttribute('data-ibge') || '';
            }
        }
        return '';
    }

    function setSelectDisabled(select, disabled) {
        if (!select) return;
        var ts = getTomSelect(select);
        if (ts) {
            if (disabled) ts.disable();
            else ts.enable();
        } else {
            select.disabled = disabled;
        }
    }

    function fillSelect(select, items, selectedValue, placeholder) {
        if (!select) return;
        var current = selectedValue != null ? String(selectedValue) : String(select.value || '');
        var ts = getTomSelect(select);

        if (ts) {
            ts.clear(true);
            ts.clearOptions();
            ts.addOption({ value: '', text: placeholder || 'Selecione…' });

            var found = false;
            (items || []).forEach(function (item) {
                var entry = {
                    value: item.value,
                    text: item.label || item.value,
                };
                if (item.ibge) entry.ibge = item.ibge;
                ts.addOption(entry);
                if (current && item.value === current) found = true;
            });

            if (current && !found && current !== '__outro__') {
                ts.addOption({ value: current, text: current });
                found = true;
            }

            ts.setValue(found ? current : '', true);
            ts.refreshOptions(false);
            return;
        }

        select.innerHTML = '';
        var empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder || 'Selecione…';
        select.appendChild(empty);

        var foundNative = false;
        (items || []).forEach(function (item) {
            var opt = document.createElement('option');
            opt.value = item.value;
            opt.textContent = item.label || item.value;
            if (item.ibge) opt.setAttribute('data-ibge', item.ibge);
            if (current && item.value === current) {
                opt.selected = true;
                foundNative = true;
            }
            select.appendChild(opt);
        });

        if (current && !foundNative) {
            var custom = document.createElement('option');
            custom.value = current;
            custom.textContent = current;
            custom.selected = true;
            select.appendChild(custom);
        }
    }

    function ensureOutroOption(select) {
        if (!select) return;
        var ts = getTomSelect(select);
        if (ts) {
            if (!ts.options.__outro__) {
                ts.addOption({ value: '__outro__', text: 'Outro (digitar)' });
                ts.refreshOptions(false);
            }
            return;
        }
        var has = false;
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.value === '__outro__') has = true;
        });
        if (!has) {
            var opt = document.createElement('option');
            opt.value = '__outro__';
            opt.textContent = 'Outro (digitar)';
            select.appendChild(opt);
        }
    }

    function initEndereco(root) {
        var box = root.querySelector('[data-clinic-endereco]');
        if (!box || box.getAttribute('data-endereco-init') === '1') return;
        box.setAttribute('data-endereco-init', '1');

        var cepUrlTpl = box.getAttribute('data-cep-url') || '';
        var cidadesUrl = box.getAttribute('data-cidades-url') || '';
        var bairrosUrl = box.getAttribute('data-bairros-url') || '';

        var cepInput = box.querySelector('[data-endereco-cep]');
        var logradouro = box.querySelector('[data-endereco-logradouro]');
        var complemento = box.querySelector('[data-endereco-complemento]');
        var ufSelect = box.querySelector('[data-endereco-uf]');
        var cidadeSelect = box.querySelector('[data-endereco-cidade]');
        var bairroSelect = box.querySelector('[data-endereco-bairro]');
        var bairroOutro = box.querySelector('[data-endereco-bairro-outro]');
        var statusEl = box.querySelector('[data-endereco-cep-status]');
        var numero = box.querySelector('[data-endereco-numero]');

        var cityIbge = '';
        var loadingCities = false;
        var loadingBairros = false;

        function setStatus(msg, isError) {
            if (!statusEl) return;
            statusEl.textContent = msg || '';
            statusEl.classList.toggle('text-danger', !!isError);
            statusEl.classList.toggle('text-muted', !isError);
        }

        function syncBairroOutro() {
            if (!bairroSelect || !bairroOutro) return;
            var ts = getTomSelect(bairroSelect);
            var value = ts ? ts.getValue() : bairroSelect.value;
            var isOutro = value === '__outro__';
            bairroOutro.hidden = !isOutro;
            if (isOutro) {
                bairroOutro.setAttribute('name', 'bairro');
                bairroSelect.removeAttribute('name');
                if (!bairroOutro.value) bairroOutro.focus();
            } else {
                bairroSelect.setAttribute('name', 'bairro');
                bairroOutro.removeAttribute('name');
            }
        }

        function selectedCityIbge() {
            if (!cidadeSelect) return cityIbge || '';
            var ts = getTomSelect(cidadeSelect);
            var val = ts ? ts.getValue() : cidadeSelect.value;
            return optionIbge(cidadeSelect, val) || cityIbge || '';
        }

        function loadBairros(preferred, extraFromCep) {
            if (!bairroSelect || !bairrosUrl) return Promise.resolve();
            var ibge = selectedCityIbge();
            var cityName = cidadeSelect ? getSelectValue(cidadeSelect) : '';
            if (!ibge && !extraFromCep && !preferred && !cityName) {
                fillSelect(bairroSelect, [], '', 'Selecione a cidade');
                ensureOutroOption(bairroSelect);
                syncBairroOutro();
                return Promise.resolve();
            }
            // Cidade escolhida sem IBGE: ainda libera “Outro” / bairro do CEP.
            if (!ibge && (extraFromCep || preferred || cityName)) {
                var seedOnly = preferred || extraFromCep || '';
                fillSelect(bairroSelect, seedOnly ? [{ value: seedOnly, label: seedOnly }] : [], seedOnly, 'Selecione…');
                ensureOutroOption(bairroSelect);
                if (!seedOnly) {
                    setSelectValue(bairroSelect, '__outro__');
                }
                syncBairroOutro();
                return Promise.resolve();
            }
            loadingBairros = true;
            setSelectDisabled(bairroSelect, true);
            var url = bairrosUrl + '?ibge=' + encodeURIComponent(ibge || '');
            if (extraFromCep) url += '&bairro=' + encodeURIComponent(extraFromCep);
            return fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (r) {
                    return r.json().then(function (body) {
                        return { ok: r.ok, body: body };
                    });
                })
                .then(function (res) {
                    var items = (res.body && res.body.items) || [];
                    fillSelect(bairroSelect, items, preferred || extraFromCep || '', 'Selecione…');
                    ensureOutroOption(bairroSelect);
                    syncBairroOutro();
                })
                .catch(function () {
                    var seed = preferred || extraFromCep || '';
                    fillSelect(bairroSelect, seed ? [{ value: seed, label: seed }] : [], seed, 'Selecione…');
                    ensureOutroOption(bairroSelect);
                    syncBairroOutro();
                })
                .finally(function () {
                    loadingBairros = false;
                    setSelectDisabled(bairroSelect, false);
                });
        }

        function loadCidades(uf, preferredCity, preferredBairro, fromCepBairro) {
            if (!cidadeSelect || !cidadesUrl) return Promise.resolve();
            if (!uf) {
                fillSelect(cidadeSelect, [], '', 'Selecione a UF');
                fillSelect(bairroSelect, [], '', 'Selecione a cidade');
                ensureOutroOption(bairroSelect);
                syncBairroOutro();
                return Promise.resolve();
            }
            loadingCities = true;
            setSelectDisabled(cidadeSelect, true);
            fillSelect(cidadeSelect, [], preferredCity || '', 'Carregando…');
            return fetch(cidadesUrl + '?uf=' + encodeURIComponent(uf), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) {
                    return r.json().then(function (body) {
                        return { ok: r.ok, status: r.status, body: body };
                    });
                })
                .then(function (res) {
                    var items = (res.body && res.body.items) || [];
                    if (!res.ok || !items.length) {
                        fillSelect(
                            cidadeSelect,
                            preferredCity ? [{ value: preferredCity, label: preferredCity }] : [],
                            preferredCity || '',
                            (res.body && res.body.error) || 'Nenhuma cidade encontrada'
                        );
                        return loadBairros(preferredBairro, fromCepBairro);
                    }
                    fillSelect(cidadeSelect, items, preferredCity || '', 'Selecione…');
                    if (preferredCity) {
                        cityIbge = optionIbge(cidadeSelect, preferredCity) || cityIbge;
                    }
                    if (!cityIbge && cidadeSelect) {
                        var tsCity = getTomSelect(cidadeSelect);
                        var cityVal = tsCity ? tsCity.getValue() : cidadeSelect.value;
                        cityIbge = optionIbge(cidadeSelect, cityVal) || cityIbge;
                    }
                    return loadBairros(preferredBairro, fromCepBairro);
                })
                .catch(function () {
                    fillSelect(
                        cidadeSelect,
                        preferredCity ? [{ value: preferredCity, label: preferredCity }] : [],
                        preferredCity || '',
                        'Não foi possível carregar'
                    );
                    return loadBairros(preferredBairro, fromCepBairro);
                })
                .finally(function () {
                    loadingCities = false;
                    setSelectDisabled(cidadeSelect, false);
                });
        }

        initTomSelect(ufSelect, 'UF', { sortField: null });
        initTomSelect(cidadeSelect, 'Selecione a cidade');
        initTomSelect(bairroSelect, 'Selecione o bairro');
        if (ufSelect) ufSelect.classList.add('clinic-endereco-ts');
        if (cidadeSelect) cidadeSelect.classList.add('clinic-endereco-ts');
        if (bairroSelect) bairroSelect.classList.add('clinic-endereco-ts');

        function lookupCep() {
            if (!cepInput || !cepUrlTpl) return;
            var d = digits(cepInput.value);
            if (d.length !== 8) return;
            setStatus('Buscando CEP…');
            var url = cepUrlTpl.replace('00000000', d);
            fetch(url, { headers: { Accept: 'application/json' } })
                .then(function (r) {
                    return r.json().then(function (body) {
                        return { ok: r.ok, body: body };
                    });
                })
                .then(function (res) {
                    if (!res.ok || !res.body || !res.body.endereco) {
                        setStatus((res.body && res.body.error) || 'CEP não encontrado.', true);
                        return;
                    }
                    var e = res.body.endereco;
                    setStatus('Endereço encontrado.');
                    if (logradouro && e.logradouro) logradouro.value = e.logradouro;
                    if (complemento && e.complemento && !complemento.value) complemento.value = e.complemento;
                    if (cepInput && e.cep) cepInput.value = e.cep;
                    cityIbge = e.ibge || '';
                    if (ufSelect && e.uf) {
                        setSelectValue(ufSelect, e.uf);
                    }
                    return loadCidades(e.uf || '', e.cidade || '', e.bairro || '', e.bairro || '').then(function () {
                        if (numero) numero.focus();
                    });
                })
                .catch(function () {
                    setStatus('Falha ao consultar o CEP.', true);
                });
        }

        if (cepInput) {
            var cepTimer = null;
            cepInput.addEventListener('blur', lookupCep);
            cepInput.addEventListener('input', function () {
                clearTimeout(cepTimer);
                var d = digits(cepInput.value);
                if (d.length === 8) {
                    cepTimer = setTimeout(lookupCep, 280);
                }
            });
        }

        if (ufSelect) {
            ufSelect.addEventListener('change', function () {
                cityIbge = '';
                loadCidades(getSelectValue(ufSelect), '', '', '');
            });
        }

        if (cidadeSelect) {
            cidadeSelect.addEventListener('change', function () {
                var ts = getTomSelect(cidadeSelect);
                var val = ts ? ts.getValue() : cidadeSelect.value;
                cityIbge = optionIbge(cidadeSelect, val) || '';
                loadBairros('', '');
            });
        }

        if (bairroSelect) {
            bairroSelect.addEventListener('change', syncBairroOutro);
        }

        // Estado inicial (edição)
        var initialUf = ufSelect ? getSelectValue(ufSelect) : '';
        var initialCidade = cidadeSelect ? (cidadeSelect.getAttribute('data-initial-cidade') || cidadeSelect.value) : '';
        var initialBairro = bairroSelect ? (bairroSelect.getAttribute('data-initial-bairro') || bairroSelect.value) : '';
        ensureOutroOption(bairroSelect);
        syncBairroOutro();
        if (initialUf) {
            loadCidades(initialUf, initialCidade, initialBairro, initialBairro);
        }
    }

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
        initEndereco(root);
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

    // Offcanvas pode injetar o form depois
    document.addEventListener('shown.bs.offcanvas', scan);
    document.addEventListener('unio:offcanvas-open', scan);

    window.ClinicPacienteForm = {
        init: initForm,
        refresh: scan,
    };
})(window, document);
