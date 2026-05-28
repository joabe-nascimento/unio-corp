import { Controller } from '@hotwired/stimulus';

const STEPS = ['dados', 'checklist'];

export default class extends Controller {
    static targets = [
        'panel',
        'progressBar',
        'progressPct',
        'progressLabel',
        'step',
        'checklistHost',
        'summary',
        'prev',
        'next',
        'complete',
        'completeHint',
        'requiredNote',
    ];

    static values = {
        createUrl: String,
        actionUrl: String,
        variant: { type: String, default: 'onboarding' },
        completeLabel: String,
        confirmMessage: String,
        csrfAction: String,
    };

    connect() {
        this.processId = null;
        this.checklist = [];
        this.step = 'dados';
        this.loading = false;
        this.onChecklistChange = this.onChecklistChange.bind(this);
        this.setStep('dados');
        this.bindCloseReset();
        this.bindChecklistDelegation();
    }

    disconnect() {
        this._closeObserver?.disconnect();
        if (this.hasChecklistHostTarget) {
            this.checklistHostTarget.removeEventListener('change', this.onChecklistChange);
        }
    }

    bindChecklistDelegation() {
        if (!this.hasChecklistHostTarget) return;
        this.checklistHostTarget.addEventListener('change', this.onChecklistChange);
    }

    onChecklistChange(event) {
        const input = event.target;
        if (!input.matches('.unio-checklist-input')) return;
        this.toggleItem({ currentTarget: input });
    }

    bindCloseReset() {
        const root = this.element.closest('[data-unio-offcanvas]');
        if (!root) return;

        this._wasOpen = root.classList.contains('is-open');
        this._closeObserver = new MutationObserver(() => {
            const open = root.classList.contains('is-open');
            if (this._wasOpen && !open) {
                this.reset();
            }
            this._wasOpen = open;
        });
        this._closeObserver.observe(root, { attributes: true, attributeFilter: ['class'] });
    }

    get form() {
        return this.element.querySelector('[data-rh-process-form]');
    }

    preventSubmit(event) {
        event.preventDefault();
    }

    reset() {
        this.processId = null;
        this.checklist = [];
        this.loading = false;
        if (this.form) {
            this.form.reset();
            this.form.querySelectorAll('select').forEach((select) => {
                if (select.tomselect) {
                    select.tomselect.clear(true);
                }
            });
        }
        if (this.hasChecklistHostTarget) {
            this.checklistHostTarget.innerHTML = '';
        }
        if (this.hasSummaryTarget) {
            this.summaryTarget.hidden = true;
            this.summaryTarget.innerHTML = '';
        }
        this.setStep('dados');
        this.setLoading(false);
    }

    goPrev() {
        if (this.step === 'checklist' && !this.processId) {
            this.setStep('dados');
            return;
        }
        if (this.step === 'checklist') {
            this.setStep('dados');
        }
    }

    async goNext() {
        if (this.step !== 'dados' || this.loading) return;
        if (this.processId) {
            this.setStep('checklist');
            return;
        }
        await this.createProcess();
    }

    validateDados() {
        const root = this.form || this.element;
        if (this.variantValue === 'offboarding') {
            const select = root.querySelector('#funcionario_id');
            const val = select?.tomselect ? select.tomselect.getValue() : select?.value;
            if (!val) {
                select?.focus();
                this.toast('Selecione o colaborador para continuar.', 'warning');
                return false;
            }
            return true;
        }

        let ok = true;
        ['nome', 'email'].forEach((fieldId) => {
            const input = root.querySelector(`#${fieldId}`);
            if (input && !input.value.trim()) {
                input.focus();
                ok = false;
            }
        });
        if (!ok) {
            this.toast('Preencha nome e e-mail para continuar.', 'warning');
        }
        return ok;
    }

    async createProcess() {
        if (!this.validateDados() || !this.form) return;

        this.setLoading(true);
        const fd = new FormData(this.form);

        try {
            const data = await this.postJson(this.createUrlValue, fd);
            this.processId = data.process.id;
            this.checklist = data.process.checklist || [];
            this.renderSummary(data.process);
            this.renderChecklist();
            this.updateProgress(data.process);
            this.setStep('checklist');
            this.toast(data.message || 'Processo iniciado.', 'success');
        } catch (err) {
            this.toast(err.message || 'Não foi possível iniciar o processo.', 'error');
        } finally {
            this.setLoading(false);
        }
    }

    async toggleItem(event) {
        if (!this.processId) return;

        const checkbox = event.currentTarget;
        const itemId = checkbox.dataset.itemId;
        const done = checkbox.checked;
        const row = checkbox.closest('.unio-checklist-item');

        const fd = new FormData();
        fd.append('_token', this.csrfActionValue);
        fd.append('action', 'toggle');
        fd.append('item_id', itemId);
        fd.append('done', done ? '1' : '0');

        try {
            const data = await this.postJson(this.actionUrlFor(this.processId), fd);
            this.checklist = data.process.checklist || [];
            this.renderChecklist();
            this.updateProgress(data.process);
        } catch (err) {
            checkbox.checked = !done;
            if (row) row.classList.toggle('is-done', !done);
            this.toast(err.message || 'Não foi possível atualizar o item.', 'error');
        }
    }

    async completeProcess() {
        if (!this.processId || this.loading) return;

        const msg = this.confirmMessageValue || 'Concluir este processo?';
        if (!window.confirm(msg)) return;

        this.setLoading(true);
        const fd = new FormData();
        fd.append('_token', this.csrfActionValue);
        fd.append('action', 'complete');

        try {
            const data = await this.postJson(this.actionUrlFor(this.processId), fd);
            this.toast(data.message || 'Processo concluído.', 'success');
            this.closeAndRefresh();
        } catch (err) {
            this.toast(err.message || 'Não foi possível concluir.', 'error');
            this.setLoading(false);
        }
    }

    closeAndRefresh() {
        const root = this.element.closest('[data-unio-offcanvas]');
        const id = root?.getAttribute('data-unio-offcanvas');
        if (id && window.UnioOffcanvas) {
            window.UnioOffcanvas.close(id);
        }
        const url = new URL(window.location.href);
        url.searchParams.delete('open_nova');
        url.searchParams.delete('funcionario_id');
        window.location.assign(url.toString());
    }

    actionUrlFor(id) {
        return this.actionUrlValue.replace(/\/0(\?|$)/, `/${id}$1`);
    }

    async postJson(url, body) {
        const res = await fetch(url, {
            method: 'POST',
            body,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) {
            throw new Error(data.error || data.message || 'Erro na requisição.');
        }
        return data;
    }

    renderSummary(process) {
        if (!this.hasSummaryTarget) return;

        const lines = [];
        if (process.nome) {
            lines.push(`<strong>${this.escape(process.nome)}</strong>`);
        }
        if (process.email) {
            lines.push(this.escape(process.email));
        }
        if (process.funcionarioNome) {
            lines.push(this.escape(process.funcionarioNome));
        }
        if (process.cargo) {
            lines.push(this.escape(process.cargo));
        }

        this.summaryTarget.innerHTML = lines.join('<br>');
        this.summaryTarget.hidden = lines.length === 0;
    }

    renderChecklist() {
        if (!this.hasChecklistHostTarget) return;

        const ul = document.createElement('ul');
        ul.className = 'unio-checklist';
        ul.setAttribute('role', 'list');

        this.checklist.forEach((item) => {
            const li = document.createElement('li');
            li.className = `unio-checklist-item${item.done ? ' is-done' : ''}`;
            li.setAttribute('role', 'listitem');

            const label = document.createElement('label');
            label.className = 'unio-checklist-item-inner';

            const input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'unio-checklist-input';
            input.checked = !!item.done;
            input.dataset.itemId = item.id;
            input.setAttribute('aria-label', item.label);

            const box = document.createElement('span');
            box.className = 'unio-checklist-box';
            box.setAttribute('aria-hidden', 'true');
            box.innerHTML = '<i class="fas fa-check"></i>';

            const text = document.createElement('span');
            text.className = 'unio-checklist-text';
            text.textContent = item.label;

            label.append(input, box, text);
            li.append(label);
            ul.append(li);
        });

        this.checklistHostTarget.innerHTML = '';
        this.checklistHostTarget.append(ul);
    }

    updateProgress(process) {
        const checklistPct = process.progress ?? 0;
        const stepIdx = STEPS.indexOf(this.step);
        const pct = stepIdx === 0
            ? 50
            : Math.min(100, 50 + Math.round(checklistPct / 2));

        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = `${pct}%`;
            this.progressBarTarget.setAttribute('aria-valuenow', String(pct));
        }
        if (this.hasProgressPctTarget) {
            this.progressPctTarget.textContent = `${pct}%`;
        }
        if (this.hasCompleteTarget) {
            const complete = !!process.complete;
            this.completeTarget.disabled = !complete || this.loading;
        }
        if (this.hasCompleteHintTarget) {
            this.completeHintTarget.hidden = !!process.complete;
        }
    }

    setStep(step) {
        if (!STEPS.includes(step)) return;
        this.step = step;
        const stepIdx = STEPS.indexOf(step);

        this.panelTargets.forEach((panel) => {
            const active = panel.dataset.rhProcessPanel === step;
            panel.toggleAttribute('hidden', !active);
            panel.classList.toggle('is-active', active);
        });

        this.stepTargets.forEach((el) => {
            const key = el.dataset.rhProcessStep;
            const idx = STEPS.indexOf(key);
            el.classList.remove('rh-admissao-step--active', 'rh-admissao-step--done', 'rh-admissao-step--next');
            if (idx < stepIdx) {
                el.classList.add('rh-admissao-step--done');
            } else if (idx === stepIdx) {
                el.classList.add('rh-admissao-step--active');
            } else {
                el.classList.add('rh-admissao-step--next');
            }
        });

        const labels = this.variantValue === 'offboarding'
            ? { dados: 'Dados do desligamento', checklist: 'Checklist de offboarding' }
            : { dados: 'Dados do colaborador', checklist: 'Checklist de onboarding' };

        if (this.hasProgressLabelTarget) {
            this.progressLabelTarget.textContent = `${labels[step]} · ${stepIdx + 1} de ${STEPS.length}`;
        }

        const pct = stepIdx === 0 ? 50 : (this.processId ? 50 + Math.round((this.checklistProgress() || 0) / 2) : 50);
        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = `${pct}%`;
            this.progressBarTarget.setAttribute('aria-valuenow', String(pct));
        }
        if (this.hasProgressPctTarget) {
            this.progressPctTarget.textContent = `${pct}%`;
        }

        if (this.hasPrevTarget) {
            this.prevTarget.hidden = stepIdx === 0;
        }
        if (this.hasNextTarget) {
            this.nextTarget.hidden = stepIdx !== 0;
        }
        if (this.hasCompleteTarget) {
            this.completeTarget.hidden = stepIdx !== 1;
            this.completeTarget.disabled = stepIdx !== 1 || !this.isChecklistComplete();
        }
        if (this.hasCompleteHintTarget) {
            this.completeHintTarget.hidden = stepIdx !== 1 || this.isChecklistComplete();
        }
        if (this.hasRequiredNoteTarget) {
            this.requiredNoteTarget.hidden = stepIdx !== 0;
        }

        const scrollHost = this.element.querySelector('.unio-offcanvas-body');
        if (scrollHost) {
            scrollHost.scrollTop = 0;
        }
    }

    checklistProgress() {
        if (!this.checklist.length) return 0;
        const done = this.checklist.filter((i) => i.done).length;
        return Math.round((done / this.checklist.length) * 100);
    }

    isChecklistComplete() {
        return this.checklist.length > 0 && this.checklist.every((i) => i.done);
    }

    setLoading(loading) {
        this.loading = loading;
        if (this.hasNextTarget) {
            this.nextTarget.disabled = loading;
        }
        if (this.hasCompleteTarget) {
            this.completeTarget.disabled = loading || (this.step === 'checklist' && !this.isChecklistComplete());
        }
    }

    toast(message, tone) {
        if (window.UnioToast) {
            window.UnioToast.show(message, tone);
        }
    }

    escape(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}
