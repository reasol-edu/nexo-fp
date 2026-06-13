import { Controller } from '@hotwired/stimulus';

// Evita el doble envío de formularios deshabilitando los botones de submit
// tras el primer envío. Uso: <form data-controller="form-submit">
export default class extends Controller {
    connect() {
        this.submitted = false;
        this.element.addEventListener('submit', (event) => {
            if (this.submitted) {
                event.preventDefault();
                return;
            }
            this.submitted = true;
            this.element.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
                btn.disabled = true;
                btn.classList.add('opacity-60', 'cursor-not-allowed');
            });
        });
    }
}
