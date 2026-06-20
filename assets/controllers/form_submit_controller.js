import { Controller } from '@hotwired/stimulus';

// Evita el doble envío de formularios deshabilitando los botones de submit
// tras el primer envío y, cuando el servidor devuelve el formulario con errores,
// lleva el foco al primer campo inválido. Uso: <form data-controller="form-submit">
export default class extends Controller {
    connect() {
        this.submitted = false;
        this.element.addEventListener('submit', this.onSubmit);
        this.focusFirstError();
    }

    disconnect() {
        this.element.removeEventListener('submit', this.onSubmit);
    }

    onSubmit = (event) => {
        if (this.submitted) {
            event.preventDefault();
            return;
        }
        this.submitted = true;
        this.element.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
            btn.disabled = true;
            btn.classList.add('opacity-60', 'cursor-not-allowed');
            if (btn.tagName === 'BUTTON') {
                btn.querySelectorAll('svg').forEach((s) => { s.style.display = 'none'; });
                const spinner = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                spinner.setAttribute('class', 'animate-spin h-4 w-4 shrink-0');
                spinner.setAttribute('fill', 'none');
                spinner.setAttribute('viewBox', '0 0 24 24');
                spinner.setAttribute('aria-hidden', 'true');
                spinner.innerHTML =
                    '<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>' +
                    '<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 22 6.477 22 12h-4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>';
                btn.prepend(spinner);
            }
        });
    };

    focusFirstError() {
        const field = this.element.querySelector('[aria-invalid="true"]');
        if (!field) {
            return;
        }
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        field.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'center' });
        // TomSelect oculta el <select> original; enfocamos su control visible.
        const tomControl = field.closest('.ts-wrapper')
            || (field.id && this.element.querySelector(`#${CSS.escape(field.id)}-ts-control`));
        const target = field.offsetParent === null && tomControl
            ? tomControl.querySelector('input, [tabindex]') || tomControl
            : field;
        target.focus({ preventScroll: true });
    }
}
