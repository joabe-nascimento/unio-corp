import { Controller } from '@hotwired/stimulus';

/**
 * Tom Select — selects com busca (visual Symfony UX Autocomplete)
 */
export default class extends Controller {
    static values = {
        placeholder: { type: String, default: 'Buscar…' },
    };

    connect() {
        const TomSelect = window.TomSelect;
        if (!TomSelect || this.element.tomselect) {
            return;
        }

        this.instance = new TomSelect(this.element, {
            allowEmptyOption: true,
            create: false,
            maxOptions: 200,
            placeholder: this.placeholderValue,
            plugins: ['dropdown_input'],
        });

        this.element.classList.add('huplex-tomselect-ready');
    }

    disconnect() {
        if (this.instance) {
            this.instance.destroy();
            this.instance = null;
        }
    }
}
