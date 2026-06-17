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
