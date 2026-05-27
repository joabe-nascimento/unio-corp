import { Controller } from '@hotwired/stimulus';

const SECTION_IDS = ['ident', 'contrato', 'endereco', 'banco', 'obs'];
const SECTION_LABELS = {
    ident: 'Identificação',
    contrato: 'Contrato',
    endereco: 'Endereço',
    banco: 'Bancário',
    obs: 'Observações',
};

export default class extends Controller {
    static targets = [
        'panel',
        'progressBar',
        'progressPct',
        'progressLabel',
        'photo',
        'prev',
        'next',
        'submit',
        'requiredNote',
    ];

    connect() {
        this.current = 'ident';
        this.bindMasks();
        this.bindCep();
        this.setSection(this.current);
    }

    get form() {
        return this.element.querySelector('form') || this.element.closest('form');
    }

    bindMasks() {
        const root = this.form || this.element;
        root.querySelectorAll('[data-mask]').forEach((input) => {
            this.applyMask(input);
            input.addEventListener('input', () => this.applyMask(input));
        });
    }

    digits(value) {
        return String(value || '').replace(/\D+/g, '');
    }

    applyMask(input) {
        const type = input.getAttribute('data-mask');
        const raw = input.value;
        if (type === 'cpf') {
            let d = this.digits(raw).slice(0, 11);
            if (d.length > 9) d = `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
            else if (d.length > 6) d = `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
            else if (d.length > 3) d = `${d.slice(0, 3)}.${d.slice(3)}`;
            input.value = d;
        } else if (type === 'phone') {
            const d = this.digits(raw).slice(0, 11);
            if (d.length <= 2) input.value = d ? `(${d}` : '';
            else if (d.length <= 6) input.value = `(${d.slice(0, 2)}) ${d.slice(2)}`;
            else if (d.length <= 10) input.value = `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
            else input.value = `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
        } else if (type === 'cep') {
            const d = this.digits(raw).slice(0, 8);
            input.value = d.length > 5 ? `${d.slice(0, 5)}-${d.slice(5)}` : d;
        } else if (type === 'agency') {
            input.value = this.digits(raw).slice(0, 6);
        } else if (type === 'money') {
            const d = this.digits(raw);
            input.value = d
                ? (parseInt(d, 10) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
                : '';
        }
    }

    bindCep() {
        const root = this.form || this.element;
        const cepInput = root.querySelector('[data-cep-lookup]');
        if (!cepInput) return;

        cepInput.addEventListener('blur', () => {
            const cep = this.digits(cepInput.value);
            if (cep.length !== 8) return;
            cepInput.classList.add('is-loading');
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then((r) => r.json())
                .then((data) => {
                    if (data.erro) return;
                    [['logradouro', data.logradouro], ['bairro', data.bairro], ['cidade', data.localidade], ['uf', data.uf]]
                        .forEach(([id, val]) => {
                            const el = root.querySelector(`#${id}`);
                            if (el && val && !el.value) el.value = val;
                        });
                })
                .catch(() => {})
                .finally(() => cepInput.classList.remove('is-loading'));
        });
    }

    goPrev() {
        const idx = SECTION_IDS.indexOf(this.current);
        if (idx > 0) this.setSection(SECTION_IDS[idx - 1]);
    }

    goNext() {
        if (!this.validateStep(this.current)) return;
        const idx = SECTION_IDS.indexOf(this.current);
        if (idx < SECTION_IDS.length - 1) this.setSection(SECTION_IDS[idx + 1]);
    }

    validateStep(id) {
        if (id !== 'ident') return true;
        const root = this.form || this.element;
        let ok = true;
        ['nome', 'email'].forEach((fieldId) => {
            const input = root.querySelector(`#${fieldId}`);
            if (input && !input.value.trim()) {
                input.focus();
                ok = false;
            }
        });
        if (!ok && window.HuplexToast) {
            window.HuplexToast.show('Preencha nome e e-mail para continuar.', 'warning');
        }
        return ok;
    }

    setSection(id) {
        if (!SECTION_IDS.includes(id)) return;
        this.current = id;

        this.panelTargets.forEach((panel) => {
            const active = panel.dataset.rhSectionPanel === id;
            panel.toggleAttribute('hidden', !active);
            panel.classList.toggle('is-active', active);
        });

        const idx = SECTION_IDS.indexOf(id);
        const pct = Math.round(((idx + 1) / SECTION_IDS.length) * 100);

        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = `${pct}%`;
            this.progressBarTarget.setAttribute('aria-valuenow', String(pct));
        }
        if (this.hasProgressPctTarget) this.progressPctTarget.textContent = `${pct}%`;
        if (this.hasProgressLabelTarget) {
            this.progressLabelTarget.textContent = `${SECTION_LABELS[id]} · ${idx + 1} de ${SECTION_IDS.length}`;
        }
        if (this.hasPhotoTarget) this.photoTarget.hidden = id !== 'ident';
        if (this.hasPrevTarget) this.prevTarget.hidden = idx === 0;
        if (this.hasNextTarget) this.nextTarget.hidden = idx === SECTION_IDS.length - 1;
        if (this.hasSubmitTarget) this.submitTarget.hidden = idx !== SECTION_IDS.length - 1;
        if (this.hasRequiredNoteTarget) {
            this.requiredNoteTarget.hidden = id !== 'ident';
        }

        const scrollHost = this.element.querySelector('.huplex-offcanvas-body');
        if (scrollHost) {
            scrollHost.scrollTop = 0;
        }
    }
}
